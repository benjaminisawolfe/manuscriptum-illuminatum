const { test, expect } = require('@playwright/test');
const { attachJournalTag, restoreJournalTags } = require('./helpers/journal-tags');
const { getJournalPersonaCollection } = require('./helpers/journal-persona');
const {
    createPersonaeImageFixture,
    restorePersonaeImageFixture,
    wpRequest,
} = require('./support/personae-fixture');

function descending(values) {
    return [...values].sort((a, b) => b.localeCompare(a));
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

async function createJournalFixtures(page, api, marker, count, relatedCharacter = 0) {
    return page.evaluate(async ({ root, nonce, marker: fixtureMarker, count: fixtureCount, relatedCharacter: characterId }) => {
        const requests = Array.from({ length: fixtureCount }, (_, index) => {
            const day = String(index + 1).padStart(2, '0');

            return fetch(new URL('wp/v2/ligatura_diary', root).toString(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
                body: JSON.stringify({
                    title: `${fixtureMarker} Journal ${day}`,
                    content: `Temporary integration fixture for ${fixtureMarker}.`,
                    excerpt: `Temporary integration fixture ${day}.`,
                    status: 'publish',
                    meta: {
                        ligatura_diary_saga_date: `1199-01-${day}`,
                        ligatura_diary_session_date: `2026-01-${day}`,
                        ...(characterId ? { ligatura_diary_character: characterId } : {}),
                    },
                }),
            }).then(async (response) => ({
                ok: response.ok,
                status: response.status,
                body: await response.json(),
            }));
        });

        return Promise.all(requests);
    }, { ...api, marker, count, relatedCharacter });
}

async function deleteJournalFixtures(page, api, ids) {
    if (!ids.length) {
        return;
    }

    await page.evaluate(async ({ root, nonce, ids: fixtureIds }) => {
        await Promise.all(fixtureIds.map((id) => fetch(
            new URL(`wp/v2/ligatura_diary/${id}?force=true`, root).toString(),
            {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': nonce },
            }
        )));
    }, { ...api, ids });
}

test('front page sections use the approved labels and stack full-width rows', async ({ page }) => {
    await page.goto('/');

    await expect(page).not.toHaveURL(/wp-login\.php/);

	await expect(page.getByRole('heading', { name: /^Latest Annales$/i })).toBeVisible();
    await expect(page.getByRole('heading', { name: /^Latest Commentarii$/i })).toBeVisible();
	await expect(page.getByRole('heading', { name: /^Latest Covenant News$/i })).toBeVisible();
	await expect(page.getByRole('heading', { name: /^Latest Personae$/i })).toBeVisible();
	await expect(page.getByRole('heading', { name: /^Latest Specula$/i })).toBeVisible();
	await expect(page.getByRole('heading', { name: /recent speculum updates/i })).toHaveCount(0);
	await expect(page.getByRole('heading', { name: /featured personae/i })).toHaveCount(0);
    await expect(page.getByRole('heading', { name: /^Latest Journals$/i })).toHaveCount(0);

    const rows = page.locator('.manuscriptum-illuminatum-journal-list .manuscriptum-illuminatum-journal-row');
    await expect(rows.first()).toBeVisible();

    const boxes = await rows.evaluateAll((items) => items.map((item) => item.getBoundingClientRect().toJSON()));
    const rowStyle = await rows.first().evaluate((item) => {
        const style = window.getComputedStyle(item);

        return {
            backgroundColor: style.backgroundColor,
            borderRadius: style.borderRadius,
            borderTopStyle: style.borderTopStyle,
            borderTopColor: style.borderTopColor,
            borderTopWidth: style.borderTopWidth,
            paddingBottom: style.paddingBottom,
            paddingLeft: style.paddingLeft,
            paddingRight: style.paddingRight,
            paddingTop: style.paddingTop,
        };
    });
    const cardStyle = await page.locator('.manuscriptum-illuminatum-card-grid .manuscriptum-illuminatum-card').first().evaluate((item) => {
        const style = window.getComputedStyle(item);
        const bodyStyle = window.getComputedStyle(item.querySelector('.manuscriptum-illuminatum-card__body'));

        return {
            backgroundColor: style.backgroundColor,
            borderRadius: style.borderRadius,
            borderTopStyle: style.borderTopStyle,
            borderTopColor: style.borderTopColor,
            borderTopWidth: style.borderTopWidth,
            paddingBottom: bodyStyle.paddingBottom,
            paddingLeft: bodyStyle.paddingLeft,
            paddingRight: bodyStyle.paddingRight,
            paddingTop: bodyStyle.paddingTop,
        };
    });
	const journalGap = await page.locator('.manuscriptum-illuminatum-journal-list').first().evaluate((item) => window.getComputedStyle(item).rowGap);
    const cardGridGap = await page.locator('.manuscriptum-illuminatum-card-grid').first().evaluate((item) => window.getComputedStyle(item).rowGap);

    expect(boxes.length).toBeGreaterThanOrEqual(2);
    expect(rowStyle).toEqual(cardStyle);
    expect(parseFloat(rowStyle.paddingLeft)).toBeGreaterThanOrEqual(12);
    expect(journalGap).toBe(cardGridGap);

    for (let index = 1; index < boxes.length; index += 1) {
        expect(boxes[index].top).toBeGreaterThan(boxes[index - 1].top);
        expect(boxes[index].top - (boxes[index - 1].top + boxes[index - 1].height)).toBeGreaterThanOrEqual(12);
    }
});

test('homepage selects latest Commentarii subset and displays it newest to oldest', async ({ page }) => {
    await page.goto('/');

    const homeDates = await page.locator('.manuscriptum-illuminatum-journal-row').evaluateAll(
        (items) => items.map((item) => item.dataset.sagaDate || '').filter(Boolean)
    );
    const latestAvailableDate = await page.evaluate(async () => {
        const url = new URL('/wp-json/wp/v2/ligatura_diary', window.location.origin);
        url.searchParams.set('per_page', '100');
        url.searchParams.set('_fields', 'meta');
        const response = await fetch(url, { credentials: 'same-origin' });
        const entries = await response.json();

        return entries
            .map((entry) => entry.meta?.ligatura_diary_saga_date || '')
            .filter(Boolean)
            .sort((left, right) => right.localeCompare(left))[0];
    });

    expect(homeDates.length).toBeGreaterThan(0);
    expect(homeDates).toEqual(descending(homeDates));
    expect(homeDates[0]).toBe(latestAvailableDate);
});

test('single Journal presents its full-width body, topics, and details in reading order', async ({ page }, testInfo) => {
    let fixture;
    let imageFixture;
    let journalState;

    try {
        imageFixture = await createPersonaeImageFixture(page, { characterTitle: 'Guarin' });
        const journals = await wpRequest(page, imageFixture.api, 'wp/v2/ligatura_diary?slug=guarin-begins-courteous-audit&context=edit');
        const journal = journals[0];

        expect(journal).toBeTruthy();
        journalState = { id: journal.id, featured_media: journal.featured_media || 0 };
        await wpRequest(page, imageFixture.api, `wp/v2/ligatura_diary/${journal.id}`, {
            method: 'POST',
            body: { featured_media: imageFixture.mediaId },
        });
        fixture = await attachJournalTag(page, 'guarin-begins-courteous-audit', 'Covenant Affairs', 'fixture-journal-covenant-affairs');

        await page.goto('/journals/guarin-begins-courteous-audit/');

        await expect(page).not.toHaveURL(/wp-login\.php/);

        const article = page.locator('article.manuscriptum-illuminatum-entry--diary');
        const header = article.locator('.manuscriptum-illuminatum-journal-header');
        const content = article.locator('.manuscriptum-illuminatum-journal-content');
        const campaignImage = content.locator('.manuscriptum-illuminatum-journal-content__image img');
        const portrait = header.locator('.manuscriptum-illuminatum-journal-header__portrait');
        const date = header.locator('.manuscriptum-illuminatum-journal-header__date');
        const topics = article.locator('.manuscriptum-illuminatum-journal-footer__topics');
        const tags = article.locator('.manuscriptum-illuminatum-journal-footer__tags');

        await expect(article).toHaveAttribute('data-saga-date', '1204-06-12');
        await expect(header.getByRole('heading', { name: /Guarin of Tremere Begins/i })).toBeVisible();
        await expect(header.locator('.manuscriptum-illuminatum-journal-header__author')).toHaveText(/Guarin of Tremere/i);
        await expect(date).toHaveText('June 12, 1204');
        await expect(header.getByText(/Saga Date/i)).toHaveCount(0);
        await expect(page.getByText(/August 22, 2026/i)).toHaveCount(0);
        expect(parseFloat(await date.evaluate((element) => getComputedStyle(element).fontSize))).toBeCloseTo(14, 0);

        await expect(portrait).toBeVisible();
        await expect(portrait).toHaveAttribute('alt', /Portrait of .+/i);
        await expect(portrait).not.toHaveAttribute('src', /gravatar\.com/i);
        await expect(campaignImage).toBeVisible();
        await expect(campaignImage).toHaveAttribute('alt', /.+/);
        await expect(campaignImage).toHaveJSProperty('complete', true);

        const [headerBox, contentBox, portraitBox, imageBox] = await Promise.all([
            header.boundingBox(),
            content.boundingBox(),
            portrait.boundingBox(),
            campaignImage.boundingBox(),
        ]);
        const headerBorder = await header.evaluate((element) => getComputedStyle(element).borderBottomStyle);
        const imageStyle = await campaignImage.locator('..').evaluate((element) => getComputedStyle(element).float);

        expect(headerBorder).not.toBe('none');
        expect(await article.locator('.manuscriptum-illuminatum-meta-list').count()).toBe(0);
        expect(contentBox.y - (headerBox.y + headerBox.height)).toBeGreaterThanOrEqual(32);
        expect(contentBox.y - (headerBox.y + headerBox.height)).toBeLessThanOrEqual(40);
        expect(Math.abs(contentBox.x - headerBox.x)).toBeLessThanOrEqual(2);
        expect(Math.abs(contentBox.width - headerBox.width)).toBeLessThanOrEqual(2);
        expect(Math.abs((headerBox.x + headerBox.width) - (portraitBox.x + portraitBox.width))).toBeLessThanOrEqual(2);
        expect(portraitBox.height).toBeLessThanOrEqual(testInfo.project.name === 'mobile' ? 72 : 96);
        expect(imageBox.width).toBeLessThanOrEqual(contentBox.width);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);

        if (testInfo.project.name === 'desktop') {
            expect(imageStyle).toBe('right');
            expect(imageBox.x + imageBox.width).toBeCloseTo(contentBox.x + contentBox.width, 0);
        } else {
            expect(imageStyle).toBe('none');
        }

        await expect(article.getByText('Related Persona:', { exact: false })).toHaveCount(0);
        await expect(topics).toContainText(/Topics:\s*Covenant Affairs/);
        await expect(topics.getByRole('link', { name: 'Covenant Affairs' })).toHaveAttribute('href', /\/saga-topic\/covenant-affairs\/?$/);
        await expect(tags.getByRole('link', { name: 'Covenant Affairs' })).toBeVisible();

        const readingOrder = await article.locator('.manuscriptum-illuminatum-journal-content, .manuscriptum-illuminatum-journal-footer__line').evaluateAll(
            (items) => items.map((item) => item.className)
        );
        expect(readingOrder[0]).toContain('manuscriptum-illuminatum-journal-content');
        expect(readingOrder[1]).toContain('manuscriptum-illuminatum-journal-footer__line');
        expect(readingOrder[1]).toContain('manuscriptum-illuminatum-journal-footer__topics');
        expect(readingOrder[2]).toContain('manuscriptum-illuminatum-journal-footer__tags');

        await page.goto('/journals/aveline-notes-spring-correspondence/');
        await expect(page.locator('.manuscriptum-illuminatum-journal-footer__tags')).toHaveCount(0);
    } finally {
        if (fixture) {
            await restoreJournalTags(page, fixture);
        }
        if (journalState && imageFixture) {
            await wpRequest(page, imageFixture.api, `wp/v2/ligatura_diary/${journalState.id}`, {
                method: 'POST',
                body: { featured_media: journalState.featured_media },
            });
        }
        await restorePersonaeImageFixture(page, imageFixture);
    }
});

