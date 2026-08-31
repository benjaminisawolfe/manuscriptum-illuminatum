const { test, expect } = require('@playwright/test');

test('private-site gate protects front-end, feeds, and REST data from guests', async ({ browser, baseURL }) => {
    const context = await browser.newContext({
        baseURL,
        storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();

    try {
        await page.goto('/', { waitUntil: 'domcontentloaded' });
        await expect(page).toHaveURL(/\/wp-login\.php/);

        const feed = await context.request.get('/feed/');
        expect(feed.status()).toBe(403);

        for (const route of [
            '/wp-json/wp/v2/posts?per_page=1',
            '/wp-json/wp/v2/media?per_page=1',
            '/wp-json/wp/v2/ligatura_wiki?per_page=1',
            '/wp-json/wp/v2/ligatura_character?per_page=1',
            '/wp-json/wp/v2/ligatura_diary?per_page=1',
            '/wp-json/wp/v2/ligatura_covenant?per_page=1',
        ]) {
            const response = await context.request.get(route);
            expect(response.status(), `${route} must require authentication`).toBe(401);
            const payload = await response.json();
            expect(payload.code).toBe('ligatura_rest_authentication_required');
        }
    } finally {
        await context.close();
    }
});
