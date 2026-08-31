const { test, expect } = require('@playwright/test');
const { getApiSettings, wpRequest } = require('./support/personae-fixture');

function descending(values) {
    return [...values].sort((left, right) => right.localeCompare(left));
}

async function restIds(page, endpoint, count) {
    return page.evaluate(async ({ endpoint: path, count: itemCount }) => {
        const url = new URL(`/wp-json/wp/v2/${path}`, window.location.origin);
		url.searchParams.set('per_page', '100');
        url.searchParams.set('orderby', path === 'posts' ? 'date' : 'modified');
        url.searchParams.set('order', 'desc');
		url.searchParams.set('_fields', 'id,date,modified');
        const response = await fetch(url, { credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error(`${path} returned ${response.status}`);
        }

		return (await response.json())
			.sort((left, right) => {
				const field = path === 'posts' ? 'date' : 'modified';
				return right[field].localeCompare(left[field]) || right.id - left.id;
			})
			.slice(0, itemCount)
			.map((item) => item.id);
    }, { endpoint, count });
}

test('Annales is a WordPress-managed Posts Page in primary navigation', async ({ page }) => {
    await page.goto('/news/');

    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expect(page).toHaveURL(/\/news\/?$/);
    await expect(page.getByRole('heading', { level: 1, name: /^Annales$/ })).toBeVisible();
    await expect(page.locator('body')).toHaveClass(/blog/);

    const navLink = page.locator('.site-nav').getByRole('link', { name: /^Annales$/ });
    await expect(navLink).toHaveCount(1);
    await expect(navLink).toHaveAttribute('href', /\/news\/?$/);

    const rows = page.locator('.manuscriptum-illuminatum-annales-directory .manuscriptum-illuminatum-annal-row');
    expect(await rows.count()).toBeGreaterThan(0);
    await expect(rows.first().locator('h2 a')).toBeVisible();
    await expect(rows.first().locator('.manuscriptum-illuminatum-card__meta')).toContainText(/\w/);

    const dates = await rows.evaluateAll((items) => items.map((item) => item.dataset.postDate));
    expect(dates).toEqual(descending(dates));
});

test('Annales uses canonical /news/ URLs with standard endpoints', async ({ page }) => {
    await page.goto('/news/');

    const firstLink = page.locator('.manuscriptum-illuminatum-annales-directory .manuscriptum-illuminatum-annal-row h2 a').first();
    await expect(firstLink).toHaveAttribute('href', /\/news\/[a-z0-9-]+\/?$/);
    const canonical = await firstLink.getAttribute('href');
    const slug = new URL(canonical).pathname.split('/').filter(Boolean).at(-1);

    await page.goto(canonical);
    await expect(page).toHaveURL(new RegExp(`/news/${slug}/?$`));
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', new RegExp(`/news/${slug}/?$`));

    const feed = await page.request.get(`/news/${slug}/feed/`);
    expect(feed.status()).toBe(200);
    expect(feed.headers()['content-type']).toMatch(/xml|rss/);

    const embed = await page.request.get(`/news/${slug}/embed/`);
    expect(embed.status()).toBe(200);
    expect(embed.url()).toMatch(new RegExp(`/news/${slug}/embed/?$`));
});

test('Annales migration blocks an unrelated /news/ Page without changing either Page', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The reversible migration collision is exercised once.');
    test.setTimeout(90_000);

    const api = await getApiSettings(page);
    const pages = await wpRequest(page, api, 'wp/v2/pages?slug=news&context=edit');
    const annales = pages[0];
    const marker = `Unrelated News Page ${Date.now()}`;
    let unrelatedId = 0;

    expect(annales?.id).toBeTruthy();

    try {
        const renamed = await wpRequest(page, api, `wp/v2/pages/${annales.id}`, {
            method: 'POST',
            body: { slug: 'annales' },
        });
        expect(renamed.slug).toBe('annales');

        const unrelated = await wpRequest(page, api, 'wp/v2/pages', {
            method: 'POST',
            body: {
                title: marker,
                slug: 'news',
                content: `<p>${marker} content must remain unchanged.</p>`,
                status: 'publish',
            },
        });
        unrelatedId = unrelated.id;
        expect(unrelated.slug).toBe('news');

        const migration = await page.evaluate(async ({ root, nonce }) => {
            const response = await fetch(new URL('paginae-manuscripti-illuminati/v1/annales/migrate', root), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': nonce },
            });
            return { status: response.status, body: await response.json() };
        }, api);
        expect(migration.status).toBe(409);
        expect(migration.body.code).toBe('paginae_manuscripti_illuminati_annales_slug_collision');
        expect(migration.body.message).toContain('slug "news" is already in use');

        const [unchangedAnnales, unchangedUnrelated, settings] = await Promise.all([
            wpRequest(page, api, `wp/v2/pages/${annales.id}?context=edit`),
            wpRequest(page, api, `wp/v2/pages/${unrelatedId}?context=edit`),
            wpRequest(page, api, 'wp/v2/settings'),
        ]);
        expect(unchangedAnnales.slug).toBe('annales');
        expect(unchangedUnrelated.slug).toBe('news');
        expect(unchangedUnrelated.title.raw).toBe(marker);
        expect(unchangedUnrelated.content.raw).toContain(`${marker} content must remain unchanged.`);
        expect(settings.page_for_posts).toBe(annales.id);

        const noncanonical = await page.request.get('/annales/', { maxRedirects: 0 });
        expect(noncanonical.status()).not.toBe(301);
        expect(noncanonical.headers().location || '').not.toMatch(/\/news\/?$/);
    } finally {
        if (unrelatedId) {
            await wpRequest(page, api, `wp/v2/pages/${unrelatedId}?force=true`, { method: 'DELETE' });
        }

        const recovery = await page.evaluate(async ({ root, nonce }) => {
            const response = await fetch(new URL('paginae-manuscripti-illuminati/v1/annales/migrate', root), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': nonce },
            });
            return { status: response.status, body: await response.json() };
        }, api);
        expect(recovery.status).toBe(200);
        expect(recovery.body.migrated).toBe(true);

        const [restored, restoredSettings] = await Promise.all([
            wpRequest(page, api, `wp/v2/pages/${annales.id}?context=edit`),
            wpRequest(page, api, 'wp/v2/settings'),
        ]);
        expect(restored.slug).toBe('news');
        expect(restoredSettings.page_for_posts).toBe(annales.id);
    }
});