test('single Journal omits the Topics row when no Saga Topic is assigned', async ({ page }) => {
    const api = await getApiSettings(page);
    const marker = `FixtureNoTopic${Date.now()}`;
    const createdIds = [];

    try {
        const [created] = await createJournalFixtures(page, api, marker, 1);

        if (created.body && created.body.id) {
            createdIds.push(created.body.id);
        }

        expect(created.ok, `fixture creation returned ${created.status}`).toBe(true);
        expect(created.body.link).toBeTruthy();

        await page.goto(created.body.link);
        await expect(page.locator('article.manuscriptum-illuminatum-entry--diary')).toBeVisible();
        await expect(page.locator('.manuscriptum-illuminatum-journal-footer__topics')).toHaveCount(0);
    } finally {
        await deleteJournalFixtures(page, api, createdIds);
    }
});

test('Commentarii locks query controls for pending search requests and restores them after success or failure', async ({ page }) => {
    await page.goto('/journals/');

    let releaseSuccess;
    let releaseFailure;
    let requestCount = 0;
    const successGate = new Promise((resolve) => { releaseSuccess = resolve; });
    const failureGate = new Promise((resolve) => { releaseFailure = resolve; });

    await page.route('**/wp-json/ligatura-manuscripti-illuminati/v1/journals*', async (route) => {
        requestCount += 1;

        if (requestCount === 1) {
            await successGate;
            await route.continue();
            return;
        }

        await failureGate;
        await route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'Temporary test failure.' }) });
    });

    const form = page.locator('[data-journal-search-form]');
    const controls = form.locator('input, select, button');
    const query = form.getByLabel('Search Journals');
    const search = form.getByRole('button', { name: /^Search$/ });
    const clear = form.locator('[data-journal-clear]');
    const rows = page.locator('[data-journal-results] .manuscriptum-illuminatum-journal-row');

    await form.getByRole('button', { name: 'Advanced Search' }).click();
    await expect(clear).toBeVisible();
    await query.fill('courteous audit');
    await search.click();
    await expect.poll(() => requestCount).toBe(1);
    expect(await controls.evaluateAll((items) => items.every((item) => item.disabled))).toBe(true);
    await expect(clear).toHaveAttribute('aria-disabled', 'true');
    await form.evaluate((element) => element.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    expect(requestCount).toBe(1);
    await expect(query).toHaveValue('courteous audit');

    releaseSuccess();
    await expect(rows).toHaveCount(1);
    await expect(page).toHaveURL(/journal_q=courteous(\+|%20)audit/);
    expect(await controls.evaluateAll((items) => items.every((item) => !item.disabled))).toBe(true);
    await expect(clear).toHaveAttribute('aria-disabled', 'false');

    await query.fill('request-that-will-fail');
    await search.click();
    await expect.poll(() => requestCount).toBe(2);
    expect(await controls.evaluateAll((items) => items.every((item) => item.disabled))).toBe(true);
    releaseFailure();
    await expect(page.getByText('The Journal results could not be loaded. Please try again.')).toBeVisible();
    expect(await controls.evaluateAll((items) => items.every((item) => !item.disabled))).toBe(true);
    await expect(query).toHaveValue('request-that-will-fail');
    await expect(clear).toHaveAttribute('aria-disabled', 'false');
    await expect(page).toHaveURL(/journal_q=courteous(\+|%20)audit/);
});

