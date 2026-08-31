const { test, expect } = require('@playwright/test');
const {
    createPersonaeImageFixture,
    getApiSettings,
    restorePersonaeImageFixture,
    wpRequest,
} = require('./support/personae-fixture');

function titleSortKey(title) {
    return title.trim().replace(/^the\s+/i, '');
}

function sortedTitles(titles) {
    return [...titles].sort((left, right) => {
        const comparison = titleSortKey(left).localeCompare(titleSortKey(right), undefined, {
            numeric: true,
            sensitivity: 'base',
        });

        return comparison || left.localeCompare(right, undefined, { numeric: true, sensitivity: 'base' });
    });
}

function personaeControls(page) {
    const directory = page.locator('.manuscriptum-illuminatum-personae-directory');
    const form = directory.locator('[data-personae-search-form]');

    return {
        directory,
        form,
        query: form.getByLabel('Search Personae'),
        results: directory.locator('[data-personae-results]'),
        toggle: form.getByRole('button', { name: 'Advanced Search' }),
        advanced: directory.locator('[data-personae-advanced]'),
    };
}

async function clearFilters(controls) {
    await controls.form.locator('[data-personae-clear]').click();
    await expect(controls.directory).toHaveAttribute('data-grouped', 'true');
    await expect(controls.results.locator('.manuscriptum-illuminatum-personae-group').first()).toBeVisible();
}