test('Annales preserves editor-authored Page content above its generated index', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The temporary WordPress Page mutation is needed only once.');
    test.setTimeout(60_000);

    const api = await getApiSettings(page);
    const pages = await wpRequest(page, api, 'wp/v2/pages?slug=news&context=edit');
    const annales = pages[0];
    const originalContent = annales?.content?.raw ?? '';
    const marker = `Editable Annales introduction ${Date.now()}`;

    expect(annales).toBeTruthy();

    try {
        await wpRequest(page, api, `wp/v2/pages/${annales.id}`, {
            method: 'POST',
            body: { content: `<p>${marker}</p>` },
        });
        await page.goto('/news/');

        const introduction = page.locator('.manuscriptum-illuminatum-annales-introduction');
        const directory = page.locator('.manuscriptum-illuminatum-annales-directory');
        await expect(introduction).toContainText(marker);
        expect(await introduction.evaluate((intro, index) => Boolean(intro.compareDocumentPosition(index) & Node.DOCUMENT_POSITION_FOLLOWING), await directory.elementHandle())).toBe(true);
    } finally {
        await wpRequest(page, api, `wp/v2/pages/${annales.id}`, {
            method: 'POST',
            body: { content: originalContent },
        });
    }
});

test('Home preserves editor content and uses the exact requested section sequence', async ({ page }) => {
    await page.goto('/');

    await expect(page).not.toHaveURL(/wp-login\.php/);
    const content = page.locator('.manuscriptum-illuminatum-front-content');
    const generatedSections = page.locator('.manuscriptum-illuminatum-page--front .manuscriptum-illuminatum-band');
    await expect(content).toBeVisible();
    await expect(generatedSections).toHaveCount(5);

    const headings = await generatedSections.locator(':scope > .manuscriptum-illuminatum-section-header > h2').allTextContents();
    expect(headings).toEqual([
        'Latest Annales',
        'Latest Commentarii',
        'Latest Covenant News',
        'Latest Personae',
        'Latest Specula',
    ]);

    const contentBeforeSections = await content.evaluate((editorContent, firstSection) => (
        Boolean(editorContent.compareDocumentPosition(firstSection) & Node.DOCUMENT_POSITION_FOLLOWING)
    ), await generatedSections.first().elementHandle());
    expect(contentBeforeSections).toBe(true);
});

