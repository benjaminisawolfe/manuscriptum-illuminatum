const { test, expect } = require('@playwright/test');

function alphaIgnoringThe(values) {
    return [...values].sort((left, right) => {
        const leftKey = left.replace(/^the\s+/i, '');
        const rightKey = right.replace(/^the\s+/i, '');
        const primary = leftKey.localeCompare(rightKey, undefined, { numeric: true, sensitivity: 'base' });

        return primary || left.localeCompare(right, undefined, { numeric: true, sensitivity: 'base' });
    });
}

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

async function wpRequest(page, api, path, options = {}) {
    const result = await page.evaluate(async ({ root, nonce, path: requestPath, options: requestOptions }) => {
        const response = await fetch(new URL(requestPath, root).toString(), {
            method: requestOptions.method || 'GET',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': nonce,
                ...(requestOptions.body ? { 'Content-Type': 'application/json' } : {}),
            },
            body: requestOptions.body ? JSON.stringify(requestOptions.body) : undefined,
        });

        return {
            ok: response.ok,
            status: response.status,
            body: await response.json(),
        };
    }, { ...api, path, options });

    expect(result.ok, `${path} returned ${result.status}`).toBe(true);

    return result.body;
}

async function saveCurrentWikiPost(page) {
    const blockEditor = page.locator('body.block-editor-page');

    if (await blockEditor.count()) {
        const update = page.getByRole('button', { name: /^(Save|Update)$/ }).last();
        await expect(update).toBeEnabled();

        await Promise.all([
            page.waitForResponse((response) => response.url().includes('/wp-json/wp/v2/ligatura_wiki/')
                && response.request().method() !== 'GET'
                && response.ok()),
            page.waitForResponse((response) => response.url().includes('/wp-admin/post.php')
                && response.request().method() === 'POST'
                && response.status() < 400),
            update.click(),
        ]);
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

async function createArtifactFixtures(page, api, marker, count) {
    return page.evaluate(async ({ root, nonce, marker: fixtureMarker, count: fixtureCount }) => {
        const termResponse = await fetch(new URL('wp/v2/ligatura_entry_type?slug=artifact', root).toString(), {
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': nonce },
        });
        const terms = await termResponse.json();

        if (!termResponse.ok || !terms[0]) {
            return { error: `Artifact term lookup failed with ${termResponse.status}.`, posts: [] };
        }

        const titles = Array.from({ length: fixtureCount }, (_, index) => {
            const number = String(index + 1).padStart(2, '0');
            return index === 0 ? `The ${fixtureMarker} Alpha` : `${fixtureMarker} Entry ${number}`;
        });
        const posts = await Promise.all(titles.map((title) => fetch(new URL('wp/v2/ligatura_wiki', root).toString(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify({
                title,
                content: `Temporary Speculum integration fixture for ${fixtureMarker}.`,
                excerpt: `Temporary Speculum fixture ${title}.`,
                status: 'publish',
                ligatura_entry_type: [terms[0].id],
            }),
        }).then(async (response) => ({
            ok: response.ok,
            status: response.status,
            body: await response.json(),
        }))));

        return { error: '', posts };
    }, { ...api, marker, count });
}

async function deleteWikiFixtures(page, api, ids) {
    if (!ids.length) {
        return;
    }

    await page.evaluate(async ({ root, nonce, ids: fixtureIds }) => {
        await Promise.all(fixtureIds.map((id) => fetch(
            new URL(`wp/v2/ligatura_wiki/${id}?force=true`, root).toString(),
            {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': nonce },
            }
        )));
    }, { ...api, ids });
}

test('homepage Speculum updates are text-only and ordered by last edit', async ({ page }) => {
    await page.goto('/');

    const section = page.locator('.manuscriptum-illuminatum-front-wiki');
    const updates = section.locator('.manuscriptum-illuminatum-card--wiki-update');

    await expect(section.getByRole('heading', { name: 'Latest Specula' })).toBeVisible();
    await expect(section.getByRole('link', { name: 'All Specula', exact: true })).toBeVisible();
    await expect(updates.first()).toBeVisible();
    await expect(section.locator('.manuscriptum-illuminatum-card__image, img')).toHaveCount(0);

    const modified = await updates.evaluateAll((items) => items.map((item) => item.dataset.modified));
    expect(modified).toEqual([...modified].sort((left, right) => right.localeCompare(left)));

    for (let index = 0; index < await updates.count(); index += 1) {
        const update = updates.nth(index);
        const excerpt = update.locator('.manuscriptum-illuminatum-card__body > p:not(.manuscriptum-illuminatum-card__meta):not(.manuscriptum-illuminatum-card__modified)');
        const edited = update.locator('.manuscriptum-illuminatum-card__modified');

        await expect(excerpt).toBeVisible();
        await expect(edited).toContainText(/Last edited:/);
        expect((await edited.boundingBox()).y).toBeGreaterThan((await excerpt.boundingBox()).y);
    }
});

test('single Speculum metadata, Saga Topic collection, and body width use the approved presentation', async ({ page }, testInfo) => {
    await page.goto('/wiki/covenant-of-the-quiet-bell/');

    const entry = page.locator('.manuscriptum-illuminatum-entry--wiki');
    const metadata = entry.locator('.manuscriptum-illuminatum-meta-list--plain');
    const topicRow = metadata.locator('div').filter({ has: page.getByText('Saga Topics', { exact: true }) });
    const topicLinks = topicRow.locator('dd a');

    await expect(entry.getByRole('heading', { level: 1, name: 'Covenant of the Quiet Bell' })).toBeVisible();
    await expect(metadata.locator('dt')).toHaveText(['Entry Type', 'In-World Date', 'Saga Topics']);
    await expect(entry.getByText('Status', { exact: true })).toHaveCount(0);
    await expect(entry.getByText('Real-World Reference', { exact: true })).toHaveCount(0);
    await expect(topicLinks).toHaveCount(2);
    await expect(topicRow.locator('dd')).toContainText('Covenant Affairs, Quiet Bell');

    for (const link of await topicLinks.all()) {
        await expect(link).toHaveAttribute('href', /\/wiki\/topics\/[a-z0-9-]+\/?$/);
    }

    const borderWidths = await metadata.locator('div').evaluateAll((items) => (
        items.map((item) => getComputedStyle(item).borderTopWidth)
    ));
    expect(borderWidths.every((width) => width === '0px')).toBe(true);

    const widths = await entry.evaluate((element) => ({
        header: element.querySelector('.manuscriptum-illuminatum-page-header').getBoundingClientRect().width,
        content: element.querySelector('.manuscriptum-illuminatum-wiki-content').getBoundingClientRect().width,
        summary: element.querySelector('.manuscriptum-illuminatum-summary').getBoundingClientRect().width,
    }));
    expect(Math.abs(widths.header - widths.content)).toBeLessThanOrEqual(2);
    expect(Math.abs(widths.header - widths.summary)).toBeLessThanOrEqual(2);

    const firstTopicName = (await topicLinks.first().textContent()).trim();
    await topicLinks.first().click();
    await expect(page).toHaveURL(/\/wiki\/topics\/[a-z0-9-]+\/?$/);
    await expect(page.getByRole('heading', { level: 1, name: firstTopicName })).toBeVisible();

    await page.goto('/wiki/topics/politics/');
    await expect(page.getByRole('heading', { level: 1, name: 'Politics' })).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-saga-topic-directory img, .manuscriptum-illuminatum-saga-topic-directory .manuscriptum-illuminatum-card__image')).toHaveCount(0);

    const topicDirectory = page.locator('.manuscriptum-illuminatum-saga-topic-directory');
    const topicGrid = topicDirectory.locator('.manuscriptum-illuminatum-wiki-teaser-grid');
    const topicTeasers = topicGrid.locator('.manuscriptum-illuminatum-speculum-teaser');
    const titles = await topicTeasers.locator('h3 a').allTextContents();
    expect(titles.length).toBeGreaterThan(0);
    expect(titles).toEqual(alphaIgnoringThe(titles));
    expect(titles.some((title) => /^The\s/i.test(title))).toBe(true);
    await expect(topicTeasers.locator('.manuscriptum-illuminatum-speculum-teaser__excerpt')).toHaveCount(titles.length);
    await expect(topicTeasers.locator('.manuscriptum-illuminatum-card__meta')).toHaveCount(0);
    await expect(page.getByRole('navigation', { name: 'Speculum collection' }).getByRole('link', { name: 'Back to all Speculum entries' })).toHaveAttribute('href', /\/wiki\/?$/);

    const topicColumns = await topicGrid.evaluate((element) => (
        getComputedStyle(element).gridTemplateColumns.split(/\s+/).filter(Boolean).length
    ));
    expect(topicColumns).toBe(testInfo.project.name === 'mobile' ? 1 : 2);

    const topicCardStyle = await topicTeasers.first().evaluate((element) => {
        const card = getComputedStyle(element);
        const body = getComputedStyle(element.querySelector('.manuscriptum-illuminatum-card__body'));

        return {
            backgroundColor: card.backgroundColor,
            borderStyle: card.borderStyle,
            paddingTop: body.paddingTop,
        };
    });
    const topicWidths = await page.locator('.manuscriptum-illuminatum-entry--saga-topic').evaluate((element) => ({
        header: element.querySelector('.manuscriptum-illuminatum-page-header').getBoundingClientRect().width,
        directory: element.querySelector('.manuscriptum-illuminatum-saga-topic-directory').getBoundingClientRect().width,
    }));
    expect(Math.abs(topicWidths.header - topicWidths.directory)).toBeLessThanOrEqual(2);

    await page.goto('/wiki/');
    const mainDirectoryWidth = await page.locator('.manuscriptum-illuminatum-wiki-directory').evaluate((element) => element.getBoundingClientRect().width);
    const directoryTeaser = page.locator('.manuscriptum-illuminatum-wiki-directory .manuscriptum-illuminatum-speculum-teaser').first();
    const directoryCardStyle = await directoryTeaser.evaluate((element) => {
        const card = getComputedStyle(element);
        const body = getComputedStyle(element.querySelector('.manuscriptum-illuminatum-card__body'));

        return {
            backgroundColor: card.backgroundColor,
            borderStyle: card.borderStyle,
            paddingTop: body.paddingTop,
        };
    });

    expect(topicCardStyle).toEqual(directoryCardStyle);
    expect(Math.abs(topicWidths.directory - mainDirectoryWidth)).toBeLessThanOrEqual(2);
});

test('Speculum editor uses validated name selectors and rich Public Summary', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Editor save/reload mutation is verified once on desktop.');
    test.setTimeout(90_000);

    const api = await getApiSettings(page);
    const marker = `FixtureEditor${Date.now()}`;
    const createdIds = [];

    try {
        const characters = await wpRequest(page, api, 'wp/v2/ligatura_character?per_page=100&context=edit&orderby=title&order=asc');
        const placeTerms = await wpRequest(page, api, 'wp/v2/ligatura_entry_type?slug=place');
        const eventTerms = await wpRequest(page, api, 'wp/v2/ligatura_entry_type?slug=event');
        const covenantTopics = await wpRequest(page, api, 'wp/v2/ligatura_saga_topic?slug=covenant-affairs');
        const chateauTopics = await wpRequest(page, api, 'wp/v2/ligatura_saga_topic?slug=quiet-bell');
        const topicTerms = [...covenantTopics, ...chateauTopics];
        const places = await wpRequest(page, api, `wp/v2/ligatura_wiki?per_page=100&context=edit&ligatura_entry_type=${placeTerms[0].id}`);
        const character = characters.find((item) => item.title.rendered.includes('Aveline')) || characters[0];
        const place = places.find((item) => item.title.rendered.includes('Village of Petit Andely')) || places[0];

        expect(characters.length).toBeGreaterThan(0);
        expect(places.length).toBeGreaterThan(0);
        expect(eventTerms.length).toBeGreaterThan(0);
        expect(topicTerms.length).toBe(2);

        const relatedThe = await wpRequest(page, api, 'wp/v2/ligatura_wiki', {
            method: 'POST',
            body: {
                title: `The ${marker} Alpha`,
                content: `Temporary related entry for ${marker}.`,
                excerpt: `Temporary related entry for ${marker}.`,
                status: 'publish',
                ligatura_entry_type: [eventTerms[0].id],
            },
        });
        createdIds.push(relatedThe.id);

        const relatedBeta = await wpRequest(page, api, 'wp/v2/ligatura_wiki', {
            method: 'POST',
            body: {
                title: `${marker} Beta`,
                content: `Temporary related entry for ${marker}.`,
                excerpt: `Temporary related entry for ${marker}.`,
                status: 'publish',
                ligatura_entry_type: [eventTerms[0].id],
            },
        });
        createdIds.push(relatedBeta.id);

        const entry = await wpRequest(page, api, 'wp/v2/ligatura_wiki', {
            method: 'POST',
            body: {
                title: `${marker} Main`,
                slug: marker.toLowerCase(),
                content: `<p>Main body for ${marker}.</p>`,
                excerpt: `Temporary editor fixture for ${marker}.`,
                status: 'publish',
                ligatura_entry_type: [eventTerms[0].id],
                ligatura_saga_topic: topicTerms.map((term) => term.id),
            },
        });
        createdIds.push(entry.id);

        await page.goto(`/wp-admin/post.php?post=${entry.id}&action=edit`, { waitUntil: 'domcontentloaded' });

        const box = page.locator('#manuscriptum-illuminatum-wiki-details');
        const charactersSelect = box.locator('select[name="ligatura_related_characters[]"]');
        const placesSelect = box.locator('select[name="ligatura_related_places[]"]');
        const entriesSelect = box.locator('select[name="ligatura_related_entries[]"]');

        await expect(box.getByText('Real-World Reference', { exact: true })).toHaveCount(0);
        await expect(box.getByText(/Related (Character|Place|Entry) IDs/)).toHaveCount(0);
        await expect(charactersSelect).toBeVisible();
        await expect(placesSelect).toBeVisible();
        await expect(entriesSelect).toBeVisible();
        await expect(box.locator('label[for="ligatura_related_characters"]')).toHaveText('Related Characters');
        await expect(box.locator('label[for="ligatura_related_places"]')).toHaveText('Related Places');
        await expect(box.locator('label[for="ligatura_related_entries"]')).toHaveText('Related Entries');
        await expect(box.locator('#wp-ligatura_public_summary-wrap')).toBeVisible();
        await expect(box.locator('#ligatura_public_summary-tmce')).toBeVisible();
        await expect(entriesSelect.locator(`option[value="${entry.id}"]`)).toHaveCount(0);
        await expect(charactersSelect.locator(`option[value="${character.id}"]`)).toContainText(character.title.rendered.replace(/<[^>]*>/g, ''));
        await expect(placesSelect.locator(`option[value="${place.id}"]`)).toContainText(place.title.rendered.replace(/<[^>]*>/g, ''));
        await expect(placesSelect.locator(`option[value="${relatedThe.id}"]`)).toHaveCount(0);

        const markerOptionTitles = await entriesSelect.locator('option').evaluateAll(
            (options, currentMarker) => options.map((option) => option.textContent.trim()).filter((title) => title.includes(currentMarker)),
            marker
        );
        expect(markerOptionTitles).toEqual([`The ${marker} Alpha`, `${marker} Beta`]);

        await charactersSelect.selectOption([String(character.id)]);
        await placesSelect.selectOption([String(place.id)]);
        await entriesSelect.selectOption([String(relatedThe.id), String(relatedBeta.id)]);
        await box.locator('#ligatura_public_summary-html').click();
        await box.locator('textarea[name="ligatura_public_summary"]').fill(`<p><strong>${marker} rich summary</strong> with semantic markup.</p>`);
        await saveCurrentWikiPost(page);

        await page.goto(`/wp-admin/post.php?post=${entry.id}&action=edit`, { waitUntil: 'domcontentloaded' });
        await expect(box.locator('select[name="ligatura_related_characters[]"]')).toHaveValues([String(character.id)]);
        await expect(box.locator('select[name="ligatura_related_places[]"]')).toHaveValues([String(place.id)]);
        await expect(box.locator('select[name="ligatura_related_entries[]"]')).toHaveValues([String(relatedThe.id), String(relatedBeta.id)]);
        await box.locator('#ligatura_public_summary-html').click();
        await expect(box.locator('textarea[name="ligatura_public_summary"]')).toHaveValue(new RegExp(`<strong>${marker} rich summary</strong>`));

        await page.goto(`/wiki/${marker.toLowerCase()}/`);
        await expect(page.locator('.manuscriptum-illuminatum-summary strong')).toHaveText(`${marker} rich summary`);
        const relatedTitles = await page.locator('.manuscriptum-illuminatum-related-list a').allTextContents();
        expect(relatedTitles).toEqual(alphaIgnoringThe(relatedTitles));
        expect(relatedTitles.filter((title) => title.includes(marker))).toEqual([`The ${marker} Alpha`, `${marker} Beta`]);

        await page.goto(`/wp-admin/post.php?post=${entry.id}&action=edit`, { waitUntil: 'domcontentloaded' });
        await box.locator('select[name="ligatura_related_characters[]"]').selectOption([]);
        await box.locator('select[name="ligatura_related_places[]"]').selectOption([]);
        await box.locator('select[name="ligatura_related_entries[]"]').selectOption([]);
        await saveCurrentWikiPost(page);
        await page.goto(`/wp-admin/post.php?post=${entry.id}&action=edit`, { waitUntil: 'domcontentloaded' });
        await expect(box.locator('select[name="ligatura_related_characters[]"] option:checked')).toHaveCount(0);
        await expect(box.locator('select[name="ligatura_related_places[]"] option:checked')).toHaveCount(0);
        await expect(box.locator('select[name="ligatura_related_entries[]"] option:checked')).toHaveCount(0);
    } finally {
        await deleteWikiFixtures(page, api, createdIds);
    }
});

test('Speculum directory uses two-column shared teasers and sorts visible titles ignoring The', async ({ page }, testInfo) => {
    await page.goto('/wiki/');

    const directory = page.locator('.manuscriptum-illuminatum-wiki-directory');
    const groups = directory.locator('.manuscriptum-illuminatum-wiki-group');

    await expect(groups.first()).toBeVisible();
    expect(await groups.count()).toBeGreaterThan(1);

    for (let index = 0; index < await groups.count(); index += 1) {
        const group = groups.nth(index);
        const grid = group.locator('.manuscriptum-illuminatum-wiki-teaser-grid');
        const teasers = grid.locator('.manuscriptum-illuminatum-speculum-teaser');
        const titles = await teasers.locator('h3 a').allTextContents();

        await expect(group.getByRole('heading', { level: 2 })).toBeVisible();
        await expect(grid).toBeVisible();
        expect(titles.length).toBeGreaterThan(0);
        expect(titles.length).toBeLessThanOrEqual(8);
        expect(titles).toEqual(alphaIgnoringThe(titles));
        await expect(teasers.locator('.manuscriptum-illuminatum-speculum-teaser__excerpt')).toHaveCount(titles.length);
        await expect(teasers.locator('.manuscriptum-illuminatum-card__meta, img, .manuscriptum-illuminatum-card__image')).toHaveCount(0);

        const columnCount = await grid.evaluate((element) => (
            getComputedStyle(element).gridTemplateColumns.split(/\s+/).filter(Boolean).length
        ));
        expect(columnCount).toBe(testInfo.project.name === 'mobile' ? 1 : 2);

        if (await group.locator('.manuscriptum-illuminatum-wiki-group__more').count()) {
            expect(titles).toHaveLength(8);
        }
    }

    const firstTeaser = directory.locator('.manuscriptum-illuminatum-speculum-teaser').first();
    const cardStyle = await firstTeaser.evaluate((element) => {
        const card = getComputedStyle(element);
        const body = getComputedStyle(element.querySelector('.manuscriptum-illuminatum-card__body'));

        return {
            backgroundColor: card.backgroundColor,
            borderStyle: card.borderStyle,
            paddingTop: Number.parseFloat(body.paddingTop),
        };
    });

    expect(cardStyle.backgroundColor).not.toBe('rgba(0, 0, 0, 0)');
    expect(cardStyle.borderStyle).toBe('solid');
    expect(cardStyle.paddingTop).toBeGreaterThan(0);
    await expect(directory.locator('.manuscriptum-illuminatum-speculum-teaser h3 a').filter({ hasText: /^The\s/ }).first()).toBeVisible();
});

test('Entry Type preview limit and detail route work generically', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Temporary Speculum fixtures exercise the shared route once.');
    test.setTimeout(60_000);

    const api = await getApiSettings(page);
    const marker = `FixtureArtifact${Date.now()}`;
    const createdIds = [];

    try {
        const created = await createArtifactFixtures(page, api, marker, 9);

        expect(created.error).toBe('');
        for (const result of created.posts) {
            if (result.body && result.body.id) {
                createdIds.push(result.body.id);
            }
            expect(result.ok, `fixture creation returned ${result.status}`).toBe(true);
        }

        await page.goto('/wiki/');
        const group = page.locator('.manuscriptum-illuminatum-wiki-group[data-entry-type="artifact"]');
        const previewLinks = group.locator('.manuscriptum-illuminatum-wiki-teaser-grid .manuscriptum-illuminatum-speculum-teaser h3 a');
        const more = group.locator('.manuscriptum-illuminatum-wiki-group__more');

        await expect(group).toBeVisible();
        await expect(previewLinks).toHaveCount(8);
        await expect(more).toHaveText('See More...');
        await expect(more).toHaveAttribute('href', /\/wiki\/artifact\/?$/);

        await more.click();
        await expect(page).toHaveURL(/\/wiki\/artifact\/?$/);
        await expect(page.getByRole('heading', { level: 1, name: 'Artifact' })).toBeVisible();

        const detailLinks = page.locator('.manuscriptum-illuminatum-entry-type-directory .manuscriptum-illuminatum-wiki-entry-list a');
        const detailTitles = await detailLinks.allTextContents();
        const fixtureTitles = detailTitles.filter((title) => title.includes(marker));

        expect(fixtureTitles).toHaveLength(9);
        expect(detailTitles).toEqual(alphaIgnoringThe(detailTitles));
        expect(fixtureTitles).toContain(`The ${marker} Alpha`);

        await page.goto('/wiki/covenant-of-the-quiet-bell/');
        await expect(page.getByRole('heading', { level: 1, name: 'Covenant of the Quiet Bell' })).toBeVisible();
    } finally {
        await deleteWikiFixtures(page, api, createdIds);
    }
});

