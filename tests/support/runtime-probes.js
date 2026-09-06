const path = require('node:path');
const { readFile, writeFile } = require('node:fs/promises');
const { test: base, expect } = require('@playwright/test');
const { getApiSettings, wpRequest } = require('./personae-fixture');

const pluginId = 'mi-runtime-regression-probes/probe';
const routes = [
    '/ligatura-manuscripti-illuminati/v1/character-publication/persistence-probe',
    '/ligatura-manuscripti-illuminati/v1/player-access/status',
    '/ligatura-manuscripti-illuminati/v1/player-access/bootstrap-probe',
];

async function expectNoRuntimeProbes(page, api) {
    const index = await wpRequest(page, api, '');
    for (const route of routes) expect(index.routes, 'Test diagnostics must be absent from the product').not.toHaveProperty(route);
}

const test = base.extend({
    regressionProbes: [async ({ page }, use, testInfo) => {
        if (testInfo.project.name !== 'desktop') { await use(); return; }
        const api = await getApiSettings(page);
        const plugins = await wpRequest(page, api, 'wp/v2/plugins');
        expect(plugins.some(plugin => plugin.plugin === pluginId), 'Do not replace an existing test-support plugin').toBe(false);
        await expectNoRuntimeProbes(page, api);
        const { createZip } = await import('../../scripts/build-packages.mjs');
        const names = ['probe.php', 'character-publication.php', 'player-access.php'];
        const entries = await Promise.all(names.map(async name => ({
            name: 'mi-runtime-regression-probes/' + name,
            content: await readFile(path.join(__dirname, 'mi-runtime-regression-probes', name)),
        })));
        const archive = testInfo.outputPath('mi-runtime-regression-probes.zip');
        await writeFile(archive, createZip(entries));
        let installed = false;
        try {
            await page.goto('/wp-admin/plugin-install.php?tab=upload');
            await page.locator('input[name=pluginzip]').setInputFiles(archive);
            await page.locator('#install-plugin-submit').click();
            await expect(page.locator('#wpbody-content')).toContainText('Plugin installed successfully.');
            installed = true;
            await wpRequest(page, api, 'wp/v2/plugins/' + pluginId, { method: 'POST', body: { status: 'active' } });
            const index = await wpRequest(page, api, '');
            for (const route of routes) expect(index.routes).toHaveProperty(route);
            await use();
        } finally {
            if (installed) {
                await wpRequest(page, api, 'wp/v2/plugins/' + pluginId, { method: 'POST', body: { status: 'inactive' } });
                await wpRequest(page, api, 'wp/v2/plugins/' + pluginId, { method: 'DELETE' });
                await expectNoRuntimeProbes(page, api);
            }
        }
    }, { timeout: 60_000 }],
});

module.exports = { test, expect };
