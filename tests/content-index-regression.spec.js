const { test, expect } = require('@playwright/test');
const { getApiSettings, wpRequest } = require('./support/personae-fixture');

const namespace = 'ligatura-manuscripti-illuminati/v1/';
const sections = [
    { name: 'Speculum', type: 'ligatura_wiki', path: '/wiki/', endpoint: 'speculum', prefix: 'speculum', search: 'speculum_q', filter: 'speculum_entry_type', taxonomy: 'ligatura_entry_type', latest: 'Latest Specula', count: 4 },
    { name: 'Personae', type: 'ligatura_character', path: '/characters/', endpoint: 'personae', prefix: 'personae', search: 'personae_q', filter: 'personae_type', taxonomy: 'ligatura_character_type', latest: 'Latest Personae', count: 4 },
    { name: 'Commentarii', type: 'ligatura_diary', path: '/journals/', endpoint: 'journals', prefix: 'journal', search: 'journal_q', filter: 'journal_topic', taxonomy: 'ligatura_saga_topic', latest: 'Latest Commentarii', count: 12, dateKey: 'ligatura_diary_saga_date' },
    { name: 'Covenant Records', type: 'ligatura_covenant', path: '/covenant-records/', endpoint: 'covenant-records', prefix: 'covenant', search: 'covenant_q', filter: 'covenant_entry_type', taxonomy: 'ligatura_entry_type', latest: 'Latest Covenant News', count: 12, dateKey: 'ligatura_covenant_saga_date' },
];

async function withFixtures(page, section, run) {
    const api = await getApiSettings(page);
    const marker = `IndexFixture${Date.now()}`;
    const posts = [];
    let term;
    try {
        term = await wpRequest(page, api, `wp/v2/${section.restTaxonomy || section.taxonomy}`, {
            method: 'POST', body: { name: marker, slug: marker.toLowerCase() },
        });
        for (let index = 0; index < section.count + 2; index += 1) {
            const status = index < section.count ? 'publish' : index === section.count ? 'draft' : 'private';
            const title = `${marker} ${String(index + 1).padStart(2, '0')} ${status}`;
            const post = await wpRequest(page, api, `wp/v2/${section.type}`, {
                method: 'POST',
                body: {
                    title, status, content: `<p>${title}</p>`, excerpt: index === 0 ? '' : title,
                    [section.restTaxonomy || section.taxonomy]: [term.id],
                    ...(section.dateKey ? { meta: { [section.dateKey]: `9998-01-${String(index + 1).padStart(2, '0')}` } } : {}),
                },
            });
            posts.push({ ...post, fixtureTitle: title });
            expect(post.status).toBe(status);
        }
        await run({ api, marker, posts, term });
    } finally {
        // Only IDs returned by this test's successful creates are deleted.
        const failures = [];
        for (const post of posts.reverse()) {
            try { await wpRequest(page, api, `wp/v2/${section.type}/${post.id}?force=true`, { method: 'DELETE' }); }
            catch (error) { failures.push(error.message); }
        }
        if (term) {
            try { await wpRequest(page, api, `wp/v2/${section.restTaxonomy || section.taxonomy}/${term.id}?force=true`, { method: 'DELETE' }); }
            catch (error) { failures.push(error.message); }
        }
        expect(failures, 'Fixture cleanup must succeed').toEqual([]);
    }
}

function fixtureLinks(page, marker) {
    return page.locator('main article').filter({ hasText: marker }).locator('h2 a, h3 a');
}