test('Journal author portraits use only Character Author Campaign Images', async ({ page }) => {
    await page.goto('/');

    const rows = page.locator('.manuscriptum-illuminatum-journal-row');
    const portraits = page.locator('.manuscriptum-illuminatum-journal-row__portrait');

    await expect(rows.first()).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-journal-row__portrait[src=""]')).toHaveCount(0);

    const portraitCount = await portraits.count();

    for (let index = 0; index < portraitCount; index += 1) {
        const portrait = portraits.nth(index);
        await portrait.scrollIntoViewIfNeeded();
        await expect(portrait).toHaveAttribute('alt', /portrait of|character author portrait/i);
        await expect(portrait).not.toHaveAttribute('src', /gravatar\.com|default-character\.svg/i);
        await expect(portrait).toHaveJSProperty('complete', true);

        const box = await portrait.boundingBox();
        expect(box.width).toBeLessThanOrEqual(72);
        expect(box.height).toBeLessThanOrEqual(72);
    }
});

test('Commentarii directory uses full-width shared rows and starts with at most ten', async ({ page }) => {
    await page.goto('/journals/');

    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expect(page.getByRole('heading', { name: /^Commentarii$/i })).toBeVisible();
    await expect(page.getByText(/editable Commentarii page/i)).toBeVisible();

    const directory = page.locator('.manuscriptum-illuminatum-journal-directory');
    const rows = directory.locator('.manuscriptum-illuminatum-journal-row');
    const pageHeader = page.locator('.manuscriptum-illuminatum-page-header');

    await expect(directory).toBeVisible();
    await expect(rows.first()).toBeVisible();
    expect(await rows.count()).toBeLessThanOrEqual(10);

    const [headerBox, directoryBox, firstRowBox] = await Promise.all([
        pageHeader.boundingBox(),
        directory.boundingBox(),
        rows.first().boundingBox(),
    ]);

    expect(Math.abs(directoryBox.width - headerBox.width)).toBeLessThanOrEqual(2);
    expect(Math.abs(firstRowBox.width - directoryBox.width)).toBeLessThanOrEqual(2);

    const dates = await rows.evaluateAll((items) => items.map((item) => item.dataset.sagaDate || ''));
    const dated = dates.filter(Boolean);
    const firstUndated = dates.indexOf('');

    expect(dated).toEqual(descending(dated));

    if (firstUndated >= 0) {
        expect(dates.slice(firstUndated).every((date) => !date)).toBe(true);
    }
});

