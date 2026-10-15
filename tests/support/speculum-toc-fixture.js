const { test: base, expect } = require('@playwright/test');
const { getApiSettings, wpRequest } = require('./personae-fixture');

const depthSetting = 'paginae_manuscripti_illuminati_wiki_toc_depth';
const enabledSetting = 'paginae_manuscripti_illuminati_enable_wiki_toc';

const test = base.extend({
    tocFixture: [async ({ page, baseURL }, use) => {
        // These tests publish temporary entries and must never target production.
        expect(new URL(baseURL).origin).toBe('https://devgaillard.wolfshafenpress.com');
        const api = await getApiSettings(page);
        const settingsPage = await page.context().newPage();
        await settingsPage.setViewportSize({ width: 1280, height: 900 });
        const depth = settingsPage.locator(`#_customize-input-${depthSetting}`);
        const enabled = settingsPage.locator(`#_customize-input-${enabledSetting}`);
        const entries = [];
        let original;

        async function openSettings() {
            await settingsPage.goto('/wp-admin/customize.php?autofocus[section]=paginae_manuscripti_illuminati_wiki');
            await expect(settingsPage, 'The saved Playwright authentication state needs to be refreshed.').not.toHaveURL(/wp-login\.php/);
            await expect(depth).toBeVisible();
        }

        async function setSettings(value, show = true) {
            await openSettings();
            if (await depth.inputValue() === String(value) && await enabled.isChecked() === show) return;
            await depth.selectOption(String(value));
            await enabled.setChecked(show);
            await expect(settingsPage.locator('#save')).toBeEnabled();
            const saved = settingsPage.waitForResponse(response => response.url().includes('admin-ajax.php')
                && (response.request().postData() || '').includes('action=customize_save'));
            await settingsPage.locator('#save').click();
            const response = await saved;
            expect(response.ok()).toBe(true);
            expect((await response.json()).success).toBe(true);
            await expect(settingsPage.locator('#save')).toBeDisabled();
            await openSettings();
            await expect(depth).toHaveValue(String(value));
            await expect(enabled).toBeChecked({ checked: show });
        }

        try {
            await openSettings();
            original = { depth: await depth.inputValue(), enabled: await enabled.isChecked() };
            await setSettings(4);
            await use({
                settingsPage, depth, setSettings,
                async create(content, type = 'ligatura_wiki', extra = {}) {
                    const entry = await wpRequest(page, api, `wp/v2/${type}`, {
                        method: 'POST',
                        body: { title: `TOC fixture ${Date.now()}`, content, status: 'publish', ...extra },
                    });
                    entries.push({ id: entry.id, type });
                    return entry;
                },
                async visit(entry) {
                    expect(new URL(entry.link).origin).toBe(new URL(baseURL).origin);
                    await page.goto(entry.link);
                    await expect(page).not.toHaveURL(/wp-login\.php/);
                },
                async setOutsideHeadings(entry) {
                    await page.goto(`/wp-admin/post.php?post=${entry.id}&action=edit`, { waitUntil: 'domcontentloaded' });
                    for (const [field, text] of [
                        ['ligatura_public_summary', 'Outside article summary'],
                        ['ligatura_storyguide_notes', 'Private fixture heading'],
                    ]) {
                        const visualTab = page.locator(`#${field}-tmce`);
                        await visualTab.click();
                        await expect(page.locator(`#${field}_ifr`)).toBeVisible();
                        await page.locator(`#${field}-html`).click();
                        await page.locator(`textarea[name="${field}"]`).fill(`<h2>${text}</h2>`);
                    }
                    if (await page.locator('body.block-editor-page').count()) {
                        const saved = page.waitForResponse(response => response.url().includes('/wp-admin/post.php')
                            && response.request().method() === 'POST' && response.status() < 400);
                        await page.getByRole('button', { name: /^(Save|Update)$/ }).last().click();
                        await saved;
                    } else {
                        await Promise.all([
                            page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
                            page.locator('#publish').click(),
                        ]);
                    }
                },
            });
        } finally {
            // Attempt every cleanup even when an assertion or another cleanup fails.
            const errors = [];
            if (original) {
                try { await setSettings(original.depth, original.enabled); } catch (error) { errors.push(error); }
            }
            for (const entry of entries.reverse()) {
                try {
                    await wpRequest(page, api, `wp/v2/${entry.type}/${entry.id}?force=true`, { method: 'DELETE' });
                } catch (error) { errors.push(error); }
            }
            await settingsPage.close();
            if (errors.length) throw new AggregateError(errors, 'TOC fixture cleanup was incomplete.');
        }
    }, { timeout: 180_000 }],
});

module.exports = { test, expect };
