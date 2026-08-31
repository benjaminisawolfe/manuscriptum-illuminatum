const { test, expect } = require('@playwright/test');
const { getApiSettings, uploadFixtureImage, wpRequest } = require('./support/personae-fixture');

function descending(values) {
    return [...values].sort((left, right) => right.localeCompare(left));
}

async function deleteFixtures(page, api, ids) {
    if (!ids.filter(Boolean).length) {
        return;
    }

    await page.evaluate(async ({ root, nonce, ids: fixtureIds }) => {
        await Promise.all(fixtureIds.filter(Boolean).map((id) => fetch(
            new URL(`wp/v2/ligatura_covenant/${id}?force=true`, root),
            { method: 'DELETE', credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce } }
        )));
    }, { ...api, ids });
}

async function saveCurrentCovenant(page) {
    const blockEditor = page.locator('body.block-editor-page');

    if (await blockEditor.count()) {
        const update = page.getByRole('button', { name: /^(Save|Update)$/ }).last();
        await expect(update).toBeEnabled();
        await Promise.all([
            page.waitForResponse((response) => response.url().includes('/wp-json/wp/v2/ligatura_covenant/')
                && response.request().method() !== 'GET' && response.ok()),
            page.waitForResponse((response) => response.url().includes('/wp-admin/post.php')
                && response.request().method() === 'POST' && response.status() < 400),
            update.click(),
        ]);
        return;
    }

    const classicUpdate = page.locator('#publish');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        classicUpdate.click(),
    ]);
}

test('Covenant Records mirrors the Commentarii directory architecture', async ({ page }) => {
    await page.goto('/covenant-records/');

    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expect(page.getByRole('heading', { level: 1, name: 'Covenant Records' })).toBeVisible();
    await expect(page.getByText(/editable covenant-records page/i)).toBeVisible();

    const directory = page.locator('.manuscriptum-illuminatum-covenant-directory');
    const rows = directory.locator('.manuscriptum-illuminatum-covenant-record-row');
    await expect(directory.getByLabel('Search Covenant Records')).toBeVisible();
    await expect(rows.first()).toBeVisible();
    expect(await rows.count()).toBeLessThanOrEqual(10);
    await expect(page.locator('.manuscriptum-illuminatum-commentarii-personae')).toHaveCount(0);

    const advanced = directory.locator('[data-covenant-advanced]');
    const toggle = directory.getByRole('button', { name: 'Advanced Search' });
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(advanced).toBeHidden();
    await toggle.click();
    await expect(advanced).toBeVisible();
    await expect(advanced.getByLabel('Saga Topic')).toBeVisible();
    await expect(advanced.getByLabel('Entry Type')).toBeVisible();
    await expect(advanced.getByLabel('Related Persona')).toBeVisible();
    await expect(advanced.getByLabel('Related Place')).toBeVisible();
    await expect(advanced.getByLabel('Related Entry')).toBeVisible();
    await expect(advanced.getByLabel('From Saga Date')).toBeVisible();
    await expect(advanced.getByLabel('To Saga Date')).toBeVisible();

    const [directoryBox, rowBox] = await Promise.all([directory.boundingBox(), rows.first().boundingBox()]);
    expect(Math.abs(directoryBox.width - rowBox.width)).toBeLessThanOrEqual(2);

    expect((await page.request.get('/covenant-records/type/fixture-no-such-type/')).status()).toBe(404);
    expect((await page.request.get('/covenant-records/topics/fixture-no-such-topic/')).status()).toBe(404);
});

test('Covenant Records search disables controls and restores them after success and failure', async ({ page }) => {
    await page.goto('/covenant-records/');

    let releaseSuccess;
    let releaseFailure;
    let requestCount = 0;
    const successGate = new Promise((resolve) => { releaseSuccess = resolve; });
    const failureGate = new Promise((resolve) => { releaseFailure = resolve; });

    await page.route('**/wp-json/ligatura-manuscripti-illuminati/v1/covenant-records*', async (route) => {
        requestCount += 1;
        if (requestCount === 1) {
            await successGate;
            await route.continue();
            return;
        }
        await failureGate;
        await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
    });

    const form = page.locator('[data-covenant-search-form]');
    const controls = form.locator('input, select, button');
    const query = form.getByLabel('Search Covenant Records');
    const search = form.getByRole('button', { name: /^Search$/ });
    const clear = form.locator('[data-covenant-clear]');

    await query.fill('Library');
    await search.click();
    await expect.poll(() => requestCount).toBe(1);
    expect(await controls.evaluateAll((items) => items.every((item) => item.disabled))).toBe(true);
    await expect(clear).toHaveAttribute('aria-disabled', 'true');
    releaseSuccess();
    await expect(page.locator('[data-covenant-results] .manuscriptum-illuminatum-covenant-record-row')).toHaveCount(1);
    expect(await controls.evaluateAll((items) => items.every((item) => !item.disabled))).toBe(true);

    await query.fill('failure-query');
    await search.click();
    await expect.poll(() => requestCount).toBe(2);
    expect(await controls.evaluateAll((items) => items.every((item) => item.disabled))).toBe(true);
    releaseFailure();
    await expect(page.getByText('The Covenant Record results could not be loaded. Please try again.')).toBeVisible();
    expect(await controls.evaluateAll((items) => items.every((item) => !item.disabled))).toBe(true);
    await expect(query).toHaveValue('failure-query');
});