test('Commentarii by Persona links are alphabetical and open the reserved Persona collection namespace', async ({ page }) => {
    await page.goto('/journals/');

    const directory = page.locator('.manuscriptum-illuminatum-commentarii-personae');
    const links = directory.locator('a');
    await expect(directory.getByRole('heading', { name: 'Commentarii by Persona' })).toBeVisible();
    await expect(links.first()).toBeVisible();

    const names = await links.allTextContents();
    expect(names).toEqual([...names].sort((left, right) => left.localeCompare(right)));

    for (const link of await links.all()) {
        await expect(link).toHaveAttribute('href', /\/journals\/persona\/[a-z0-9-]+\/?$/);
    }
});

test('Persona-specific Commentarii search and clearing remain Persona-scoped', async ({ page }) => {
    const persona = await getJournalPersonaCollection(page);
    await page.goto(persona.path);

    await expect(page).toHaveURL(new RegExp(`${persona.path.replace(/\/$/, '')}/?$`));
    await expect(page.getByRole('heading', { name: `Commentarii by ${persona.name}` })).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-commentarii-personae')).toHaveCount(0);
    await expect(page.locator('.manuscriptum-illuminatum-breadcrumb__label')).toHaveText(['Home', 'Commentarii', persona.name]);

    const form = page.locator('[data-journal-search-form]');
    const rows = page.locator('[data-journal-results] .manuscriptum-illuminatum-journal-row');
    const initialLinks = await rows.locator('h3 a').evaluateAll((items) => items.map((item) => item.href));

    expect(initialLinks.length).toBeGreaterThan(0);
    for (const row of await rows.all()) {
        await expect(row.locator('.manuscriptum-illuminatum-card__meta')).toContainText(persona.name);
    }

    const noMatch = `No matching Journal ${Date.now()}`;
    await form.getByLabel('Search Journals').fill(noMatch);
    await form.getByRole('button', { name: /^Search$/ }).click();
    await expect(rows).toHaveCount(0);
    await expect(page).toHaveURL(new RegExp(`${persona.path.replace(/\/$/, '')}/?\\?journal_q=No`));

    await form.getByLabel('Search Journals').fill('');
    await expect.poll(() => rows.count()).toBe(initialLinks.length);
    await expect(page).toHaveURL(new RegExp(`${persona.path.replace(/\/$/, '')}/?$`));

    await form.getByRole('button', { name: 'Advanced Search' }).click();
    const topic = form.getByLabel('Topic');
    const topicValue = await topic.locator('option:not([value=""])').first().getAttribute('value');
    await topic.selectOption(topicValue);
    await form.getByRole('button', { name: 'Apply Filters' }).click();
    for (const row of await rows.all()) {
        await expect(row.locator('.manuscriptum-illuminatum-card__meta')).toContainText(persona.name);
    }
});