test('Personae search is restrained, collapsed by default, and preserves the grouped directory', async ({ page }) => {
    await page.goto('/characters/');

    const controls = personaeControls(page);
    const pageHeader = page.locator('.manuscriptum-illuminatum-page-header');
    const intro = page.locator('.manuscriptum-illuminatum-content > p').first();

    await expect(controls.form).toBeVisible();
    await expect(controls.toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(controls.advanced).toBeHidden();
    await expect(controls.results.locator('.manuscriptum-illuminatum-personae-group').first()).toBeVisible();
    await expect(page.getByText(/editable Personae page introduces/i)).toBeVisible();

    const [headerBox, introBox, formBox, resultsBox] = await Promise.all([
        pageHeader.boundingBox(),
        intro.boundingBox(),
        controls.form.boundingBox(),
        controls.results.boundingBox(),
    ]);

    expect(formBox.y).toBeGreaterThan(headerBox.y + headerBox.height);
    expect(formBox.y).toBeGreaterThan(introBox.y);
    expect(resultsBox.y).toBeGreaterThan(formBox.y + formBox.height);
    expect(Math.abs(formBox.x + formBox.width - resultsBox.x - resultsBox.width)).toBeLessThanOrEqual(2);
});

test('simple Personae search is dynamic, Personae-only, bookmarkable, and restores groups when cleared', async ({ page }) => {
    await page.goto('/characters/');

    const controls = personaeControls(page);
    await page.evaluate(() => { window.__personaeResetSentinel = 'same-document'; });
    await controls.query.fill('Aveline of Bonisagus');
    await controls.form.getByRole('button', { name: /^Search$/ }).click();

    const cards = controls.results.locator('.manuscriptum-illuminatum-persona-teaser');
    await expect(cards).toHaveCount(1);
    await expect(cards.getByRole('link', { name: 'Aveline of Bonisagus' })).toHaveAttribute('href', /\/characters\/aveline-of-bonisagus\/?$/);
    await expect(controls.results.locator('.manuscriptum-illuminatum-personae-group')).toHaveCount(0);
    await expect(page).toHaveURL(/personae_q=Aveline(\+|%20)of(\+|%20)Bonisagus/);
    await expect(controls.results.locator('.manuscriptum-illuminatum-wiki-result, .manuscriptum-illuminatum-journal-row')).toHaveCount(0);

    await controls.query.fill('');
    await expect(controls.results.locator('.manuscriptum-illuminatum-personae-group').first()).toBeVisible();
    expect(new URL(page.url()).searchParams.has('personae_q')).toBe(false);
    expect(await page.evaluate(() => window.__personaeResetSentinel)).toBe('same-document');

    await controls.query.fill('phrase-that-no-persona-contains');
    await controls.form.getByRole('button', { name: /^Search$/ }).click();
    await expect(cards).toHaveCount(0);
    await expect(controls.directory.getByText('No Personae match these search criteria.')).toBeVisible();
});

test('bookmarkable Personae filters render the flat result state on first load', async ({ page }) => {
    await page.goto('/characters/?personae_tradition=Hermetic');

    const controls = personaeControls(page);
    await expect(controls.results.locator('.manuscriptum-illuminatum-personae-group')).toHaveCount(0);
    await expect(controls.results.locator('.manuscriptum-illuminatum-persona-teaser').first()).toBeVisible();
    await expect(controls.results).toContainText('Aveline of Bonisagus');
    await controls.toggle.click();
    await expect(controls.advanced.getByLabel('Tradition')).toHaveValue('Hermetic');
});

test('Advanced Personae search filters canonical taxonomies and stored metadata', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Canonical server-side filters are verified once; responsive controls have separate coverage.');
    test.setTimeout(90_000);

    const api = await getApiSettings(page);
    const characters = await wpRequest(page, api, 'wp/v2/ligatura_character?slug=aveline-of-bonisagus&context=edit');
    const character = characters[0];
    const originalOccupation = character.meta?.ligatura_occupation || '';
    const fixtureOccupation = `Fixture Search Librarian ${Date.now()}`;

    expect(character).toBeTruthy();

    try {
        await wpRequest(page, api, `wp/v2/ligatura_character/${character.id}`, {
            method: 'POST',
            body: { meta: { ligatura_occupation: fixtureOccupation } },
        });
        await page.goto('/characters/');

        const controls = personaeControls(page);
        await controls.toggle.click();
        await expect(controls.toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(controls.advanced).toBeVisible();

        const filters = [
            { label: 'Character Type', option: 'Grog', parameter: 'personae_type', expected: 'Renaud the Gatewarden', absent: 'Aveline of Bonisagus' },
            { label: 'House', option: 'Bonisagus', parameter: 'personae_house', expected: 'Aveline of Bonisagus', absent: 'Renaud the Gatewarden' },
            { label: 'Tradition', option: 'Mundane', parameter: 'personae_tradition', expected: 'Renaud the Gatewarden', absent: 'Aveline of Bonisagus' },
            { label: 'Player', option: 'Open troupe character', parameter: 'personae_player', expected: 'Renaud the Gatewarden', absent: 'Aveline of Bonisagus' },
            { label: 'Origin', option: 'Normandy', parameter: 'personae_origin', expected: 'Aveline of Bonisagus' },
            { label: 'Occupation', option: fixtureOccupation, parameter: 'personae_occupation', expected: 'Aveline of Bonisagus', absent: 'Renaud the Gatewarden' },
            { label: 'Saga Topic', option: 'Covenant Affairs', parameter: 'personae_topic', expected: 'Aveline of Bonisagus' },
        ];

        for (const filter of filters) {
            const select = controls.advanced.getByLabel(filter.label);
            const option = select.locator('option').filter({ hasText: filter.option }).filter({ hasNotText: /^All / }).first();
            const value = await option.getAttribute('value');

            expect(value, `${filter.label} should expose ${filter.option}`).toBeTruthy();
            await select.selectOption(value);
            await controls.advanced.getByRole('button', { name: 'Apply Filters' }).click();
            await expect(controls.directory).toHaveAttribute('data-grouped', 'false');
            await expect(controls.results.locator('.manuscriptum-illuminatum-persona-teaser').first()).toBeVisible();
            await expect(controls.results).toContainText(filter.expected);
            expect(new URL(page.url()).searchParams.get(filter.parameter)).toBe(value);

            if (filter.absent) {
                await expect(controls.results).not.toContainText(filter.absent);
            }

            await clearFilters(controls);
        }

        await controls.advanced.getByLabel('House').selectOption({ label: 'Bonisagus' });
        await controls.advanced.getByLabel('Occupation').selectOption({ label: fixtureOccupation });
        await controls.advanced.getByRole('button', { name: 'Apply Filters' }).click();
        await expect(controls.results.locator('.manuscriptum-illuminatum-persona-teaser')).toHaveCount(1);
        await expect(controls.results).toContainText('Aveline of Bonisagus');

        await controls.toggle.click();
        await expect(controls.toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(controls.advanced).toBeHidden();
    } finally {
        await wpRequest(page, api, `wp/v2/ligatura_character/${character.id}`, {
            method: 'POST',
            body: { meta: { ligatura_occupation: originalOccupation } },
        });
    }
});

test('clearing simple Personae search preserves active advanced filters', async ({ page }) => {
    await page.goto('/characters/');

    const controls = personaeControls(page);
    await controls.toggle.click();
    const house = controls.advanced.getByLabel('House');
    const bonisagus = await house.locator('option', { hasText: /^Bonisagus$/ }).getAttribute('value');

    await house.selectOption(bonisagus);
    await controls.advanced.getByRole('button', { name: 'Apply Filters' }).click();
    await expect(controls.directory).toHaveAttribute('data-grouped', 'false');
    const advancedLinks = await controls.results.locator('h3 a').evaluateAll((items) => items.map((item) => item.href));

    await controls.query.fill('Aveline');
    await controls.form.getByRole('button', { name: /^Search$/ }).click();
    await expect(controls.results.locator('.manuscriptum-illuminatum-persona-teaser')).toHaveCount(1);

    await controls.query.fill('');
    await expect.poll(() => new URL(page.url()).searchParams.has('personae_q')).toBe(false);
    await expect.poll(
        () => controls.results.locator('h3 a').evaluateAll((items) => items.map((item) => item.href))
    ).toEqual(advancedLinks);
    expect(await house.inputValue()).toBe(bonisagus);
    expect(new URL(page.url()).searchParams.get('personae_house')).toBe(bonisagus);
    await expect(controls.results.locator('.manuscriptum-illuminatum-personae-group')).toHaveCount(0);
});

test('simple search covers title, body content, and Brief Description', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The temporary WordPress fixture is needed only once.');
    test.setTimeout(60_000);

    const api = await getApiSettings(page);
    const types = await wpRequest(page, api, 'wp/v2/ligatura_character_type?slug=companion');
    const marker = Date.now();
    const titleMarker = `FixtureTitle${marker}`;
    const bodyMarker = `FixtureBody${marker}`;
    const briefMarker = `FixtureBrief${marker}`;
    let character;

    expect(types).toHaveLength(1);

    try {
        character = await wpRequest(page, api, 'wp/v2/ligatura_character', {
            method: 'POST',
            body: {
                title: `${titleMarker} Persona`,
                content: `Public body containing ${bodyMarker}.`,
                excerpt: 'An unrelated public excerpt.',
                status: 'publish',
                ligatura_character_type: [types[0].id],
                meta: {
                    ligatura_brief_description: `A description containing ${briefMarker}.`,
                    ligatura_player: 'Open troupe character',
                },
            },
        });

        await page.goto('/characters/');
        const controls = personaeControls(page);

        for (const searchTerm of [titleMarker, bodyMarker, briefMarker]) {
            await controls.query.fill(searchTerm);
            await controls.form.getByRole('button', { name: /^Search$/ }).click();
            await expect(controls.results.locator('.manuscriptum-illuminatum-persona-teaser')).toHaveCount(1);
            await expect(controls.results).toContainText(`${titleMarker} Persona`);
        }
    } finally {
        if (character) {
            await wpRequest(page, api, `wp/v2/ligatura_character/${character.id}?force=true`, { method: 'DELETE' });
        }
    }
});

test('Personae search disables controls in flight and restores them after success or failure', async ({ page }) => {
    await page.goto('/characters/');

    let releaseSuccess;
    let releaseFailure;
    let requestCount = 0;
    const successGate = new Promise((resolve) => { releaseSuccess = resolve; });
    const failureGate = new Promise((resolve) => { releaseFailure = resolve; });

    await page.route('**/wp-json/ligatura-manuscripti-illuminati/v1/personae*', async (route) => {
        requestCount += 1;

        if (requestCount === 1) {
            await successGate;
            await route.continue();
            return;
        }

        await failureGate;
        await route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'Temporary test failure.' }) });
    });

    const controls = personaeControls(page);
    const queryControls = controls.form.locator('input, select, button');
    const search = controls.form.getByRole('button', { name: /^Search$/ });
    const clear = controls.form.locator('[data-personae-clear]');

    await controls.toggle.click();
    await controls.query.fill('Aveline');
    await search.click();
    await expect.poll(() => requestCount).toBe(1);
    expect(await queryControls.evaluateAll((items) => items.every((item) => item.disabled))).toBe(true);
    await expect(clear).toHaveAttribute('aria-disabled', 'true');
    await controls.form.evaluate((element) => element.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    expect(requestCount).toBe(1);

    releaseSuccess();
    await expect(controls.results.locator('.manuscriptum-illuminatum-persona-teaser')).toHaveCount(1);
    expect(await queryControls.evaluateAll((items) => items.every((item) => !item.disabled))).toBe(true);
    await expect(clear).toHaveAttribute('aria-disabled', 'false');

    await controls.query.fill('request-that-will-fail');
    await search.click();
    await expect.poll(() => requestCount).toBe(2);
    expect(await queryControls.evaluateAll((items) => items.every((item) => item.disabled))).toBe(true);
    releaseFailure();
    await expect(controls.directory.getByText('The Personae results could not be loaded. Please try again.')).toBeVisible();
    expect(await queryControls.evaluateAll((items) => items.every((item) => !item.disabled))).toBe(true);
    await expect(controls.query).toHaveValue('request-that-will-fail');
    await expect(clear).toHaveAttribute('aria-disabled', 'false');
    await expect(controls.results.locator('.manuscriptum-illuminatum-persona-teaser')).toHaveCount(1);
});