test('Entry Type create, rename, and delete rebuild term-dependent rewrite rules', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Rewrite lifecycle mutations exercise the shared rules once.');
    test.setTimeout(90_000);

    await page.goto('/wiki/artifact/');
    await expect(page.getByRole('heading', { level: 1, name: 'Artifact' })).toBeVisible();

    const api = await getApiSettings(page);
    const timestamp = Date.now();
    const initialSlug = `fixture-rewrite-${timestamp}`;
    const renamedSlug = `${initialSlug}-renamed`;
    const initialName = `Fixture Rewrite ${timestamp}`;
    const renamedName = `${initialName} Renamed`;
    const postTitle = `${initialName} Single`;
    let termId = 0;
    let postId = 0;

    try {
        const created = await page.evaluate(async ({ root, nonce, initialSlug: slug, initialName: name, postTitle: title }) => {
            const termResponse = await fetch(new URL('wp/v2/ligatura_entry_type', root).toString(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                body: JSON.stringify({ name, slug }),
            });
            const term = await termResponse.json();

            if (!termResponse.ok) {
                return { error: `Entry Type creation returned ${termResponse.status}.`, term, post: null };
            }

            const postResponse = await fetch(new URL('wp/v2/ligatura_wiki', root).toString(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                body: JSON.stringify({
                    title,
                    slug,
                    content: `Temporary rewrite lifecycle fixture for ${name}.`,
                    excerpt: `Temporary rewrite lifecycle fixture for ${name}.`,
                    status: 'publish',
                    ligatura_entry_type: [term.id],
                }),
            });
            const post = await postResponse.json();

            return {
                error: postResponse.ok ? '' : `Wiki fixture creation returned ${postResponse.status}.`,
                term,
                post,
            };
        }, { ...api, initialSlug, initialName, postTitle });

        expect(created.error).toBe('');
        termId = created.term.id;
        postId = created.post.id;

        await page.goto(`/wiki/${initialSlug}/`);
        await expect(page.getByRole('heading', { level: 1, name: initialName })).toBeVisible();
        await expect(page.locator('.manuscriptum-illuminatum-entry-type-directory').getByRole('link', { name: postTitle })).toBeVisible();

        const renamed = await page.evaluate(async ({ root, nonce, termId: id, renamedSlug: slug, renamedName: name }) => {
            const response = await fetch(new URL(`wp/v2/ligatura_entry_type/${id}`, root).toString(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                body: JSON.stringify({ name, slug }),
            });

            return { ok: response.ok, status: response.status, body: await response.json() };
        }, { ...api, termId, renamedSlug, renamedName });
        expect(renamed.ok, `Entry Type rename returned ${renamed.status}`).toBe(true);

        await page.goto(`/wiki/${renamedSlug}/`);
        await expect(page.getByRole('heading', { level: 1, name: renamedName })).toBeVisible();
        await expect(page.locator('.manuscriptum-illuminatum-entry-type-directory').getByRole('link', { name: postTitle })).toBeVisible();

        await page.goto(`/wiki/${initialSlug}/`);
        await expect(page.getByRole('heading', { level: 1, name: postTitle })).toBeVisible();

        const deleted = await page.evaluate(async ({ root, nonce, termId: id }) => {
            const response = await fetch(new URL(`wp/v2/ligatura_entry_type/${id}?force=true`, root).toString(), {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': nonce },
            });

            return { ok: response.ok, status: response.status, body: await response.json() };
        }, { ...api, termId });
        expect(deleted.ok, `Entry Type deletion returned ${deleted.status}`).toBe(true);
        termId = 0;

        const deletedRoute = await page.goto(`/wiki/${renamedSlug}/`);
        expect(deletedRoute.status()).toBe(404);
    } finally {
        await deleteWikiFixtures(page, api, postId ? [postId] : []);

        if (termId) {
            await page.evaluate(async ({ root, nonce, termId: id }) => {
                await fetch(new URL(`wp/v2/ligatura_entry_type/${id}?force=true`, root).toString(), {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'X-WP-Nonce': nonce },
                });
            }, { ...api, termId });
        }
    }
});