test('More Commentarii preserves the Persona restriction', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Temporary pagination fixtures are exercised once.');
    test.setTimeout(60_000);

    const api = await getApiSettings(page);
    const persona = await getJournalPersonaCollection(page);
    const personae = await page.evaluate(async ({ root, nonce, slug }) => {
        const response = await fetch(new URL(`wp/v2/ligatura_character?slug=${encodeURIComponent(slug)}&context=edit`, root), {
            credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce },
        });
        return response.json();
    }, { ...api, slug: persona.slug });
    const marker = `FixturePersonaMore${Date.now()}`;
    const createdIds = [];

    try {
        const stale = await page.evaluate(async ({ root, nonce }) => {
            const url = new URL('wp/v2/ligatura_diary?search=FixturePersonaMore&per_page=100&context=edit', root);
            const response = await fetch(url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce } });
            return response.json();
        }, api);
        await deleteJournalFixtures(page, api, stale.map((entry) => entry.id));

        expect(personae[0]?.id).toBeTruthy();
        const created = await createJournalFixtures(page, api, marker, 11, personae[0].id);
        for (const result of created) {
            expect(result.ok, `fixture creation returned ${result.status}`).toBe(true);
            createdIds.push(result.body.id);
        }

        const readBack = await page.evaluate(async ({ root, nonce, id }) => {
            const response = await fetch(new URL(`wp/v2/ligatura_diary/${id}?context=edit`, root), {
                credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce },
            });
            return response.json();
        }, { ...api, id: createdIds[0] });
        expect(Number(readBack.meta?.ligatura_diary_character)).toBe(personae[0].id);

        const directPayload = await page.evaluate(async ({ marker: fixtureMarker, characterSlug, nonce }) => {
            const endpoint = new URL('/wp-json/ligatura-manuscripti-illuminati/v1/journals', window.location.origin);
            endpoint.searchParams.set('journal_q', fixtureMarker);
            endpoint.searchParams.set('journal_persona', characterSlug);
            const response = await fetch(endpoint, {
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': nonce },
            });
            return response.json();
        }, { marker, characterSlug: persona.slug, nonce: api.nonce });
        expect(directPayload.total).toBe(11);

        await page.goto(persona.path);
        const form = page.locator('[data-journal-search-form]');
        const rows = page.locator('[data-journal-results] .manuscriptum-illuminatum-journal-row');
        const more = page.getByRole('button', { name: 'More Commentarii' });
        await form.getByLabel('Search Journals').fill(marker);
        await form.getByRole('button', { name: /^Search$/ }).click();
        await expect(rows).toHaveCount(10);
        await more.click();
        await expect(rows).toHaveCount(11);
        await expect(more).toBeHidden();
        await expect(page).toHaveURL(new RegExp(`${persona.path}\\?journal_q=${marker}`));
    } finally {
        await deleteJournalFixtures(page, api, createdIds);
    }
});

