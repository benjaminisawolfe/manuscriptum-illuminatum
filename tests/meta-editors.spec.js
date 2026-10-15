const { test, expect } = require('./support/runtime-probes');
const { randomBytes } = require('node:crypto');
const { getApiSettings, wpRequest } = require('./support/personae-fixture');

test.use({ actionTimeout: 15_000, trace: 'off' });
test.beforeEach(async ({}, info) => test.skip(info.project.name !== 'desktop', 'Disposable editor fixtures run once.'));

async function openEditor(page, id, classic = false) {
    // Start in Visual, reproducing initialization before Gutenberg moves boxes.
    await page.evaluate(() => window.setUserSetting?.('editor', 'tinymce'));
    await page.goto(`/wp-admin/post.php?post=${id}&action=edit${classic ? '&mi_fixture_classic=1' : ''}`, { waitUntil: 'domcontentloaded' });
    const takeover = page.locator('a[href*="get-post-lock=1"]');
    if (await takeover.count()) await takeover.first().click();
    await expect(page.locator('[name="ligatura_manuscripti_illuminati_meta_nonce"]')).toBeAttached();
    await page.waitForLoadState('load');
    if (!classic) {
        const presenter = page.locator('.edit-post-meta-boxes-main__presenter > button[aria-expanded]');
        await presenter.waitFor();
        const welcome = page.getByRole('dialog', { name: /Welcome/ });
        if (await welcome.isVisible()) await welcome.getByRole('button', { name: /Close/ }).click();
        if (await presenter.getAttribute('aria-expanded') === 'false') await presenter.evaluate(button => button.click());
    }
}

async function visual(page, key) {
    await page.locator(`#${key}-tmce`).click();
    const body = page.frameLocator(`#${key}_ifr`).locator('body');
    await expect(body, 'The visible iframe must contain the live editable body after meta-box relocation').toHaveAttribute('contenteditable', 'true');
    await page.waitForFunction(id => window.tinymce?.get(id)?.initialized, key);
    return body;
}

async function saveEditor(page, classic) {
    if (classic) {
        const button = await page.locator('#save-post').isVisible() ? page.locator('#save-post') : page.locator('#publish');
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]);
        return;
    }
    const response = page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('meta-box-loader='));
    await page.getByRole('button', { name: /^Save(?: draft)?$/ }).click();
    expect([200, 302]).toContain((await response).status());
    await expect.poll(() => page.evaluate(() => !wp.data.select('core/editor').isSavingPost()
        && !wp.data.select('core/edit-post').isSavingMetaBoxes() && wp.data.select('core/editor').didPostSaveRequestSucceed())).toBe(true);
}

