const { test, expect } = require('./support/runtime-probes');
const { randomBytes } = require('node:crypto');
const { getApiSettings, wpRequest, wpRawRequest } = require('./support/personae-fixture');

const metaNonce = 'ligatura_manuscripti_illuminati_meta_nonce';
const assignment = '_ligatura_assigned_player';
test.use({ actionTimeout: 15_000 });

test.beforeEach(async ({}, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Database mutations run once.');
});

async function openEditor(page, id) {
    await page.goto(`/wp-admin/post.php?post=${id}&action=edit`, { waitUntil: 'domcontentloaded' });
    const takeover = page.locator('a[href*="get-post-lock=1"]');
    if (await takeover.count()) await takeover.first().click();
    await expect(page.locator(`[name="${metaNonce}"]`)).toBeAttached();
    const fields = page.locator('.manuscriptum-illuminatum-admin-fields').first();
    if (!(await fields.isVisible())) {
        const presenter = page.locator('.edit-post-meta-boxes-main__presenter > button[aria-expanded]');
        await presenter.waitFor({ state: 'attached' });
        if (await presenter.getAttribute('aria-expanded') === 'false') await presenter.evaluate(button => button.click());
    }
    await expect(fields).toBeVisible();
    const welcome = page.getByRole('dialog', { name: /Welcome/ });
    if (await welcome.isVisible()) await welcome.getByRole('button', { name: /Close/ }).click();
}

async function textMode(page, key) {
    const tab = page.locator(`#${key}-html`);
    if (await tab.count()) await tab.click();
    await expect(page.locator(`#${key}`)).toBeVisible();
    return page.locator(`#${key}`);
}

async function saveEditor(page) {
    const metaResponse = page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('meta-box-loader='));
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    const response = await metaResponse;
    expect(response.status(), 'The separate meta-box save must succeed as well as the REST save').toBe(200);
    await expect.poll(() => page.evaluate(() => {
        const editor = wp.data.select('core/editor');
        return !editor.isSavingPost() && !wp.data.select('core/edit-post').isSavingMetaBoxes() && editor.didPostSaveRequestSucceed();
    }), { timeout: 20_000 }).toBe(true);
}

async function partialForm(page, id, fields, validNonce = true) {
    const nonce = await page.locator('#_wpnonce').inputValue();
    const fieldNonce = await page.locator(`[name="${metaNonce}"]`).inputValue();
    return page.request.post('/wp-admin/post.php', { form: {
        action: 'editpost', post_ID: String(id), _wpnonce: nonce,
        ...(validNonce === null ? {} : { [metaNonce]: validNonce ? fieldNonce : 'invalid' }), ...fields,
    } });
}

for (const type of ['ligatura_character', 'ligatura_wiki', 'ligatura_diary', 'ligatura_covenant']) {
    test(`partial ${type} form preserves omitted metadata and allows explicit clearing`, async ({ page, regressionProbes }) => {
        const api = await getApiSettings(page);
        const ordinary = type === 'ligatura_character' ? 'ligatura_occupation' : type === 'ligatura_diary' ? 'ligatura_diary_session_date' : 'ligatura_entry_status';
        const untouched = type === 'ligatura_character' ? 'ligatura_notes_public' : type === 'ligatura_diary' ? 'ligatura_diary_saga_date' : 'ligatura_public_summary';
        const seed = type === 'ligatura_diary' ? '1207-01-02' : '<p>Keep this <strong>unchanged</strong>.</p>';
        const value = type === 'ligatura_diary' ? '2026-10-15' : 'Revised';
        const post = await wpRequest(page, api, `wp/v2/${type}`, { method: 'POST', body: {
            title: `Fixture partial save ${Date.now()}`, status: 'draft', meta: { [ordinary]: 'original', [untouched]: seed },
        } });
        try {
            await openEditor(page, post.id);
            // Wiki intentionally uses the classic meta-box interface, without REST meta exposure.
            if (type === 'ligatura_wiki') await partialForm(page, post.id, { [ordinary]: 'original', [untouched]: seed });
            const readStored = () => wpRequest(page, api, `ligatura-manuscripti-illuminati/v1/meta-saving/stored?post_id=${post.id}`);
            const response = await partialForm(page, post.id, { [ordinary]: value });
            expect(response.ok()).toBe(true);
            let stored = await readStored();
            expect(stored.meta[ordinary]).toBe(value);
            expect(stored.meta[untouched], 'An omitted field is not a request to erase it').toBe(seed);
            await openEditor(page, post.id);
            expect(await page.locator(`#${untouched}`).inputValue()).toBe(seed);
            await partialForm(page, post.id, { [ordinary]: '' });
            stored = await readStored();
            expect(stored.meta[ordinary]).toBe('');
            expect(stored.meta[untouched]).toBe(seed);
            for (const nonce of [false, null]) {
                const rejected = await partialForm(page, post.id, { [untouched]: 'must not save' }, nonce);
                expect(rejected.status(), 'Invalid and absent metadata nonces must give feedback').toBe(403);
                stored = await readStored();
                expect(stored.meta[untouched]).toBe(seed);
            }
        } finally {
            await wpRequest(page, api, `wp/v2/${type}/${post.id}?force=true`, { method: 'DELETE' });
        }
    });
}

