const { test, expect } = require('@playwright/test');

async function getApiSettings(page) {
    await page.goto('/wp-admin/', { waitUntil: 'domcontentloaded' });

    await expect(page).not.toHaveURL(/wp-login\.php/);

    const api = await page.evaluate(() => {
        const settings = window.wpApiSettings || {};
        const apiLink = document.querySelector('link[rel="https://api.w.org/"]');

        return {
            nonce: settings.nonce || '',
            root: settings.root || (apiLink ? apiLink.href : ''),
        };
    });

    expect(api.nonce).toBeTruthy();
    expect(api.root).toBeTruthy();

    return api;
}

async function wpFetch(page, api, path) {
    const result = await page.evaluate(async ({ root, nonce, path: requestPath }) => {
        const response = await fetch(new URL(requestPath, root).toString(), {
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': nonce,
            },
        });

        return {
            ok: response.ok,
            status: response.status,
            body: await response.json(),
        };
    }, { ...api, path });

    expect(result.ok, `${path} returned ${result.status}`).toBe(true);

    return result.body;
}

async function getEntryAndAuthor(page, api, restBase, slug) {
    const entries = await wpFetch(page, api, `wp/v2/${restBase}?slug=${slug}&context=edit`);

    expect(entries.length).toBeGreaterThan(0);

    const entry = entries[0];
    const author = await wpFetch(page, api, `wp/v2/users/${entry.author}?context=edit`);

    return { entry, author };
}

function textFromRendered(value) {
    return String(value || '').replace(/<[^>]*>/g, '').trim();
}

async function saveCurrentPost(page) {
    const blockEditor = page.locator('body.block-editor-page');

    if (await blockEditor.count()) {
        const blockUpdate = page.getByRole('button', { name: /^(Save|Update)$/ }).last();
        await expect(blockUpdate).toBeEnabled();

        const postSaved = page.waitForResponse((response) => {
            return response.url().includes('/wp-json/wp/v2/ligatura_diary/')
                && response.request().method() !== 'GET'
                && response.ok();
        });
        const metaBoxesSaved = page.waitForResponse((response) => {
            return response.url().includes('/wp-admin/post.php')
                && response.request().method() === 'POST'
                && response.status() < 400;
        });

        await blockUpdate.click();
        await Promise.all([postSaved, metaBoxesSaved]);
        return;
    }

    const classicUpdate = page.locator('#publish');

    if (await classicUpdate.isVisible().catch(() => false)) {
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
            classicUpdate.click(),
        ]);
        return;
    }

    throw new Error('Could not identify a supported WordPress editor save control.');
}

async function expectNicknameInsteadOfDisplayName(locator, author) {
    const nickname = (author.nickname || '').trim();
    const displayName = (author.name || '').trim();

    expect(nickname).toBeTruthy();

    await expect(locator).toContainText(nickname);

    if (displayName && displayName !== nickname) {
        await expect(locator).not.toContainText(displayName);
    }
}

test('Annales use WordPress nicknames while Journals use their Character Author', async ({ page }) => {
    const api = await getApiSettings(page);
    const post = await getEntryAndAuthor(page, api, 'posts', 'spring-rain-over-the-lower-bailey');
    const journal = await getEntryAndAuthor(page, api, 'ligatura_diary', 'guarin-begins-courteous-audit');
    const characterId = Number(journal.entry.meta?.ligatura_diary_character || 0);
    const character = await wpFetch(page, api, `wp/v2/ligatura_character/${characterId}?context=edit`);
    const characterName = textFromRendered(character.title.rendered);

    expect(characterId).toBeGreaterThan(0);
    expect(characterName).toBeTruthy();

    await page.goto(post.entry.link);
    await expectNicknameInsteadOfDisplayName(page.locator('.manuscriptum-illuminatum-entry .manuscriptum-illuminatum-kicker').first(), post.author);

    await page.goto(journal.entry.link);
    await expect(page.locator('.manuscriptum-illuminatum-journal-header__author')).toHaveText(characterName);

    await page.goto('/');
    await expect(page.locator('.manuscriptum-illuminatum-journal-row[data-saga-date="1204-06-12"] .manuscriptum-illuminatum-card__meta').first()).toContainText(characterName);

    await page.goto('/journals/');
    await expect(page.locator('.manuscriptum-illuminatum-journal-row[data-saga-date="1204-06-12"] .manuscriptum-illuminatum-card__meta').first()).toContainText(characterName);
    await page.getByRole('button', { name: 'Advanced Search' }).click();

    await expect(page.locator('#journal-author')).toHaveCount(0);
    await expect(page.locator(`#journal-character option[value="${characterId}"]`)).toHaveText(characterName);

    for (const realName of [journal.author.nickname, journal.author.name].map((value) => String(value || '').trim()).filter(Boolean)) {
        if (realName !== characterName) {
            await expect(page.locator('.manuscriptum-illuminatum-journal-row[data-saga-date="1204-06-12"] .manuscriptum-illuminatum-card__meta').first()).not.toContainText(realName);
        }
    }
});

test('Journal editor exposes Saga Date and one Character Author dropdown', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Editor save/reload mutation is verified once on desktop.');
    test.setTimeout(60_000);

    const api = await getApiSettings(page);
    const { entry } = await getEntryAndAuthor(page, api, 'ligatura_diary', 'aveline-notes-spring-correspondence');
    const characters = await wpFetch(page, api, 'wp/v2/ligatura_character?per_page=100&context=edit&orderby=title&order=asc');

    await page.goto(`/wp-admin/post.php?post=${entry.id}&action=edit`, { waitUntil: 'domcontentloaded' });

    const box = page.locator('#manuscriptum-illuminatum-diary-details');
    const relatedCharacter = box.locator('select[name="ligatura_diary_character"]');
    const originalCharacter = await relatedCharacter.inputValue();
    const replacement = characters.find((character) => String(character.id) !== originalCharacter) || characters[0];

    expect(characters.length).toBeGreaterThan(1);

    await expect(box).toContainText('Journal Details');
    await expect(box.locator('label[for="ligatura_diary_character"]')).toHaveText('Character Author');
    await expect(relatedCharacter).toBeVisible();
    await expect(box.locator('input[name="ligatura_diary_character"]')).toHaveCount(0);
    await expect(box.locator('input[name="ligatura_diary_saga_date"]')).toBeVisible();
    await expect(box).not.toContainText('In-World Date');
    await expect(box.locator('input[name="ligatura_diary_in_world_date"]')).toHaveCount(0);

    const optionLabels = await relatedCharacter.locator('option').evaluateAll(
        (options) => options.map((option) => option.textContent.trim()).filter(Boolean)
    );

    expect(optionLabels).toContain('No Character Author');
    expect(optionLabels).toContain(textFromRendered(replacement.title.rendered));

    await relatedCharacter.selectOption(String(replacement.id));
    await saveCurrentPost(page);
    await page.goto(`/wp-admin/post.php?post=${entry.id}&action=edit`, { waitUntil: 'domcontentloaded' });
    await expect(box.locator('select[name="ligatura_diary_character"]')).toHaveValue(String(replacement.id));

    await box.locator('select[name="ligatura_diary_character"]').selectOption('');
    await saveCurrentPost(page);
    await page.goto(`/wp-admin/post.php?post=${entry.id}&action=edit`, { waitUntil: 'domcontentloaded' });
    await expect(box.locator('select[name="ligatura_diary_character"]')).toHaveValue('');

    if (originalCharacter) {
        await box.locator('select[name="ligatura_diary_character"]').selectOption(originalCharacter);
        await saveCurrentPost(page);
    }
});