test('single Covenant Record renders scoped metadata, the saved Public Summary, relationships, and a constrained Campaign Image', async ({ page }, testInfo) => {
    const api = await getApiSettings(page);
    const records = await wpRequest(page, api, 'wp/v2/ligatura_covenant?slug=charter-chest-three-mismatched-keys&context=edit');
    const record = records[0];
    const media = await uploadFixtureImage(page, api);

    expect(record).toBeTruthy();
    await wpRequest(page, api, `wp/v2/media/${media.id}`, {
        method: 'POST',
        body: { alt_text: 'Gold key on a blue heraldic shield' },
    });
    await wpRequest(page, api, `wp/v2/ligatura_covenant/${record.id}`, {
        method: 'POST',
        body: { featured_media: media.id },
    });

    try {
        await page.goto('/covenant-records/charter-chest-three-mismatched-keys/');

    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expect(page.getByRole('heading', { level: 1, name: 'Charter Chest with Three Mismatched Keys' })).toBeVisible();

    const metadata = page.locator('.manuscriptum-illuminatum-covenant-metadata');
    await expect(metadata.locator('dt')).toHaveText(['Entry Type', 'Saga Date', 'Saga Topics']);
    await expect(metadata.getByRole('link', { name: 'Artifact' })).toHaveAttribute('href', /\/covenant-records\/type\/artifact\/?$/);
    await expect(metadata.getByRole('link', { name: 'Covenant Affairs' })).toHaveAttribute('href', /\/covenant-records\/topics\/covenant-affairs\/?$/);
    await expect(metadata.locator('dd').nth(1)).toContainText(/^[A-Z][a-z]+ \d{1,2}, \d{4}$/);

    const summary = page.locator('.manuscriptum-illuminatum-entry--covenant > .manuscriptum-illuminatum-summary');
    await expect(summary).toHaveText('A lockable chest holding charters, copies, and letters too important to trust to memory.');

    const headerBox = await page.locator('.manuscriptum-illuminatum-entry--covenant > .manuscriptum-illuminatum-page-header').boundingBox();
    const content = page.locator('.manuscriptum-illuminatum-covenant-content');
    const contentBox = await content.boundingBox();
    expect(Math.abs(headerBox.width - contentBox.width)).toBeLessThanOrEqual(2);

    const image = content.locator('.manuscriptum-illuminatum-article-campaign-image img');
    await expect(image).toBeVisible();
    const [imageBox, imageMetrics, floatValue] = await Promise.all([
        image.boundingBox(),
        image.evaluate((element) => ({
            naturalWidth: element.naturalWidth,
            naturalHeight: element.naturalHeight,
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
        })),
        image.locator('..').evaluate((figure) => getComputedStyle(figure).float),
    ]);
    expect(imageMetrics.scrollWidth).toBeLessThanOrEqual(imageMetrics.clientWidth);
    expect(Math.abs((imageBox.width / imageBox.height) - (imageMetrics.naturalWidth / imageMetrics.naturalHeight))).toBeLessThan(0.03);

    if (testInfo.project.name === 'desktop') {
        expect(floatValue).toBe('right');
        expect(imageBox.width).toBeLessThanOrEqual((contentBox.width * 0.4) + 2);
        expect(imageBox.height).toBeLessThanOrEqual(450);
    } else {
        expect(floatValue).toBe('none');
        expect(imageBox.width).toBeLessThanOrEqual(contentBox.width + 2);
        expect(imageBox.height).toBeLessThanOrEqual((page.viewportSize().height * 0.5) + 2);
    }

    const personae = page.locator('[data-related-group="personae"]');
    const places = page.locator('[data-related-group="places"]');
    const entries = page.locator('[data-related-group="entries"]');
        await expect(personae.locator('a')).toHaveText(['Guarin of Tremere, Who Measures Every Door Before Entering']);
        await expect(places.locator('a')).toHaveCount(0);
        await expect(entries.locator('a')).toHaveText(['The Covenant Charter of Quiet Bells']);
        expect(await page.locator('.manuscriptum-illuminatum-covenant-relationships a').evaluateAll((links) => links.every((link) => !/^\d+$/.test(link.textContent.trim())))).toBe(true);
    } finally {
        await wpRequest(page, api, `wp/v2/ligatura_covenant/${record.id}`, {
            method: 'POST',
            body: { featured_media: record.featured_media || 0 },
        });
        await wpRequest(page, api, `wp/v2/media/${media.id}?force=true`, { method: 'DELETE' });
    }
});