test('Commentarii search is Journal-only, bookmarkable, and reports no results', async ({ page }) => {
    await page.goto('/journals/');

    const form = page.locator('[data-journal-search-form]');
    const query = form.getByLabel('Search Journals');
    const rows = page.locator('[data-journal-results] .manuscriptum-illuminatum-journal-row');

    await query.fill('courteous audit');
    await form.getByRole('button', { name: /^Search$/ }).click();
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText(/Guarin of Tremere Begins/i);
    await expect(page).toHaveURL(/journal_q=courteous(\+|%20)audit/);

    await query.fill('phrase-that-no-journal-contains');
    await form.getByRole('button', { name: /^Search$/ }).click();
    await expect(rows).toHaveCount(0);
    await expect(page.getByText('No Journals match these search criteria.')).toBeVisible();
});

test('clearing simple Journal search dynamically restores the default results', async ({ page }) => {
    await page.goto('/journals/');

    const form = page.locator('[data-journal-search-form]');
    const query = form.getByLabel('Search Journals');
    const rows = page.locator('[data-journal-results] .manuscriptum-illuminatum-journal-row');
    const loadMore = page.getByRole('button', { name: 'More Commentarii' });
    const defaultLinks = await rows.locator('h3 a').evaluateAll((items) => items.map((item) => item.href));
    const defaultDates = await rows.evaluateAll((items) => items.map((item) => item.dataset.sagaDate || ''));
    const defaultHasMore = await loadMore.isVisible();

    await page.evaluate(() => {
        window.__journalResetSentinel = 'same-document';
    });

    await query.fill('courteous audit');
    await form.getByRole('button', { name: /^Search$/ }).click();
    await expect(rows).toHaveCount(1);
    await expect(page).toHaveURL(/journal_q=courteous(\+|%20)audit/);

    await query.fill('');
    await expect.poll(() => new URL(page.url()).searchParams.has('journal_q')).toBe(false);
    await expect.poll(
        () => rows.locator('h3 a').evaluateAll((items) => items.map((item) => item.href))
    ).toEqual(defaultLinks);

    const restoredDates = await rows.evaluateAll((items) => items.map((item) => item.dataset.sagaDate || ''));

    expect(restoredDates).toEqual(defaultDates);
    expect(restoredDates.filter(Boolean)).toEqual(descending(restoredDates.filter(Boolean)));
    expect(await rows.count()).toBeLessThanOrEqual(10);
    expect(await loadMore.isVisible()).toBe(defaultHasMore);
    expect(await page.evaluate(() => window.__journalResetSentinel)).toBe('same-document');
});

