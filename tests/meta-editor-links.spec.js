const { test, expect } = require('@playwright/test');
const { getApiSettings, wpRequest } = require('./support/personae-fixture');

test.use({ actionTimeout: 15_000, trace: 'off' });

async function openCharacterEditor(page, id) {
    await page.goto(`/wp-admin/post.php?post=${id}&action=edit`, { waitUntil: 'load' });
    const presenter = page.locator('.edit-post-meta-boxes-main__presenter > button[aria-expanded]');
    await presenter.waitFor();
    const welcome = page.getByRole('dialog', { name: /Welcome/ });
    if (await welcome.isVisible()) await welcome.getByRole('button', { name: /Close/ }).click();
    if (await presenter.getAttribute('aria-expanded') === 'false') await presenter.evaluate(button => button.click());
}

async function visualBody(page, key) {
    await page.locator(`#${key}-tmce`).click();
    const body = page.frameLocator(`#${key}_ifr`).locator('body');
    await expect(body).toHaveAttribute('contenteditable', 'true');
    await page.waitForFunction(id => window.tinymce?.get(id)?.initialized, key);
    return body;
}

test('Visual link toolbar creates and edits links without stealing focus or duplicating editor IDs', async ({ page }, info) => {
    test.skip(info.project.name !== 'desktop', 'Disposable editor fixture runs once.');
    test.setTimeout(90_000);
    const api = await getApiSettings(page);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const post = await wpRequest(page, api, 'wp/v2/ligatura_character', { method: 'POST', body: {
        title: `Fixture Visual link toolbar ${Date.now()}`, status: 'draft',
    } });
    try {
        await openCharacterEditor(page, post.id);
        const sheet = await visualBody(page, 'ligatura_full_sheet');
        await sheet.click();
        await expect(sheet).toBeFocused();
        await page.keyboard.type('Sheet reference');
        await page.keyboard.press('Control+A');
        await page.locator('#wp-ligatura_full_sheet-wrap').getByRole('button', { name: /^Insert\/edit link/ }).click();

        // Exercise the real WordPress inline link dialog and its keyboard Apply
        // action. No HTML, TinyMCE commands or synthetic content is injected.
        const url = page.getByRole('combobox', { name: 'Paste URL or type to search', exact: true }).filter({ visible: true });
        await expect(url).toBeFocused();
        await url.fill('https://example.com/first-sheet');
        await url.press('Enter');
        await expect(sheet.locator('a')).toHaveText('Sheet reference');
        await expect(sheet.locator('a')).toHaveAttribute('href', 'https://example.com/first-sheet');
        await expect(sheet).toBeFocused();

        await sheet.locator('a').click();
        await page.locator('#wp-ligatura_full_sheet-wrap').getByRole('button', { name: /^Insert\/edit link/ }).click();
        await expect(url).toHaveValue('https://example.com/first-sheet');
        await url.fill('https://example.com/revised-sheet');
        await url.press('Enter');
        await expect(sheet.locator('a')).toHaveAttribute('href', 'https://example.com/revised-sheet');
        await expect(sheet).toBeFocused();
        await page.keyboard.press('Escape');

        const notes = await visualBody(page, 'ligatura_notes_public');
        await notes.click();
        await expect(notes).toBeFocused();
        await page.keyboard.type('Notes keep their own caret and content.');
        await expect(notes).toHaveText('Notes keep their own caret and content.');
        await expect(sheet.locator('a')).toHaveText('Sheet reference');

        const duplicates = await page.locator('.postbox .wp-editor-wrap, .postbox .wp-editor-wrap [id]').evaluateAll(elements => {
            const ids = elements.map(element => element.id).filter(Boolean);
            return ids.filter((id, index) => ids.indexOf(id) !== index);
        });
        expect(duplicates, 'Each editor, toolbar control and iframe must have a unique ID').toEqual([]);
        for (const key of ['ligatura_full_sheet', 'ligatura_notes_public']) {
            await expect(page.locator(`#${key}`)).toHaveCount(1);
            await expect(page.locator(`#${key}_ifr`)).toHaveCount(1);
        }

        const saved = page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('meta-box-loader='));
        await page.getByRole('button', { name: /^Save(?: draft)?$/ }).click();
        expect([200, 302]).toContain((await saved).status());
        await expect.poll(() => page.evaluate(() => !wp.data.select('core/editor').isSavingPost()
            && !wp.data.select('core/edit-post').isSavingMetaBoxes() && wp.data.select('core/editor').didPostSaveRequestSucceed())).toBe(true);
        const stored = await wpRequest(page, api, `wp/v2/ligatura_character/${post.id}?context=edit`);
        expect(['<a href="https://example.com/revised-sheet">Sheet reference</a>', '<p><a href="https://example.com/revised-sheet">Sheet reference</a></p>']).toContain(stored.meta.ligatura_full_sheet);
        expect(['Notes keep their own caret and content.', '<p>Notes keep their own caret and content.</p>']).toContain(stored.meta.ligatura_notes_public);
        await openCharacterEditor(page, post.id);
        await expect((await visualBody(page, 'ligatura_full_sheet')).locator('a')).toHaveAttribute('href', 'https://example.com/revised-sheet');
        await expect(await visualBody(page, 'ligatura_notes_public')).toHaveText('Notes keep their own caret and content.');
        const screenshot = info.outputPath('visual-link-editors.png');
        await page.locator('.metabox-location-normal').screenshot({ path: screenshot });
        await info.attach('Visual link and independent notes', { path: screenshot, contentType: 'image/png' });
        expect(errors).toEqual([]);
    } finally {
        await wpRequest(page, api, `wp/v2/ligatura_character/${post.id}?force=true`, { method: 'DELETE' });
    }
});