test('Covenant Records search, Saga Date ordering, advanced filters, and More Covenant Records stay in sync', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Temporary directory fixtures are exercised once.');
    test.setTimeout(150_000);

    const api = await getApiSettings(page);
    const marker = `FixtureCovenant${Date.now()}`;
    const createdIds = [];

    try {
        const existing = await wpRequest(page, api, 'wp/v2/ligatura_covenant?per_page=100&context=edit');
        const staleIds = existing
            .filter((record) => (
                String(record.meta?.ligatura_public_summary || '').includes('FixtureCovenant')
                || /^Temporary Record \d+$/.test(String(record.title?.rendered || '').trim())
            ))
            .map((record) => record.id);
        await deleteFixtures(page, api, staleIds);

        const [topics, entryTypes, personae, placeTypes, entries] = await Promise.all([
            wpRequest(page, api, 'wp/v2/ligatura_saga_topic?slug=covenant-affairs'),
            wpRequest(page, api, 'wp/v2/ligatura_entry_type?slug=event'),
            wpRequest(page, api, 'wp/v2/ligatura_character?per_page=3&context=edit'),
            wpRequest(page, api, 'wp/v2/ligatura_entry_type?slug=place'),
            wpRequest(page, api, 'wp/v2/ligatura_wiki?per_page=3&context=edit'),
        ]);
        const places = await wpRequest(page, api, `wp/v2/ligatura_wiki?per_page=100&context=edit&ligatura_entry_type=${placeTypes[0].id}`);
        const place = places[0];
        expect(topics[0]?.id).toBeTruthy();
        expect(entryTypes[0]?.id).toBeTruthy();
        expect(personae.length).toBeGreaterThanOrEqual(3);
        expect(place?.id).toBeTruthy();
        expect(entries.length).toBeGreaterThanOrEqual(3);

        const created = await page.evaluate(async ({ root, nonce, marker: fixtureMarker, topicId, entryTypeId, personaIds, placeId, entryIds }) => {
            return Promise.all(Array.from({ length: 11 }, async (_, index) => {
                const day = String(index + 1).padStart(2, '0');
                const response = await fetch(new URL('wp/v2/ligatura_covenant', root), {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                    body: JSON.stringify({
                        title: `Temporary Record ${day}`,
                        content: '<p>Directory fixture body without the search token.</p>',
                        excerpt: 'Temporary Covenant Record.',
                        status: 'publish',
                        ligatura_saga_topic: [topicId],
                        ligatura_entry_type: [entryTypeId],
                        meta: {
                            ligatura_covenant_saga_date: `1205-01-${day}`,
                            ligatura_public_summary: `<p>${fixtureMarker} public summary ${day}</p>`,
                            ligatura_related_characters: personaIds.join(','),
                            ligatura_related_places: String(placeId),
                            ligatura_related_entries: entryIds.join(','),
                        },
                    }),
                });
                return { ok: response.ok, status: response.status, body: await response.json() };
            }));
        }, {
            ...api, marker, topicId: topics[0].id, entryTypeId: entryTypes[0].id,
            personaIds: personae.map((persona) => persona.id), placeId: place.id,
            entryIds: entries.map((entry) => entry.id),
        });

        for (const result of created) {
            expect(result.ok, `fixture creation returned ${result.status}`).toBe(true);
            createdIds.push(result.body.id);
        }

        await page.goto('/covenant-records/');
        const form = page.locator('[data-covenant-search-form]');
        const rows = page.locator('[data-covenant-results] .manuscriptum-illuminatum-covenant-record-row');
        const more = page.getByRole('button', { name: 'More Covenant Records' });
        await form.getByLabel('Search Covenant Records').fill(marker);
        await form.getByRole('button', { name: /^Search$/ }).click();
        await expect(rows).toHaveCount(10);

        const firstDates = await rows.evaluateAll((items) => items.map((item) => item.dataset.sagaDate));
        expect(firstDates).toEqual(descending(firstDates));
        await expect(more).toBeVisible();
        await more.click();
        await expect(rows).toHaveCount(11);
        await expect(more).toBeHidden();
        const allLinks = await rows.locator('h3 a').evaluateAll((items) => items.map((item) => item.href));
        expect(new Set(allLinks).size).toBe(11);

        await form.getByRole('button', { name: 'Advanced Search' }).click();
        await form.getByLabel('Saga Topic').selectOption('covenant-affairs');
        await form.getByLabel('Entry Type').selectOption('event');
        await form.getByLabel('Related Persona').selectOption(String(personae[0].id));
        await form.getByLabel('Related Place').selectOption(String(place.id));
        await form.getByLabel('Related Entry').selectOption(String(entries[0].id));
        await form.getByLabel('From Saga Date').fill('1205-01-11');
        await form.getByLabel('To Saga Date').fill('1205-01-11');
        await form.getByRole('button', { name: 'Apply Filters' }).click();
        await expect(rows).toHaveCount(1);
        await expect(rows.first()).toHaveAttribute('data-saga-date', '1205-01-11');

        await form.getByLabel('Search Covenant Records').fill('');
        await expect(rows).toHaveCount(1);
        expect(new URL(page.url()).searchParams.get('covenant_topic')).toBe('covenant-affairs');

        await page.goto('/covenant-records/type/event/');
        const typeDirectory = page.locator('.manuscriptum-illuminatum-covenant-directory');
        const typeForm = typeDirectory.locator('[data-covenant-search-form]');
        const typeRows = typeDirectory.locator('.manuscriptum-illuminatum-covenant-record-row');
        await expect(page.getByRole('heading', { level: 2, name: 'Covenant Records: Event' })).toBeVisible();
        await expect(page.locator('.manuscriptum-illuminatum-breadcrumb')).toContainText('Covenant Records › Event');
        await expect(typeForm.locator('input[name="covenant_entry_type"]')).toHaveValue('event');
        await expect(typeForm.getByLabel('Entry Type')).toHaveCount(0);
        await typeForm.getByLabel('Search Covenant Records').fill(marker);
        await typeForm.getByRole('button', { name: /^Search$/ }).click();
        await expect(typeRows).toHaveCount(10);
        expect(await typeRows.evaluateAll((items) => items.map((item) => item.dataset.sagaDate))).toEqual(
            descending(await typeRows.evaluateAll((items) => items.map((item) => item.dataset.sagaDate)))
        );
        await typeDirectory.getByRole('button', { name: 'More Covenant Records' }).click();
        await expect(typeRows).toHaveCount(11);
        expect(await typeRows.locator('h3').allTextContents()).toEqual(
            Array.from({ length: 11 }, (_, index) => `Temporary Record ${String(11 - index).padStart(2, '0')}`)
        );
        await typeForm.getByLabel('Search Covenant Records').fill('');
        await expect.poll(() => new URL(page.url()).pathname).toMatch(/\/covenant-records\/type\/event\/?$/);
        expect(new URL(page.url()).searchParams.get('covenant_entry_type')).toBeNull();
        await expect(typeForm.locator('input[name="covenant_entry_type"]')).toHaveValue('event');

        await page.goto('/covenant-records/topics/covenant-affairs/');
        const topicDirectory = page.locator('.manuscriptum-illuminatum-covenant-directory');
        const topicForm = topicDirectory.locator('[data-covenant-search-form]');
        const topicRows = topicDirectory.locator('.manuscriptum-illuminatum-covenant-record-row');
        await expect(page.getByRole('heading', { level: 2, name: 'Covenant Records: Covenant Affairs' })).toBeVisible();
        await expect(page.locator('.manuscriptum-illuminatum-breadcrumb')).toContainText('Covenant Records › Covenant Affairs');
        await expect(topicForm.locator('input[name="covenant_topic"]')).toHaveValue('covenant-affairs');
        await expect(topicForm.getByLabel('Saga Topic')).toHaveCount(0);
        await topicForm.getByLabel('Search Covenant Records').fill(marker);
        await topicForm.getByRole('button', { name: /^Search$/ }).click();
        await expect(topicRows).toHaveCount(10);
        await topicDirectory.getByRole('button', { name: 'More Covenant Records' }).click();
        await expect(topicRows).toHaveCount(11);
        expect(await topicRows.locator('h3').allTextContents()).toEqual(
            Array.from({ length: 11 }, (_, index) => `Temporary Record ${String(11 - index).padStart(2, '0')}`)
        );

        await page.goto('/covenant-records/temporary-record-11/');
        await expect(page.getByRole('heading', { level: 1, name: 'Temporary Record 11' })).toBeVisible();
        for (const [group, source] of [
            ['personae', personae],
            ['entries', entries],
        ]) {
            const expectedTitles = source.map((item) => item.title.rendered).sort((left, right) => (
                left.replace(/^The\s+/i, '').localeCompare(right.replace(/^The\s+/i, ''), undefined, { numeric: true, sensitivity: 'base' })
                || left.localeCompare(right, undefined, { numeric: true, sensitivity: 'base' })
            ));
            await expect(page.locator(`[data-related-group="${group}"] a`)).toHaveText(expectedTitles);
        }
        const placeTitle = (place.title.raw || place.title.rendered).replaceAll("'", '’');
        await expect(page.locator('[data-related-group="places"] a')).toHaveText([placeTitle]);
    } finally {
        await deleteFixtures(page, api, createdIds);
    }
});