for (const section of sections) {
    test(`${section.name}: published fixtures survive empty filters, search, reset, and latest queries`, async ({ page }) => {
        test.setTimeout(180_000);
        await withFixtures(page, section, async ({ api, marker, posts, term }) => {
            const published = posts.filter(post => post.status === 'publish');
            const draft = posts.find(post => post.status === 'draft');
            const privatePost = posts.find(post => post.status === 'private');
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));

            await page.goto(section.path);
            await expect(page).not.toHaveURL(/wp-login\.php/);
            const directory = page.locator(`[data-${section.prefix}-results]`).locator('..');
            const form = page.locator(`[data-${section.prefix}-search-form]`);
            const results = page.locator(`[data-${section.prefix}-results]`);
            await expect(form).toBeVisible();
            const fields = await form.locator('[name]').evaluateAll(inputs => inputs.map(input => input.name));
            const empty = new URLSearchParams(fields.map(name => [name, ''])).toString();
            const baseline = await wpRequest(page, api, namespace + section.endpoint);
            const cleared = await wpRequest(page, api, namespace + section.endpoint + '?' + empty);
            expect(cleared.total).toBe(baseline.total);
            expect(cleared.html).toBe(baseline.html);
            expect(cleared.html).not.toContain(draft.fixtureTitle);
            const whitespace = await wpRequest(page, api, namespace + section.endpoint + '?' + new URLSearchParams(fields.map(name => [name, '   '])));
            expect(whitespace.total).toBe(baseline.total);
            expect(whitespace.html).toBe(baseline.html);

            await page.goto(section.path + '?' + empty);
            await expect(fixtureLinks(page, marker).first()).toBeVisible();
            const target = published[0];
            await form.locator(`[name="${section.search}"]`).fill(target.fixtureTitle);
            await form.getByRole('button', { name: 'Search', exact: true }).click();
            await expect(results.getByRole('link', { name: target.fixtureTitle, exact: true })).toBeVisible();
            await expect(fixtureLinks(page, marker)).toHaveCount(1);
            const filtered = await wpRequest(page, api, namespace + section.endpoint + '?' + new URLSearchParams({ [section.search]: target.fixtureTitle }));
            expect(filtered.total).toBe(1);
            expect(filtered.html).toContain(target.link);

            await form.getByRole('button', { name: 'Advanced Search' }).click();
            await form.locator(`[data-${section.prefix}-clear]`).click();
            await expect(fixtureLinks(page, marker).first()).toBeVisible();
            await expect(form.locator(`[name="${section.search}"]`)).toHaveValue('');
            // A known type/topic must restrict the real directory query, including its readable private result.
            const typed = await wpRequest(page, api, namespace + section.endpoint + '?' + new URLSearchParams({ [section.filter]: term.slug }));
            expect(typed.total).toBe(section.count + 1);
            expect(typed.html).not.toContain(draft.fixtureTitle);
            await form.locator(`[name="${section.filter}"]`).selectOption(term.slug);
            await form.getByRole('button', { name: 'Apply Filters' }).click();
            await expect(results).toHaveAttribute('aria-busy', 'false');
            await expect(page).toHaveURL(new RegExp(`${section.filter}=${term.slug}`));
            if (section.count > 10) {
                await expect(fixtureLinks(page, marker)).toHaveCount(10);
                await page.locator(`[data-${section.prefix}-load-more]`).click();
                await expect(fixtureLinks(page, marker)).toHaveCount(section.count + 1);
                const links = await fixtureLinks(page, marker).evaluateAll(nodes => nodes.map(node => node.href));
                expect(new Set(links).size).toBe(links.length);
                const pageTwo = await wpRequest(page, api, namespace + section.endpoint + '?' + new URLSearchParams({ [section.filter]: term.slug, page: '2' }));
                expect(pageTwo.count).toBe(section.count + 1 - 10);
                expect(pageTwo.hasMore).toBe(false);
                const pageZero = await wpRequest(page, api, namespace + section.endpoint + '?page=0');
                expect(pageZero.page).toBe(1);
            }
            await expect(results.getByRole('link', { name: new RegExp(privatePost.fixtureTitle) })).toBeVisible();
            await form.locator(`[data-${section.prefix}-clear]`).click();
            await expect(form.locator(`[name="${section.filter}"]`)).toHaveValue('');
            await expect(fixtureLinks(page, marker).first()).toBeVisible();
            await expect(directory.locator(`[data-${section.prefix}-empty]`)).toBeHidden();

            await page.goto('/');
            const latest = page.locator('main section').filter({ has: page.getByRole('heading', { level: 2, name: section.latest, exact: true }) });
            const visibleTitles = await latest.locator('article h2 a, article h3 a').allTextContents();
            const expectedCount = section.count === 4 ? 4 : visibleTitles.length;
            expect(expectedCount).toBeGreaterThan(0);
            const expected = [...published].reverse().slice(0, expectedCount).map(post => post.fixtureTitle);
            expect(visibleTitles).toEqual(expected);
            await expect(latest).not.toContainText(draft.fixtureTitle);
            await expect(latest).not.toContainText(privatePost.fixtureTitle);
            await expect(page.locator('main')).not.toContainText(/Fatal error:|Warning:|Parse error:/);

            await page.goto(target.link);
            await expect(page).toHaveURL(target.link);
            await expect(page.locator('main h1')).toHaveText(target.fixtureTitle);
            if (section.type === 'ligatura_wiki') {
                await page.goto(term.link);
                await expect(page.locator('main').getByRole('link', { name: new RegExp(marker) })).toHaveCount(section.count + 1);
            }
            expect(errors).toEqual([]);
        });
    });
}