test('clearing simple Journal search preserves active advanced filters', async ({ page }) => {
    await page.goto('/journals/');

    const form = page.locator('[data-journal-search-form]');
    const query = form.getByLabel('Search Journals');
    const rows = page.locator('[data-journal-results] .manuscriptum-illuminatum-journal-row');
    const advancedToggle = page.getByRole('button', { name: 'Advanced Search' });

    await advancedToggle.click();

    const topic = form.getByLabel('Topic');
    const topicValue = await topic.locator('option:not([value=""])').first().getAttribute('value');

    expect(topicValue).toBeTruthy();
    await topic.selectOption(topicValue);
    await form.getByRole('button', { name: 'Apply Filters' }).click();
    await expect(page).toHaveURL(new RegExp(`journal_topic=${topicValue}`));

    const advancedLinks = await rows.locator('h3 a').evaluateAll((items) => items.map((item) => item.href));

    await query.fill('courteous audit');
    await form.getByRole('button', { name: /^Search$/ }).click();
    await expect(rows).toHaveCount(1);
    await expect(page).toHaveURL(/journal_q=courteous(\+|%20)audit/);

    await query.fill('');
    await expect.poll(() => new URL(page.url()).searchParams.has('journal_q')).toBe(false);
    await expect.poll(
        () => rows.locator('h3 a').evaluateAll((items) => items.map((item) => item.href))
    ).toEqual(advancedLinks);

    expect(await topic.inputValue()).toBe(topicValue);
    expect(new URL(page.url()).searchParams.get('journal_topic')).toBe(topicValue);
});