test('Saga Topic create, rename, and delete rebuild namespaced rewrite rules', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Saga Topic lifecycle mutations exercise the shared rules once.');
    test.setTimeout(90_000);

    const api = await getApiSettings(page);
    const timestamp = Date.now();
    const initialSlug = `fixture-topic-${timestamp}`;
    const renamedSlug = `${initialSlug}-renamed`;
    const initialName = `Fixture Topic ${timestamp}`;
    const renamedName = `${initialName} Renamed`;
    let termId = 0;
    let postId = 0;

    try {
        const term = await wpRequest(page, api, 'wp/v2/ligatura_saga_topic', {
            method: 'POST',
            body: { name: initialName, slug: initialSlug },
        });
        termId = term.id;

        const eventTerms = await wpRequest(page, api, 'wp/v2/ligatura_entry_type?slug=event');
        const entry = await wpRequest(page, api, 'wp/v2/ligatura_wiki', {
            method: 'POST',
            body: {
                title: `${initialName} Entry`,
                content: `Temporary Saga Topic rewrite fixture for ${initialName}.`,
                excerpt: `Temporary Saga Topic rewrite fixture for ${initialName}.`,
                status: 'publish',
                ligatura_entry_type: [eventTerms[0].id],
                ligatura_saga_topic: [term.id],
            },
        });
        postId = entry.id;

        await page.goto(`/wiki/topics/${initialSlug}/`);
        await expect(page.getByRole('heading', { level: 1, name: initialName })).toBeVisible();
        await expect(page.locator('.manuscriptum-illuminatum-saga-topic-directory').getByRole('link', { name: `${initialName} Entry` })).toBeVisible();

        await wpRequest(page, api, `wp/v2/ligatura_saga_topic/${termId}`, {
            method: 'POST',
            body: { name: renamedName, slug: renamedSlug },
        });

        await page.goto(`/wiki/topics/${renamedSlug}/`);
        await expect(page.getByRole('heading', { level: 1, name: renamedName })).toBeVisible();
        await expect(page.locator('.manuscriptum-illuminatum-saga-topic-directory').getByRole('link', { name: `${initialName} Entry` })).toBeVisible();

        await page.goto(`/wiki/topics/${initialSlug}/`);
        await expect(page.locator('.manuscriptum-illuminatum-saga-topic-directory')).toHaveCount(0);
        await expect(page.getByRole('heading', { level: 1, name: renamedName })).toHaveCount(0);
        await expect(page).toHaveURL(new RegExp(`/wiki/${initialSlug}-entry/?$`));

        await wpRequest(page, api, `wp/v2/ligatura_saga_topic/${termId}?force=true`, { method: 'DELETE' });
        termId = 0;

        await page.goto(`/wiki/topics/${renamedSlug}/`);
        await expect(page.locator('.manuscriptum-illuminatum-saga-topic-directory')).toHaveCount(0);
        await expect(page.getByRole('heading', { level: 1, name: renamedName })).toHaveCount(0);

        await page.goto('/wiki/artifact/');
        await expect(page.getByRole('heading', { level: 1, name: 'Artifact' })).toBeVisible();
        await page.goto('/wiki/covenant-of-the-quiet-bell/');
        await expect(page.getByRole('heading', { level: 1, name: 'Covenant of the Quiet Bell' })).toBeVisible();
    } finally {
        await deleteWikiFixtures(page, api, postId ? [postId] : []);

        if (termId) {
            await wpRequest(page, api, `wp/v2/ligatura_saga_topic/${termId}?force=true`, { method: 'DELETE' });
        }
    }
});