test('Annales fixtures retain empty-search results, native pagination, single URLs, and latest ordering', async ({ page }) => {
    test.setTimeout(180_000);
    const api = await getApiSettings(page);
    const settings = await wpRequest(page, api, 'wp/v2/settings');
    const perPage = settings.posts_per_page;
    expect(perPage).toBeGreaterThan(0);
    expect(perPage, 'Keep the temporary fixture batch bounded').toBeLessThanOrEqual(50);
    const section = { type: 'posts', taxonomy: 'categories', count: perPage + 2 };
    await withFixtures(page, section, async ({ marker, posts }) => {
        const published = posts.filter(post => post.status === 'publish');
        const newest = [...published].reverse();
        await page.goto('/news/');
        const first = await fixtureLinks(page, marker).allTextContents();
        expect(first).toEqual(newest.slice(0, perPage).map(post => post.fixtureTitle));
        const nextPage = await page.locator('.pagination a.page-numbers').filter({ hasText: /^2$/ }).getAttribute('href');
        expect(nextPage).toBeTruthy();
        await page.goto('/news/?s=');
        expect(await fixtureLinks(page, marker).allTextContents()).toEqual(first);
        await page.goto(nextPage);
        await expect(page.locator('main h1')).toHaveText('Annales');
        const second = await fixtureLinks(page, marker).allTextContents();
        expect(second).toEqual(newest.slice(perPage).map(post => post.fixtureTitle));
        expect(new Set([...first, ...second]).size).toBe(published.length);
        await page.goto('/?post_type=post&s=' + encodeURIComponent(published[0].fixtureTitle));
        await expect(fixtureLinks(page, marker)).toHaveCount(1);
        await page.goto('/news/');
        expect(await fixtureLinks(page, marker).allTextContents()).toEqual(first);
        await page.goto('/');
        const latest = page.locator('.manuscriptum-illuminatum-front-annales');
        const titles = await latest.locator('article h3 a').allTextContents();
        expect(titles.length).toBeGreaterThan(0);
        expect(titles).toEqual(newest.slice(0, titles.length).map(post => post.fixtureTitle));
        for (const post of posts.filter(post => post.status !== 'publish')) {
            await expect(latest).not.toContainText(post.fixtureTitle);
        }
        await page.goto(published[0].link);
        await expect(page).toHaveURL(published[0].link);
        await expect(page.locator('main h1')).toHaveText(published[0].fixtureTitle);
    });
});

test('Speculum without an Entry Type remains discoverable without searching', async ({ page }) => {
    test.setTimeout(60_000);
    const api = await getApiSettings(page);
    let post;
    try {
        const title = `UnclassifiedSpeculum${Date.now()}`;
        post = await wpRequest(page, api, 'wp/v2/ligatura_wiki', {
            method: 'POST', body: { title, content: `<p>${title}</p>`, status: 'publish', ligatura_entry_type: [] },
        });
        expect(post.status).toBe('publish');
        expect(post.ligatura_entry_type).toEqual([]);
        const search = await wpRequest(page, api, namespace + 'speculum?speculum_q=' + title);
        expect(search.total).toBe(1);
        await page.goto('/');
        await expect(page.locator('.manuscriptum-illuminatum-front-wiki').getByRole('link', { name: title, exact: true })).toBeVisible();
        await page.goto('/wiki/?speculum_q=&speculum_entry_type=');
        await expect(page.locator('[data-speculum-results]').getByRole('link', { name: title, exact: true })).toBeVisible();
        const unfiltered = await wpRequest(page, api, namespace + 'speculum');
        expect(unfiltered.html).toContain(post.link);
    } finally {
        if (post) await wpRequest(page, api, `wp/v2/ligatura_wiki/${post.id}?force=true`, { method: 'DELETE' });
    }
});
