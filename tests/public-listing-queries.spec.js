const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');
const { getApiSettings, wpRequest } = require('./support/personae-fixture');

// The temporary plugin runs real WP_Query/renderers inside an administrator-only
// request. Public-mode simulation never changes staging's login requirement.
test('public and private listing queries respect visitor visibility', async ({ page, browser, baseURL }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Server-side query/visibility matrix only needs one run.');
    test.setTimeout(180_000);
    const api = await getApiSettings(page);
    const pluginId = 'mi-listing-regression-probe/probe';
    const plugins = await wpRequest(page, api, 'wp/v2/plugins');
    expect(plugins.some(plugin => plugin.plugin === pluginId), 'Do not overwrite an existing probe installation').toBe(false);
    const source = path.resolve(__dirname, 'support/mi-listing-regression-probe');
    const archive = testInfo.outputPath('mi-listing-regression-probe.zip');
    const quote = value => "'" + value.replaceAll("'", "''") + "'";
    execFileSync('powershell.exe', ['-NoProfile', '-Command', `Compress-Archive -LiteralPath ${quote(source)} -DestinationPath ${quote(archive)}`]);
    let installed = false;
    try {
        await page.goto('/wp-admin/plugin-install.php?tab=upload');
        await page.locator('input[name=pluginzip]').setInputFiles(archive);
        await page.locator('#install-plugin-submit').click();
        await expect(page.locator('#wpbody-content')).toContainText('Plugin installed successfully.');
        installed = true;
        await wpRequest(page, api, `wp/v2/plugins/${pluginId}`, { method: 'POST', body: { status: 'active' } });
        await page.goto('/wp-admin/');
        const nonce = await page.locator('[data-listing-probe-nonce]').getAttribute('data-listing-probe-nonce');
        const guest = await browser.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
        try {
            const response = await guest.request.post('/wp-admin/admin-ajax.php', {
                form: { action: 'listing_regression_probe', mode: 'public_guest', _ajax_nonce: nonce },
            });
            expect(response.status()).toBe(400);
        } finally { await guest.close(); }

        for (const mode of ['authenticated', 'private_guest', 'public_guest']) {
            const response = await page.request.post('/wp-admin/admin-ajax.php', {
                form: { action: 'listing_regression_probe', mode, _ajax_nonce: nonce },
            });
            expect(response.ok()).toBe(true);
            const result = await response.json();
            expect(result.success).toBe(true);
            expect(result.data.errors, mode).toEqual([]);
            expect(Object.keys(result.data.checks).length).toBeGreaterThan(60);
            expect.soft(Object.entries(result.data.checks).filter(([, pass]) => !pass), mode).toEqual([]);
        }
    } finally {
        if (installed) {
            await wpRequest(page, api, `wp/v2/plugins/${pluginId}`, { method: 'POST', body: { status: 'inactive' } });
            await wpRequest(page, api, `wp/v2/plugins/${pluginId}`, { method: 'DELETE' });
        }
    }
});