for (const context of ['block', 'classic', 'assigned Player', 'Storyguide']) {
    test(`${context} Character Visual editors accept typing, formatting, mode switching and save independently`, async ({ page: adminPage, browser, baseURL, regressionProbes }) => {
        test.setTimeout(90_000);
        const api = await getApiSettings(adminPage);
        const admin = await wpRequest(adminPage, api, 'wp/v2/users/me');
        const types = await wpRequest(adminPage, api, 'wp/v2/ligatura_character_type?per_page=100');
        const suffix = randomBytes(8).toString('hex');
        const role = context === 'assigned Player' ? 'ligatura_player' : context === 'Storyguide' ? 'ligatura_storyguide' : null;
        let page = adminPage, actor, user, post;
        const errors = [];
        const classic = context === 'classic';
        try {
            if (role) {
                const username = `fixture_visual_${suffix}`;
                const password = randomBytes(36).toString('base64url');
                user = await wpRequest(adminPage, api, 'wp/v2/users', { method: 'POST', body: {
                    username, password, email: `${username}@example.invalid`, roles: [role],
                } });
                actor = await browser.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
                page = await actor.newPage();
                await page.goto('/wp-login.php');
                await page.locator('#user_login').fill(username);
                await page.locator('#user_pass').fill(password);
                await Promise.all([page.waitForURL(/\/wp-admin\//), page.locator('#wp-submit').click()]);
            }
            page.on('pageerror', error => errors.push(error.message));
            post = await wpRequest(adminPage, api, 'wp/v2/ligatura_character', { method: 'POST', body: {
                title: `Fixture Visual editors ${suffix}`, status: 'publish', ligatura_character_type: [types[0].id],
                meta: { _ligatura_assigned_player: user?.id || admin.id },
            } });
            await openEditor(page, post.id, classic);
            const sheet = await visual(page, 'ligatura_full_sheet');
            await sheet.click();
            await page.keyboard.type('Sheet text');
            await expect(sheet).toContainText('Sheet text');
            await page.keyboard.press('Control+A');
            await page.locator('#wp-ligatura_full_sheet-wrap').getByRole('button', { name: /^Bold/ }).click();
            await expect(sheet.locator('strong')).toHaveText('Sheet text');
            const notes = await visual(page, 'ligatura_notes_public');
            await notes.click();
            await page.keyboard.type('Independent notes');
            await expect(notes).toContainText('Independent notes');
            await page.keyboard.press('Control+A');
            await page.locator('#wp-ligatura_notes_public-wrap').getByRole('button', { name: /^Italic/ }).click();
            await expect(notes.locator('em')).toHaveText('Independent notes');
            await page.locator('#ligatura_full_sheet-html').click();
            await expect(page.locator('#ligatura_full_sheet')).toHaveValue(/<strong>Sheet text<\/strong>/);
            await page.locator('#ligatura_full_sheet').fill('<p><strong>Sheet text</strong> <a href="https://example.com/sheet">Reference</a></p>');
            await visual(page, 'ligatura_full_sheet');
            await expect(sheet.locator('a')).toHaveAttribute('href', 'https://example.com/sheet');
            await expect(notes.locator('em')).toHaveText('Independent notes');
            await saveEditor(page, classic);
            const stored = await wpRequest(adminPage, api, `ligatura-manuscripti-illuminati/v1/meta-saving/stored?post_id=${post.id}`);
            // wpautop legitimately strips a single paragraph while retaining
            // all authored text, formatting and link attributes.
            expect(['<p><strong>Sheet text</strong> <a href="https://example.com/sheet">Reference</a></p>', '<strong>Sheet text</strong> <a href="https://example.com/sheet">Reference</a>']).toContain(stored.meta.ligatura_full_sheet);
            expect(['<p><em>Independent notes</em></p>', '<em>Independent notes</em>']).toContain(stored.meta.ligatura_notes_public);
            await openEditor(page, post.id, classic);
            await expect((await visual(page, 'ligatura_full_sheet')).locator('strong')).toHaveText('Sheet text');
            await expect((await visual(page, 'ligatura_notes_public')).locator('em')).toHaveText('Independent notes');
            // A second Visual edit after reloading must not replay stale REST metadata.
            await (await visual(page, 'ligatura_full_sheet')).click();
            await page.keyboard.press('Control+End');
            await page.keyboard.type(' again');
            await saveEditor(page, classic);
            const repeated = await wpRequest(adminPage, api, `ligatura-manuscripti-illuminati/v1/meta-saving/stored?post_id=${post.id}`);
            expect(repeated.meta.ligatura_full_sheet).toContain('again');
            expect(repeated.meta.ligatura_notes_public).toBe(stored.meta.ligatura_notes_public);
            if (classic) await openEditor(page, post.id, true);
            await expect(page.locator('.postbox .insert-media')).toHaveCount(0);
            if (classic) await expect(page.locator('#wp-content-wrap .insert-media')).toBeVisible();
            expect(errors).toEqual([]);
        } finally {
            await actor?.close();
            if (post) await wpRequest(adminPage, api, `wp/v2/ligatura_character/${post.id}?force=true`, { method: 'DELETE' });
            if (user) await wpRequest(adminPage, api, `wp/v2/users/${user.id}?force=true&reassign=${admin.id}`, { method: 'DELETE' });
        }
    });
}

test('Wiki generic and Storyguide Visual fields survive editing and a meta-box move', async ({ page, regressionProbes }) => {
    test.setTimeout(90_000);
    const api = await getApiSettings(page);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const post = await wpRequest(page, api, 'wp/v2/ligatura_wiki', { method: 'POST', body: {
        title: `Fixture Wiki Visual ${Date.now()}`, status: 'draft',
    } });
    try {
        await openEditor(page, post.id);
        for (const [key, text] of [['ligatura_public_summary', 'Public summary'], ['ligatura_storyguide_notes', 'Private fixture notes']]) {
            const body = await visual(page, key);
            await body.click();
            await page.keyboard.type(text);
        }
        const box = page.locator('#manuscriptum-illuminatum-storyguide-notes');
        await box.getByRole('button', { name: /Move up/ }).click();
        const notes = await visual(page, 'ligatura_storyguide_notes');
        await expect(notes).toContainText('Private fixture notes');
        await notes.click();
        await page.keyboard.press('Control+End');
        await page.keyboard.type(' after moving');
        await saveEditor(page, false);
        const stored = await wpRequest(page, api, `ligatura-manuscripti-illuminati/v1/meta-saving/stored?post_id=${post.id}`);
        expect(['<p>Public summary</p>', 'Public summary']).toContain(stored.meta.ligatura_public_summary);
        expect(['<p>Private fixture notes after moving</p>', 'Private fixture notes after moving']).toContain(stored.meta.ligatura_storyguide_notes);
        await expect(page.locator('.postbox .insert-media')).toHaveCount(0);
        expect(errors).toEqual([]);
    } finally {
        await wpRequest(page, api, `wp/v2/ligatura_wiki/${post.id}?force=true`, { method: 'DELETE' });
    }
});