test('Home sections retain distinct chronology and required destination links', async ({ page }) => {
    await page.goto('/');

    const annalesRows = page.locator('.manuscriptum-illuminatum-front-annales .manuscriptum-illuminatum-annal-row');
    const journalRows = page.locator('.manuscriptum-illuminatum-journal-row--commentarium');
    const covenantRows = page.locator('.manuscriptum-illuminatum-covenant-news-row');
    const personaeCards = page.locator('.manuscriptum-illuminatum-front-personae .manuscriptum-illuminatum-card--character');
    const speculaCards = page.locator('.manuscriptum-illuminatum-front-wiki .manuscriptum-illuminatum-card--wiki-update');

    const publishedDates = await annalesRows.evaluateAll((items) => items.map((item) => item.dataset.postDate));
    const sagaDates = await journalRows.evaluateAll((items) => items.map((item) => item.dataset.sagaDate));
    const covenantDates = await covenantRows.evaluateAll((items) => items.map((item) => item.dataset.modified));
    const personaeDates = await personaeCards.evaluateAll((items) => items.map((item) => item.dataset.modified));
    const speculaDates = await speculaCards.evaluateAll((items) => items.map((item) => item.dataset.modified));

    expect(publishedDates).toEqual(descending(publishedDates));
    expect(sagaDates).toEqual(descending(sagaDates));
    expect(covenantDates).toEqual(descending(covenantDates));
    expect(personaeDates).toEqual(descending(personaeDates));
    expect(speculaDates).toEqual(descending(speculaDates));
    await expect(page.getByRole('link', { name: 'All Annales' })).toHaveAttribute('href', /\/news\/?$/);
    await expect(page.getByRole('link', { name: 'All Covenant News' })).toHaveAttribute('href', /\/covenant-records\/?$/);

    await expect(personaeCards).toHaveCount(4);
    await expect(speculaCards).toHaveCount(4);
    expect(await restIds(page, 'ligatura_character', 4)).toEqual(await personaeCards.evaluateAll((items) => items.map((item) => Number(item.dataset.postId))));
    expect(await restIds(page, 'ligatura_wiki', 4)).toEqual(await speculaCards.evaluateAll((items) => items.map((item) => Number(item.dataset.postId))));
    expect(await restIds(page, 'ligatura_covenant', covenantDates.length)).toEqual(await covenantRows.evaluateAll((items) => items.map((item) => Number(item.dataset.postId))));
    expect(await restIds(page, 'posts', publishedDates.length)).toEqual(await annalesRows.evaluateAll((items) => items.map((item) => Number(item.dataset.postId))));
});

test('Home Personae use campaign images only and leave no empty media block', async ({ page }) => {
    await page.goto('/');

    const cards = page.locator('.manuscriptum-illuminatum-front-personae .manuscriptum-illuminatum-card--character');
    await expect(cards).toHaveCount(4);
    await expect(cards.locator('img[src*="default-character"]')).toHaveCount(0);
    await expect(cards.locator('.manuscriptum-illuminatum-card--without-image .manuscriptum-illuminatum-card__image')).toHaveCount(0);

    for (const image of await cards.locator('.manuscriptum-illuminatum-card--with-image img').all()) {
        expect((await image.getAttribute('alt'))?.trim()).not.toBe('');
    }
});