test('Speculum search and Entry Type filter update dynamically and restore groups', async ({ page }) => {
    await page.goto('/wiki/');

    const directory = page.locator('.manuscriptum-illuminatum-wiki-directory');
    const form = directory.locator('[data-speculum-search-form]');
    const toggle = form.getByRole('button', { name: 'Advanced Search' });
    const advanced = directory.locator('[data-speculum-advanced]');
    const query = form.getByLabel('Search Speculum');
    const results = directory.locator('[data-speculum-results]');

    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(advanced).toBeHidden();
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(advanced).toBeVisible();

    const entryType = form.getByLabel('Entry Type');
    const artifact = await entryType.locator('option', { hasText: /^Artifact$/ }).getAttribute('value');
    expect(artifact).toBeTruthy();
    await entryType.selectOption(artifact);
    await form.getByRole('button', { name: 'Apply Filters' }).click();
    await expect(results.locator('.manuscriptum-illuminatum-wiki-result').first()).toBeVisible();
    await expect(results.locator('.manuscriptum-illuminatum-wiki-result__meta').first()).toContainText('Artifact');
    await expect(page).toHaveURL(new RegExp(`speculum_entry_type=${artifact}`));

    await form.locator('[data-speculum-clear]').click();
    await expect(results.locator('.manuscriptum-illuminatum-wiki-group').first()).toBeVisible();

    await page.evaluate(() => {
        window.__speculumResetSentinel = 'same-document';
    });
    await query.fill('Whispering Cistern');
    await form.getByRole('button', { name: /^Search$/ }).click();
    await expect(results.locator('.manuscriptum-illuminatum-wiki-result')).toHaveCount(1);
    await expect(results.getByRole('link', { name: /Whispering Cistern/i })).toHaveAttribute('href', /\/wiki\/whispering-cistern-beneath-old-kitchen\/?$/);

    await query.fill('');
    await expect(results.locator('.manuscriptum-illuminatum-wiki-group').first()).toBeVisible();
    expect(await page.evaluate(() => window.__speculumResetSentinel)).toBe('same-document');
    expect(new URL(page.url()).searchParams.has('speculum_q')).toBe(false);

    await query.fill('phrase-that-no-speculum-entry-contains');
    await form.getByRole('button', { name: /^Search$/ }).click();
    await expect(results.locator('.manuscriptum-illuminatum-wiki-result')).toHaveCount(0);
    await expect(directory.getByText('No Speculum entries match these search criteria.')).toBeVisible();

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(advanced).toBeHidden();
});

