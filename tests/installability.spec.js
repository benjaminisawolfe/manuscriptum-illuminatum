const { test, expect } = require('@playwright/test');
const { getApiSettings, wpRequest } = require('./support/personae-fixture');

const expectedPages = {
    home: 'home',
    annales: 'news',
    speculum: 'wiki',
    personae: 'characters',
    commentarii: 'journals',
    'covenant-records': 'covenant-records',
};

test('WordPress recognizes only the renamed active theme and plugin', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The installed component inventory is checked once.');

    await page.goto('/wp-admin/plugins.php');
    await expect(page).not.toHaveURL(/wp-login\.php/);

    const plugin = page.locator('tr[data-plugin="ligatura-manuscripti-illuminati/ligatura-manuscripti-illuminati.php"]');
    await expect(plugin).toHaveCount(1);
    await expect(plugin).toHaveClass(/\bactive\b/);
    await expect(plugin).toContainText('Ligatura Manuscripti Illuminati');

    await page.goto('/wp-admin/themes.php');
    const theme = page.locator('.theme[data-slug="paginae-manuscripti-illuminati"]');
    await expect(theme).toHaveCount(1);
    await expect(theme).toHaveClass(/\bactive\b/);
    await expect(theme).toContainText('Paginae Manuscripti Illuminati');
});

test('versioned site setup is complete and idempotent', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The setup retry and database assertions are exercised once.');

    const api = await getApiSettings(page);
    const before = await wpRequest(page, api, 'paginae-manuscripti-illuminati/v1/site-setup/status');

    expect(before.ready).toBe(true);
    expect(before.errors).toEqual([]);
    expect(before.primaryMenuId).toBeGreaterThan(0);
    expect(before.showOnFront).toBe('page');
    expect(before.pageOnFront).toBe(before.pages.home.id);
    expect(before.pageForPosts).toBe(before.pages.annales.id);

    for (const [key, slug] of Object.entries(expectedPages)) {
        expect(before.pages[key]).toMatchObject({ slug, status: 'publish' });

        const matches = await wpRequest(page, api, `wp/v2/pages?slug=${encodeURIComponent(slug)}&context=edit&per_page=100`);
        expect(matches).toHaveLength(1);
        expect(matches[0].id).toBe(before.pages[key].id);
    }

    const after = await wpRequest(page, api, 'paginae-manuscripti-illuminati/v1/site-setup/run', { method: 'POST' });

    expect(after.ready).toBe(true);
    expect(after.errors).toEqual([]);
    expect(after.primaryMenuId).toBe(before.primaryMenuId);
    expect(Object.fromEntries(Object.entries(after.pages).map(([key, value]) => [key, value.id])))
        .toEqual(Object.fromEntries(Object.entries(before.pages).map(([key, value]) => [key, value.id])));
});

test('missing default Page recovery restores the same Page and preserves its content', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The reversible missing-Page repair is exercised once.');
    test.setTimeout(90_000);

    const api = await getApiSettings(page);
    const setup = await wpRequest(page, api, 'paginae-manuscripti-illuminati/v1/site-setup/status');
    const pageId = setup.pages['covenant-records'].id;
    const original = await wpRequest(page, api, `wp/v2/pages/${pageId}?context=edit`);

    try {
        const trashed = await wpRequest(page, api, `wp/v2/pages/${pageId}`, { method: 'DELETE' });
        expect(trashed.status).toBe('trash');

        const repaired = await wpRequest(page, api, 'paginae-manuscripti-illuminati/v1/site-setup/run', { method: 'POST' });
        expect(repaired.ready).toBe(true);
        expect(repaired.pages['covenant-records']).toMatchObject({ id: pageId, slug: 'covenant-records', status: 'publish' });

        const restored = await wpRequest(page, api, `wp/v2/pages/${pageId}?context=edit`);
        expect(restored.title.raw).toBe(original.title.raw);
        expect(restored.content.raw).toBe(original.content.raw);
    } finally {
        const current = await wpRequest(page, api, `wp/v2/pages/${pageId}?context=edit`);
        if (current.status !== 'publish' || current.slug !== original.slug || current.content.raw !== original.content.raw) {
            await wpRequest(page, api, `wp/v2/pages/${pageId}`, {
                method: 'POST',
                body: {
                    status: 'publish',
                    slug: original.slug,
                    title: original.title.raw,
                    content: original.content.raw,
                },
            });
        }
    }
});

test('header and footer use configurable WordPress structures', async ({ page }) => {
    await page.goto('/');

    const menu = page.locator('.site-nav__menu');
    await expect(menu).toBeVisible();
    await expect(menu.locator('.menu-item')).toHaveCount(6);
    expect(await menu.getByRole('link').allTextContents()).toEqual([
        'Home',
        'Annales',
        'Speculum',
        'Personae',
        'Commentarii',
        'Covenant Records',
    ]);

    await expect(page.locator('.site-footer p')).toContainText(/©\s+\d{4}\s+Benjamin Wolfe/);
});

test('footer copyright text can be changed, emptied, and restored', async ({ page, baseURL }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The reversible Customizer mutation is exercised once.');
    test.setTimeout(90_000);

    const customizeUrl = `/wp-admin/customize.php?autofocus[section]=paginae_manuscripti_illuminati_footer&url=${encodeURIComponent(new URL('/', baseURL).toString())}`;
    const selector = '#_customize-input-paginae_manuscripti_illuminati_footer_copyright_text';
    let original = '';
    let changed = false;

    async function openCustomizer() {
        await page.goto(customizeUrl);
        await expect(page).not.toHaveURL(/wp-login\.php/);
        await expect(page.locator(selector)).toBeVisible();
    }

    async function publish(value) {
        const input = page.locator(selector);
        await input.fill(value);
        await input.dispatchEvent('change');
        await expect(page.locator('#save')).toBeEnabled();
        const saved = page.waitForResponse((response) => (
            response.url().includes('admin-ajax.php')
            && (response.request().postData() || '').includes('action=customize_save')
        ));
        await page.locator('#save').click();
        expect((await saved).ok()).toBe(true);
        await expect(page.locator('#save')).toBeDisabled();
    }

    try {
        await openCustomizer();
        original = await page.locator(selector).inputValue();
        await publish('© {year} Manuscriptum footer test');
        changed = true;
        await page.goto('/');
        await expect(page.locator('.site-footer p')).toHaveText(/©\s+\d{4}\s+Manuscriptum footer test/);

        await openCustomizer();
        await publish('');
        await page.goto('/');
        await expect(page.locator('.site-footer p')).toHaveCount(0);
    } finally {
        if (changed) {
            await openCustomizer();
            await publish(original);
            await page.goto('/');
            await expect(page.locator('.site-footer p')).toContainText(/Benjamin Wolfe/);
        }
    }
});