test('flat Personae results reuse responsive, image-aware, alphabetized teasers', async ({ page }, testInfo) => {
    test.setTimeout(60_000);
    let fixture;

    try {
        fixture = await createPersonaeImageFixture(page);
        await page.goto('/characters/');

        const controls = personaeControls(page);
        await controls.toggle.click();
        await controls.advanced.getByLabel('Tradition').selectOption({ label: 'Hermetic' });
        await controls.advanced.getByRole('button', { name: 'Apply Filters' }).click();

        const resultSection = controls.results.locator('.manuscriptum-illuminatum-personae-search-results');
        const grid = resultSection.locator('.manuscriptum-illuminatum-personae-teaser-grid');
        const withImage = grid.locator('.manuscriptum-illuminatum-persona-teaser--with-image');
        const withoutImage = grid.locator('.manuscriptum-illuminatum-persona-teaser--without-image');
        await expect(resultSection).toBeVisible();
        await expect(withImage.first()).toBeVisible();
        await expect(withoutImage.first()).toBeVisible();
        await expect(withoutImage.first().locator('img')).toHaveCount(0);

        const titles = await grid.locator('h3 a').allTextContents();
        expect(titles).toEqual(sortedTitles(titles));

        const layout = await grid.evaluate((element) => {
            const cards = [...element.querySelectorAll('.manuscriptum-illuminatum-persona-teaser')].slice(0, 2);
            const boxes = cards.map((card) => card.getBoundingClientRect());
            return {
                columnCount: getComputedStyle(element).gridTemplateColumns.split(' ').filter(Boolean).length,
                firstX: boxes[0].x,
                firstY: boxes[0].y,
                secondX: boxes[1].x,
                secondY: boxes[1].y,
                viewportOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            };
        });

        expect(layout.viewportOverflow).toBeLessThanOrEqual(0);

        if (testInfo.project.name === 'mobile') {
            expect(layout.columnCount).toBe(1);
            expect(layout.secondY).toBeGreaterThan(layout.firstY);
        } else {
            expect(layout.columnCount).toBe(2);
            expect(Math.abs(layout.secondY - layout.firstY)).toBeLessThanOrEqual(2);
            expect(layout.secondX).toBeGreaterThan(layout.firstX);
        }
    } finally {
        await restorePersonaeImageFixture(page, fixture);
    }
});