test('Covenant Record editor mirrors Speculum selectors and uses one Journal-style Saga Date', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Editor save/reload mutation is verified once.');
    test.setTimeout(90_000);

    const api = await getApiSettings(page);
    const marker = `FixtureCovenantEditor${Date.now()}`;
    const createdIds = [];

    try {
        const [personae, places, entries] = await Promise.all([
            wpRequest(page, api, 'wp/v2/ligatura_character?per_page=1&context=edit'),
            wpRequest(page, api, 'wp/v2/ligatura_wiki?per_page=100&context=edit'),
            wpRequest(page, api, 'wp/v2/ligatura_wiki?per_page=1&context=edit'),
        ]);
        const record = await wpRequest(page, api, 'wp/v2/ligatura_covenant', {
            method: 'POST',
            body: { title: marker, content: `<p>${marker} body.</p>`, status: 'publish' },
        });
        createdIds.push(record.id);

        await page.goto(`/wp-admin/post.php?post=${record.id}&action=edit`, { waitUntil: 'domcontentloaded' });
        const box = page.locator('#manuscriptum-illuminatum-covenant-details');
        const characters = box.locator('select[name="ligatura_related_characters[]"]');
        const placeOptions = box.locator('select[name="ligatura_related_places[]"] option:not([value=""])');
        const relatedEntries = box.locator('select[name="ligatura_related_entries[]"]');
        const sagaDate = box.locator('input[name="ligatura_covenant_saga_date"]');

        await expect(box).toBeVisible();
        await expect(box.getByText('Real-World Reference', { exact: true })).toHaveCount(0);
        await expect(box.getByText('In-World Date', { exact: true })).toHaveCount(0);
        await expect(box.locator('label[for="ligatura_covenant_saga_date"]')).toHaveText('Saga Date');
        await expect(sagaDate).toHaveAttribute('type', 'date');
        await expect(box.locator('input[type="date"]')).toHaveCount(1);
        await expect(box.getByText(/Related (Character|Place|Entry) IDs/)).toHaveCount(0);
        await expect(characters.locator(`option[value="${personae[0].id}"]`)).toBeVisible();
        await expect(placeOptions.first()).toBeVisible();
        await expect(relatedEntries.locator(`option[value="${entries[0].id}"]`)).toBeVisible();
        await expect(box.locator('#wp-ligatura_public_summary-wrap')).toBeVisible();

        const placeId = await placeOptions.first().getAttribute('value');
        await characters.selectOption([String(personae[0].id)]);
        await box.locator('select[name="ligatura_related_places[]"]').selectOption([placeId]);
        await relatedEntries.selectOption([String(entries[0].id)]);
        await sagaDate.fill('1206-02-03');
        await box.locator('#ligatura_public_summary-html').click();
        await box.locator('textarea[name="ligatura_public_summary"]').fill(`<p><strong>${marker} summary</strong></p>`);
        await saveCurrentCovenant(page);

        await page.goto(`/wp-admin/post.php?post=${record.id}&action=edit`, { waitUntil: 'domcontentloaded' });
        await expect(box.locator('input[name="ligatura_covenant_saga_date"]')).toHaveValue('1206-02-03');
        await expect(box.locator('select[name="ligatura_related_characters[]"]')).toHaveValues([String(personae[0].id)]);
        await expect(box.locator('select[name="ligatura_related_places[]"]')).toHaveValues([placeId]);
        await expect(box.locator('select[name="ligatura_related_entries[]"]')).toHaveValues([String(entries[0].id)]);
        await box.locator('#ligatura_public_summary-html').click();
        await expect(box.locator('textarea[name="ligatura_public_summary"]')).toHaveValue(new RegExp(`${marker} summary`));
    } finally {
        await deleteFixtures(page, api, createdIds);
    }
});