test('Advanced Journal search is accessible and filters canonical metadata', async ({ page }) => {
    await page.goto('/journals/');

    const toggle = page.getByRole('button', { name: 'Advanced Search' });
    const advanced = page.locator('#journal-advanced-search');

    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(advanced).toBeHidden();

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(advanced).toBeVisible();
    await expect(advanced.getByLabel('Topic')).toBeVisible();
    await expect(advanced.getByLabel('Author', { exact: true })).toHaveCount(0);
    await expect(advanced.getByLabel('Character Author')).toBeVisible();
    await expect(advanced.getByLabel('From Session Date')).toBeVisible();
    await expect(advanced.getByLabel('To Session Date')).toBeVisible();
    await expect(advanced.getByLabel('From Saga Date')).toBeVisible();
    await expect(advanced.getByLabel('To Saga Date')).toBeVisible();

	const topic = advanced.getByLabel('Topic');
	const character = advanced.getByLabel('Character Author');
	const covenantAffairs = await topic.locator('option', { hasText: /^Covenant Affairs$/ }).getAttribute('value');

	expect(covenantAffairs).toBeTruthy();
	await topic.selectOption(covenantAffairs);
	const guarinCharacter = await character.locator('option', { hasText: /Guarin of Tremere/i }).getAttribute('value');
	expect(guarinCharacter).toBeTruthy();
	await character.selectOption(guarinCharacter);
	await advanced.getByLabel('From Session Date').fill('2026-08-22');
	await advanced.getByLabel('To Session Date').fill('2026-08-22');
    await advanced.getByLabel('From Saga Date').fill('1204-06-12');
    await advanced.getByLabel('To Saga Date').fill('1204-06-12');
    await advanced.getByRole('button', { name: 'Apply Filters' }).click();

    const rows = page.locator('[data-journal-results] .manuscriptum-illuminatum-journal-row');
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toHaveAttribute('data-saga-date', '1204-06-12');
    await expect(page).toHaveURL(/journal_saga_from=1204-06-12/);

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(advanced).toBeHidden();
});

test('More Commentarii appends a filtered second batch without duplicates', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Temporary REST fixtures exercise pagination once.');
    test.setTimeout(60_000);

    const api = await getApiSettings(page);
    const marker = `FixtureLoadMore${Date.now()}`;
    const createdIds = [];

    try {
        const created = await createJournalFixtures(page, api, marker, 11);

        for (const result of created) {
			if (result.body && result.body.id) {
				createdIds.push(result.body.id);
			}
        }

		for (const result of created) {
			expect(result.ok, `fixture creation returned ${result.status}`).toBe(true);
		}

        await page.goto('/journals/');
        const form = page.locator('[data-journal-search-form]');
        const rows = page.locator('[data-journal-results] .manuscriptum-illuminatum-journal-row');
        const loadMore = page.getByRole('button', { name: 'More Commentarii' });

        await form.getByLabel('Search Journals').fill(marker);
        await form.getByRole('button', { name: /^Search$/ }).click();
		await expect(page).toHaveURL(new RegExp(`journal_q=${marker}`));
        await expect(rows).toHaveCount(10);
        await expect(loadMore).toBeVisible();

        const firstBatchLinks = await rows.locator('h3 a').evaluateAll((items) => items.map((item) => item.href));
        await loadMore.click();
        await expect(rows).toHaveCount(11);
        await expect(loadMore).toBeHidden();

        const allLinks = await rows.locator('h3 a').evaluateAll((items) => items.map((item) => item.href));
        const dates = await rows.evaluateAll((items) => items.map((item) => item.dataset.sagaDate || ''));

        expect(new Set(allLinks).size).toBe(allLinks.length);
        expect(allLinks.slice(0, 10)).toEqual(firstBatchLinks);
        expect(dates).toEqual(descending(dates));
    } finally {
        await deleteJournalFixtures(page, api, createdIds);
    }
});