test('Speculum search disables controls in flight and restores them after success or failure', async ({ page }) => {
    await page.goto('/wiki/');

    let releaseSuccess;
    let releaseFailure;
    let requestCount = 0;
    const successGate = new Promise((resolve) => { releaseSuccess = resolve; });
    const failureGate = new Promise((resolve) => { releaseFailure = resolve; });

    await page.route('**/wp-json/ligatura-manuscripti-illuminati/v1/speculum*', async (route) => {
        requestCount += 1;

        if (requestCount === 1) {
            await successGate;
            await route.continue();
            return;
        }

        await failureGate;
        await route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'Temporary test failure.' }) });
    });

    const directory = page.locator('.manuscriptum-illuminatum-wiki-directory');
    const form = directory.locator('[data-speculum-search-form]');
    const controls = form.locator('input, select, button');
    const query = form.getByLabel('Search Speculum');
    const search = form.getByRole('button', { name: /^Search$/ });
    const clear = form.locator('[data-speculum-clear]');

    await form.getByRole('button', { name: 'Advanced Search' }).click();
    await query.fill('Whispering Cistern');
    await search.click();
    await expect.poll(() => requestCount).toBe(1);
    expect(await controls.evaluateAll((items) => items.every((item) => item.disabled))).toBe(true);
    await expect(clear).toHaveAttribute('aria-disabled', 'true');
    await form.evaluate((element) => element.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    expect(requestCount).toBe(1);

    releaseSuccess();
    await expect(directory.locator('.manuscriptum-illuminatum-wiki-result')).toHaveCount(1);
    expect(await controls.evaluateAll((items) => items.every((item) => !item.disabled))).toBe(true);
    await expect(clear).toHaveAttribute('aria-disabled', 'false');

    await query.fill('request-that-will-fail');
    await search.click();
    await expect.poll(() => requestCount).toBe(2);
    expect(await controls.evaluateAll((items) => items.every((item) => item.disabled))).toBe(true);
    releaseFailure();
    await expect(directory.getByText('The Speculum results could not be loaded. Please try again.')).toBeVisible();
    expect(await controls.evaluateAll((items) => items.every((item) => !item.disabled))).toBe(true);
    await expect(query).toHaveValue('request-that-will-fail');
    await expect(clear).toHaveAttribute('aria-disabled', 'false');
    await expect(directory.locator('.manuscriptum-illuminatum-wiki-result')).toHaveCount(1);
});