for (const role of ['administrator', 'ligatura_storyguide', 'ligatura_player']) {
    test(`${role} saves published Character ordinary and Code-mode metadata through the editor`, async ({ page, browser, baseURL }, testInfo) => {
        test.setTimeout(120_000);
        const api = await getApiSettings(page);
        const admin = await wpRequest(page, api, 'wp/v2/users/me');
        const suffix = randomBytes(8).toString('hex');
        const username = `fixture_save_${suffix}`;
        const password = randomBytes(36).toString('base64url');
        let user, post, context;
        try {
            if (role !== 'administrator') user = await wpRequest(page, api, 'wp/v2/users', { method: 'POST', body: {
                username, password, email: `${username}@example.invalid`, roles: [role],
            } });
            const types = await wpRequest(page, api, 'wp/v2/ligatura_character_type?per_page=100');
            post = await wpRequest(page, api, 'wp/v2/ligatura_character', { method: 'POST', body: {
                title: `Fixture editor save ${suffix}`, status: 'publish', ligatura_character_type: [types[0].id],
                meta: { [assignment]: user?.id || admin.id, ligatura_occupation: 'Before', ligatura_full_sheet: '<p>Existing sheet.</p>', ligatura_notes_public: '<p>Preserved notes.</p>' },
            } });
            let editorPage = page;
            if (user) {
                context = await browser.newContext({ baseURL });
                editorPage = await context.newPage();
                await editorPage.goto('/wp-login.php');
                await editorPage.locator('#user_login').fill(username);
                await editorPage.locator('#user_pass').fill(password);
                await editorPage.locator('#wp-submit').click();
                await expect(editorPage).toHaveURL(/\/wp-admin\//);
            }
            const failures = [];
            editorPage.on('pageerror', error => failures.push(error.message));
            editorPage.on('response', response => { if (response.status() >= 400 && /wp-json|post\.php/.test(response.url())) failures.push(`HTTP ${response.status()} ${new URL(response.url()).pathname}`); });
            await openEditor(editorPage, post.id);
            await editorPage.locator('#ligatura_occupation').fill('After editor save');
            await editorPage.locator('#ligatura_age').fill('34');
            await (await textMode(editorPage, 'ligatura_full_sheet')).fill('<p>Saved <strong>sheet</strong>.</p>');
            await saveEditor(editorPage);
            const stored = await wpRequest(page, api, `wp/v2/ligatura_character/${post.id}?context=edit`);
            expect(stored.meta.ligatura_occupation).toBe('After editor save');
            expect(stored.meta.ligatura_age).toBe(34);
            expect(stored.meta.ligatura_full_sheet).toBe('<p>Saved <strong>sheet</strong>.</p>');
            // wp_editor's paragraph formatting can serialize a single paragraph
            // without its wrapper; the untouched rich-text content must survive.
            expect(['<p>Preserved notes.</p>', 'Preserved notes.']).toContain(stored.meta.ligatura_notes_public);
            expect(stored.status).toBe('publish');
            // A second save without reload must not replay the REST response's
            // older metadata over the completed meta-box request.
            await editorPage.locator('#ligatura_occupation').fill('');
            await editorPage.evaluate(() => wp.data.dispatch('core/editor').editPost({
                title: 'Fixture updated Character title', content: '<p>Updated body.</p>', excerpt: 'Updated excerpt.',
            }));
            await saveEditor(editorPage);
            const repeated = await wpRequest(page, api, `wp/v2/ligatura_character/${post.id}?context=edit`);
            expect(repeated.meta.ligatura_occupation).toBe('');
            expect(repeated.meta.ligatura_full_sheet).toBe('<p>Saved <strong>sheet</strong>.</p>');
            expect(['<p>Preserved notes.</p>', 'Preserved notes.']).toContain(repeated.meta.ligatura_notes_public);
            expect(repeated.content.raw).toBe('<p>Updated body.</p>');
            expect(repeated.title.raw).toBe('Fixture updated Character title');
            expect(repeated.excerpt.raw).toBe('Updated excerpt.');
            await openEditor(editorPage, post.id);
            await expect(editorPage.locator('#ligatura_occupation')).toHaveValue('');
            expect(await editorPage.locator('#ligatura_full_sheet').inputValue()).toBe('<p>Saved <strong>sheet</strong>.</p>');
            await testInfo.attach('reloaded-editor', { body: await editorPage.screenshot({ fullPage: true }), contentType: 'image/png' });
            await testInfo.attach('browser-errors', { body: JSON.stringify(failures), contentType: 'application/json' });
            expect(failures).toEqual([]);
        } finally {
            await context?.close().catch(() => {});
            if (post) await wpRequest(page, api, `wp/v2/ligatura_character/${post.id}?force=true`, { method: 'DELETE' });
            if (user) await wpRequest(page, api, `wp/v2/users/${user.id}?force=true&reassign=${admin.id}`, { method: 'DELETE' });
        }
    });
}

test('Wiki partial saves preserve private notes and relationships; deselecting relationships clears them', async ({ page, regressionProbes }) => {
    const api = await getApiSettings(page);
    const posts = [];
    try {
        for (let index = 0; index < 2; index++) posts.push(await wpRequest(page, api, 'wp/v2/ligatura_wiki', { method: 'POST', body: {
            title: `Fixture private save ${Date.now()} ${index}`, status: 'draft',
        } }));
        const id = posts[0].id;
        await openEditor(page, id);
        await partialForm(page, id, { ligatura_storyguide_notes: '<p>Private preserved.</p>', 'ligatura_related_entries[]': String(posts[1].id) });
        await partialForm(page, id, { ligatura_entry_status: 'Reviewed' });
        const read = () => wpRequest(page, api, `ligatura-manuscripti-illuminati/v1/meta-saving/stored?post_id=${id}`);
        let stored = await read();
        expect(['<p>Private preserved.</p>', 'Private preserved.']).toContain(stored.meta.ligatura_storyguide_notes);
        expect(stored.meta.ligatura_related_entries).toBe(String(posts[1].id));
        await openEditor(page, id);
        await page.locator('#ligatura_related_entries').selectOption([]);
        await saveEditor(page);
        stored = await read();
        expect(stored.meta.ligatura_related_entries).toBe('');
        expect(['<p>Private preserved.</p>', 'Private preserved.']).toContain(stored.meta.ligatura_storyguide_notes);
        await partialForm(page, id, { ligatura_storyguide_notes: '' });
        expect((await read()).meta.ligatura_storyguide_notes).toBe('');
    } finally {
        for (const post of posts.reverse()) await wpRequest(page, api, `wp/v2/ligatura_wiki/${post.id}?force=true`, { method: 'DELETE' });
    }
});

test('assignment nonce without assignment field does not reassign a Character', async ({ page }) => {
    const api = await getApiSettings(page);
    const suffix = randomBytes(8).toString('hex');
    const admin = await wpRequest(page, api, 'wp/v2/users/me');
    const user = await wpRequest(page, api, 'wp/v2/users', { method: 'POST', body: {
        username: `fixture_save_${suffix}`, password: randomBytes(36).toString('base64url'),
        email: `fixture_save_${suffix}@example.invalid`, roles: ['ligatura_player'],
    } });
    let post;
    try {
        post = await wpRequest(page, api, 'wp/v2/ligatura_character', { method: 'POST', body: {
            title: `Fixture assignment save ${suffix}`, status: 'draft', meta: { [assignment]: user.id },
        } });
        await openEditor(page, post.id);
        await partialForm(page, post.id, {
            ligatura_assigned_player_nonce: await page.locator('[name="ligatura_assigned_player_nonce"]').inputValue(),
            ligatura_occupation: 'Partial update',
        });
        const stored = await wpRequest(page, api, `wp/v2/ligatura_character/${post.id}?context=edit`);
        expect(stored.meta[assignment]).toBe(user.id);
    } finally {
        if (post) await wpRequest(page, api, `wp/v2/ligatura_character/${post.id}?force=true`, { method: 'DELETE' });
        await wpRequest(page, api, `wp/v2/users/${user.id}?force=true&reassign=${admin.id}`, { method: 'DELETE' });
    }
});
