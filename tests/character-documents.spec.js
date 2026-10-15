const { test, expect } = require('./support/runtime-probes');
const { withDocumentFixture } = require('./support/document-fixture');
const { documentFiles } = require('./support/document-files');
const { wpRequest, wpRawRequest, uploadFixtureImage } = require('./support/personae-fixture');

const metaKey = '_ligatura_character_documents';
const nonceKey = 'ligatura_character_documents_nonce';
const route = id => `ligatura-manuscripti-illuminati/v1/characters/${id}/documents`;

test.use({ actionTimeout: 15_000, navigationTimeout: 20_000, trace: 'off' });
test.beforeEach(async ({}, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Document mutation fixtures run once.');
    test.setTimeout(120_000);
});

async function openDocuments(page, id) {
    await page.goto(`/wp-admin/post.php?post=${id}&action=edit`, { waitUntil: 'domcontentloaded' });
    const takeover = page.locator('a[href*="get-post-lock=1"]');
    if (await takeover.count()) await takeover.first().click();
    const welcome = page.getByRole('dialog', { name: /Welcome/ });
    if (await welcome.isVisible()) await welcome.getByRole('button', { name: /Close/ }).click();
    const presenter = page.locator('.edit-post-meta-boxes-main__presenter > button[aria-expanded]');
    if (await presenter.count() && await presenter.getAttribute('aria-expanded') === 'false') await presenter.evaluate(button => button.click());
    const documents = page.getByRole('region', { name: 'Character Documents', exact: true });
    await expect(documents).toBeVisible();
    return documents;
}

async function saveEditor(page) {
    const response = page.waitForResponse(result => result.request().method() === 'POST' && result.url().includes('meta-box-loader='));
    await page.getByRole('button', { name: /^Save(?: draft)?$/ }).click();
    expect([200, 302], 'The document meta-box request must succeed').toContain((await response).status());
    await expect.poll(() => page.evaluate(() => !wp.data.select('core/editor').isSavingPost()
        && !wp.data.select('core/edit-post').isSavingMetaBoxes() && wp.data.select('core/editor').didPostSaveRequestSucceed()), { timeout: 20_000 }).toBe(true);
}

async function storedIds(page, api, id) {
    const saved = await wpRequest(page, api, `ligatura-manuscripti-illuminati/v1/meta-saving/stored?post_id=${id}`);
    // WordPress returns an empty string for a meta key that has never been created.
    return saved.meta[metaKey] === '' ? [] : saved.meta[metaKey];
}

async function rawJson(page, api, path, options = {}) {
    const nonce = Object.hasOwn(options, 'nonce') ? options.nonce : api.nonce;
    const response = await page.request.fetch(new URL(path, api.root).href, {
        method: options.method || 'GET',
        headers: nonce === null ? {} : { 'X-WP-Nonce': nonce },
        ...(Object.hasOwn(options, 'body') ? { data: options.body } : {}),
    });
    return { ok: response.ok(), status: response.status(), body: await response.json() };
}

async function upload(page, api, id, file, track, options = {}) {
    const nonce = Object.hasOwn(options, 'nonce') ? options.nonce : api.nonce;
    const response = await page.request.post(new URL(`${route(id)}/upload`, api.root).href, {
        headers: { ...(nonce === null ? {} : { 'X-WP-Nonce': nonce }), ...options.headers },
        multipart: { document: file },
    });
    const result = { ok: response.ok(), status: response.status(), body: await response.json() };
    if (result.body?.id) track('media', result.body.id);
    return result;
}

function rejected(result, description) {
    expect(result.status, description).toBeGreaterThanOrEqual(400);
    expect(result.status, 'Validation failures must not crash the server').toBeLessThan(500);
    expect(result.body?.message, 'A rejected document request needs a useful error').toEqual(expect.any(String));
    expect(result.body.message.length).toBeGreaterThan(10);
}

async function documentForm(page, character, fields, nonce = true) {
    const documentsNonce = await page.locator(`[name="${nonceKey}"]`).inputValue();
    return page.request.post(`/wp-admin/post.php?post=${character.id}`, { form: {
        action: 'editpost', post_ID: String(character.id), post_type: character.type, post_author: String(character.author),
        _wpnonce: await page.locator('#_wpnonce').inputValue(),
        ligatura_manuscripti_illuminati_meta_nonce: await page.locator('[name="ligatura_manuscripti_illuminati_meta_nonce"]').inputValue(),
        ...(nonce === null ? {} : { [nonceKey]: nonce ? documentsNonce : 'invalid' }), ...fields,
    } });
}

test('assigned Player sees a separate Character Documents area with a direct-file privacy notice', async ({ page, browser, baseURL }) => {
    await withDocumentFixture({ page, browser, baseURL }, 'ligatura_player', async ({ actor, character }) => {
        const documents = await openDocuments(actor, character.id);
        await expect(documents).toContainText(/anyone with the (file )?link/i);
        await expect(documents.getByRole('button', { name: 'Upload Document', exact: true })).toBeVisible();
        await expect(documents.getByRole('button', { name: 'Attach Document', exact: true })).toBeVisible();
    });
});

test('Player uploads multiple documents, saves, uses the media picker and unlinks without deleting media', async ({ page, browser, baseURL, regressionProbes }, testInfo) => {
    await withDocumentFixture({ page, browser, baseURL }, 'ligatura_player', async ({ api, actor, actorApi, character, suffix, track }) => {
        const files = documentFiles(suffix), errors = [], pendingUploads = [];
        actor.on('pageerror', error => errors.push(error.message));
        const trackUpload = response => {
            if (response.url().endsWith(`${route(character.id)}/upload`) && response.request().method() === 'POST') {
                pendingUploads.push(response.json().then(body => { if (body.id) track('media', body.id); }));
            }
        };
        actor.on('response', trackUpload);
        try {
            let documents = await openDocuments(actor, character.id);
            await documents.locator('[data-document-upload]').setInputFiles([files.pdf, files.txt]);
            await documents.getByRole('button', { name: 'Upload Document', exact: true }).click();
            await expect(documents.locator('[data-document-list] > li')).toHaveCount(2);
            await expect(documents.locator('[data-document-status]')).toContainText(/save/i);
            await expect(documents).toHaveAttribute('aria-busy', 'false');
            const ids = await documents.locator('[data-document-id]').evaluateAll(rows => rows.map(row => Number(row.dataset.documentId)));
            expect(await storedIds(page, api, character.id), 'Upload creates media; Save persists the relationship').toEqual([]);
            await saveEditor(actor);
            expect(await storedIds(page, api, character.id)).toEqual(ids);
            documents = await openDocuments(actor, character.id);
            await expect(documents.locator('[data-document-id]')).toHaveCount(2);
            await expect(documents.locator('[data-document-list]')).toContainText('PDF');
            await expect(documents.locator('[data-document-list]')).toContainText('TXT');
            await testInfo.attach('character-documents-editor', { body: await documents.screenshot(), contentType: 'image/png' });
            await documents.locator(`[data-document-id="${ids[0]}"] [data-unlink-document]`).click();
            await saveEditor(actor);
            expect(await storedIds(page, api, character.id)).toEqual([ids[1]]);
            expect((await wpRequest(page, api, `wp/v2/media/${ids[0]}?context=edit`)).id).toBe(ids[0]);
            documents = await openDocuments(actor, character.id);
            await documents.getByRole('button', { name: 'Attach Document', exact: true }).click();
            const modal = actor.locator('.media-modal');
            await expect(modal).toBeVisible();
            await modal.locator(`.attachment[data-id="${ids[0]}"]`).click();
            await modal.getByRole('button', { name: 'Attach Document', exact: true }).click();
            await expect(documents.locator('[data-document-id]')).toHaveCount(2);
            await saveEditor(actor);
            expect(await storedIds(page, api, character.id), 'Reattaching appends in stable order').toEqual([ids[1], ids[0]]);
            await actor.goto(character.link);
            const section = actor.getByRole('region', { name: 'Documents', exact: true });
            await expect(section.getByRole('heading', { name: 'Documents', exact: true })).toBeVisible();
            await expect(section.getByRole('link')).toHaveCount(2);
            await expect(actor.getByRole('region', { name: 'Full Sheet', exact: true })).toContainText('Fixture full sheet.');
            const descriptors = await wpRequest(page, api, route(character.id));
            for (const document of descriptors.documents) {
                await expect(section.getByRole('link', { name: document.title, exact: true })).toHaveAttribute('href', document.url);
            }
            await testInfo.attach('character-documents-front-end', { body: await section.screenshot(), contentType: 'image/png' });
            expect(errors).toEqual([]);
        } finally {
            actor.off('response', trackUpload);
            await Promise.all(pendingUploads);
        }
    });
});

for (const role of ['administrator', 'ligatura_storyguide', 'ligatura_player']) {
    test(`${role} can upload, attach and unlink documents through the authorized API`, async ({ page, browser, baseURL, regressionProbes }) => {
        await withDocumentFixture({ page, browser, baseURL }, role, async ({ api, actor, actorApi, character, user, suffix, track }) => {
            const files = documentFiles(suffix);
            const result = await upload(actor, actorApi, character.id, files.pdf, track);
            expect(result.ok, `Permitted PDF upload returned HTTP ${result.status}`).toBe(true);
            const document = result.body;
            expect(document).toMatchObject({ id: expect.any(Number), title: expect.any(String), filename: expect.any(String), type: 'PDF', url: expect.any(String) });
            expect((await wpRequest(page, api, `wp/v2/media/${document.id}?context=edit`)).author).toBe(user.id);
            expect(await storedIds(page, api, character.id)).toEqual([]);
            const selected = await wpRequest(actor, actorApi, `${route(character.id)}?ids=${document.id}`);
            expect(selected.documents.map(item => item.id)).toEqual([document.id]);
            const ids = [document.id];
            if (role !== 'ligatura_player') {
                const foreign = await upload(page, api, character.id, files.txt, track);
                expect(foreign.ok).toBe(true);
                ids.push(foreign.body.id);
            }
            await wpRequest(actor, actorApi, route(character.id), { method: 'POST', body: { ids } });
            expect(await storedIds(page, api, character.id), 'Privileged roles may select another authorized uploader\'s document').toEqual(ids);
            expect((await wpRequest(actor, actorApi, route(character.id))).documents.map(item => item.id)).toEqual(ids);
            await wpRequest(actor, actorApi, route(character.id), { method: 'POST', body: { ids: [] } });
            expect(await storedIds(page, api, character.id)).toEqual([]);
            expect((await wpRequest(page, api, `wp/v2/media/${document.id}`)).id).toBe(document.id);
            await actor.goto(character.link);
            await expect(actor.getByRole('region', { name: 'Documents', exact: true })).toHaveCount(0);
        });
    });
}

test('safe document formats are accepted or report precise host identification limits', async ({ page, browser, baseURL, regressionProbes }, testInfo) => {
    await withDocumentFixture({ page, browser, baseURL }, 'ligatura_player', async ({ api, actor, actorApi, character, suffix, track }) => {
        const files = documentFiles(suffix), accepted = [];
        for (const extension of ['pdf', 'txt', 'rtf', 'docx', 'odt']) {
            const result = await upload(actor, actorApi, character.id, files[extension], track);
            if (['pdf', 'txt'].includes(extension) || result.ok) {
                expect(result.ok, `${extension.toUpperCase()} must upload safely; HTTP ${result.status}`).toBe(true);
                expect(result.body.type).toBe(extension.toUpperCase());
                accepted.push(result.body.id);
            } else {
                rejected(result, `Host must reject unidentifiable ${extension.toUpperCase()} safely`);
                expect(result.body.message, 'Unsupported host identification needs an explicit explanation').toMatch(/identif|verif|mime|server|host|WordPress/i);
                testInfo.annotations.push({ type: 'host-format-limitation', description: `${extension.toUpperCase()}: ${result.body.message}` });
            }
        }
        await wpRequest(actor, actorApi, route(character.id), { method: 'POST', body: { ids: accepted } });
        expect(await storedIds(page, api, character.id)).toEqual(accepted);
    });
});

test('server rejects disallowed files, disguised archives, MIME mismatches and oversized uploads', async ({ page, browser, baseURL, regressionProbes }) => {
    await withDocumentFixture({ page, browser, baseURL }, 'ligatura_player', async ({ api, actor, actorApi, character, suffix, track }) => {
        const files = documentFiles(suffix);
        const seed = await upload(actor, actorApi, character.id, files.pdf, track);
        expect(seed.ok).toBe(true);
        await wpRequest(actor, actorApi, route(character.id), { method: 'POST', body: { ids: [seed.body.id] } });
        const invalid = [files.png, files.zip, files.exe, files.html, files.svg,
            { ...files.png, name: `fixture-mismatch-${suffix}.pdf`, mimeType: 'application/pdf' },
            { ...files.html, name: `fixture-markup-${suffix}.txt`, mimeType: 'text/plain' },
            { ...files.zip, name: `fixture-archive-${suffix}.pdf`, mimeType: 'application/pdf' },
            { ...files.zip, name: `fixture-archive-${suffix}.docx`, mimeType: files.docx.mimeType },
            { ...files.zip, name: `fixture-archive-${suffix}.odt`, mimeType: files.odt.mimeType },
            { ...files.txt, name: `fixture-word-${suffix}.doc`, mimeType: 'application/msword' },
        ];
        for (const file of invalid) {
            rejected(await upload(actor, actorApi, character.id, file, track), `Server must reject ${file.name}`);
            expect(await storedIds(page, api, character.id)).toEqual([seed.body.id]);
        }
        const oversized = await upload(actor, actorApi, character.id, { ...files.txt, buffer: Buffer.alloc(2048, 'A') }, track, {
            headers: { 'X-MI-Fixture-Upload-Limit': '1024' },
        });
        rejected(oversized, 'Server upload limit must be enforced independently of the UI');
        expect(oversized.body.message).toMatch(/size|large|limit|exceed/i);
        expect(await storedIds(page, api, character.id)).toEqual([seed.body.id]);
        const documents = await openDocuments(actor, character.id);
        await documents.locator('[data-document-upload]').setInputFiles(files.png);
        await documents.getByRole('button', { name: 'Upload Document', exact: true }).click();
        await expect(documents.locator('[data-document-status]')).toHaveClass(/notice-error/);
        await expect(documents.locator('[data-document-status]')).toContainText(/document|format|allow|type/i);
        await expect(documents.locator('[data-document-id]')).toHaveCount(1);
        await documents.evaluate(control => { control.dataset.restNonce = 'invalid'; });
        await documents.locator('[data-document-upload]').setInputFiles(files.txt);
        await documents.getByRole('button', { name: 'Upload Document', exact: true }).click();
        await expect(documents.locator('[data-document-status]')).toContainText(/nonce|verif|authoriz|cookie/i);
        await expect(documents.locator('[data-document-status]')).toHaveClass(/notice-error/);
        expect(await storedIds(page, api, character.id)).toEqual([seed.body.id]);
    });
});

test('REST rejects missing nonces, invalid IDs, foreign media and unassigned Character access', async ({ page, browser, baseURL, regressionProbes }) => {
    await withDocumentFixture({ page, browser, baseURL }, 'ligatura_player', async ({ api, actor, actorApi, character, suffix, create, track }) => {
        const files = documentFiles(suffix);
        const own = await upload(actor, actorApi, character.id, files.pdf, track);
        const foreign = await upload(page, api, character.id, files.txt, track);
        expect(own.ok && foreign.ok).toBe(true);
        const image = await uploadFixtureImage(page, api); track('media', image.id);
        const unassigned = await create('ligatura_character');
        await wpRequest(actor, actorApi, route(character.id), { method: 'POST', body: { ids: [own.body.id] } });
        for (const nonce of [null, 'invalid']) {
            rejected(await rawJson(actor, actorApi, route(character.id), { method: 'POST', nonce, body: { ids: [] } }), 'Relationship writes need a valid REST nonce');
            rejected(await upload(actor, actorApi, character.id, files.txt, track, { nonce }), 'Uploads need a valid REST nonce');
            expect(await storedIds(page, api, character.id)).toEqual([own.body.id]);
        }
        for (const ids of [[foreign.body.id], [image.id], [character.id], [2147483647], [-own.body.id], [0], ['not-an-id'], [String(own.body.id)], [{ id: own.body.id }], null, '1,2', { id: own.body.id }, Array(51).fill(own.body.id)]) {
            rejected(await rawJson(actor, actorApi, route(character.id), { method: 'POST', body: { ids } }), 'Invalid or unauthorized IDs must not alter the list');
            expect(await storedIds(page, api, character.id)).toEqual([own.body.id]);
        }
        rejected(await rawJson(actor, actorApi, `${route(character.id)}?ids=${foreign.body.id}`), 'The media-picker validation route must enforce ownership');
        rejected(await rawJson(actor, actorApi, route(unassigned.id)), 'An unassigned Character must not expose its editor document list');
        rejected(await rawJson(actor, actorApi, route(unassigned.id), { method: 'POST', body: { ids: [own.body.id] } }), 'An unassigned Character must not be writable');
        rejected(await upload(actor, actorApi, unassigned.id, files.txt, track), 'An unassigned Character must not accept uploads');
        expect(await storedIds(page, api, unassigned.id)).toEqual([]);
        await wpRequest(page, api, route(character.id), { method: 'POST', body: { ids: [foreign.body.id, own.body.id] } });
        await wpRequest(actor, actorApi, route(character.id), { method: 'POST', body: { ids: [foreign.body.id, own.body.id] } });
        expect(await storedIds(page, api, character.id), 'Players can retain already assigned documents owned by someone else').toEqual([foreign.body.id, own.body.id]);
        await wpRequest(actor, actorApi, route(character.id), { method: 'POST', body: { ids: [own.body.id] } });
        expect(await storedIds(page, api, character.id)).toEqual([own.body.id]);
        expect((await wpRequest(page, api, `wp/v2/media/${foreign.body.id}`)).id).toBe(foreign.body.id);
    });
});

test('form and core REST boundaries preserve omitted lists and reject forged values', async ({ page, browser, baseURL, regressionProbes }) => {
    await withDocumentFixture({ page, browser, baseURL }, 'ligatura_player', async ({ api, actor, actorApi, character, suffix, track }) => {
        const files = documentFiles(suffix);
        const own = await upload(actor, actorApi, character.id, files.pdf, track);
        const foreign = await upload(page, api, character.id, files.txt, track);
        expect(own.ok && foreign.ok).toBe(true);
        await wpRequest(actor, actorApi, route(character.id), { method: 'POST', body: { ids: [own.body.id] } });
        await wpRequest(actor, actorApi, `wp/v2/ligatura_character/${character.id}`, { method: 'POST', body: { meta: { ligatura_occupation: 'Document omission preserved' } } });
        expect(await storedIds(page, api, character.id)).toEqual([own.body.id]);
        await wpRequest(actor, actorApi, `wp/v2/ligatura_character/${character.id}/autosaves`, { method: 'POST', body: { content: 'Disposable autosave content.' } });
        expect(await storedIds(page, api, character.id), 'A core autosave/revision with no document field must preserve relationships').toEqual([own.body.id]);
        await wpRequest(actor, actorApi, route(character.id), { method: 'POST', body: {} });
        expect(await storedIds(page, api, character.id), 'Omitting ids from the dedicated route must also preserve relationships').toEqual([own.body.id]);
        const exposed = await wpRequest(actor, actorApi, `wp/v2/ligatura_character/${character.id}?context=edit`);
        expect(exposed.meta).not.toHaveProperty(metaKey);
        for (const ids of [[], [foreign.body.id], [2147483647]]) {
            await wpRawRequest(actor, actorApi, `wp/v2/ligatura_character/${character.id}`, { method: 'POST', body: { meta: { [metaKey]: ids } } });
            expect(await storedIds(page, api, character.id), 'Core REST must not bypass document validation').toEqual([own.body.id]);
        }
        await openDocuments(actor, character.id);
        expect((await documentForm(actor, character, { ligatura_occupation: 'Partial form preserved' }, null)).ok()).toBe(true);
        expect(await storedIds(page, api, character.id)).toEqual([own.body.id]);
        for (const nonce of [false, null]) {
            const response = await documentForm(actor, character, { [metaKey]: '[]' }, nonce);
            expect(response.status(), 'Invalid and absent document nonces must visibly reject a supplied list').toBe(403);
            expect(await storedIds(page, api, character.id)).toEqual([own.body.id]);
        }
        for (const value of ['not-json', JSON.stringify([foreign.body.id]), JSON.stringify([2147483647]), JSON.stringify([-own.body.id])]) {
            const response = await documentForm(actor, character, { [metaKey]: value });
            expect(response.status(), 'Crafted form values must produce a rejection').toBeGreaterThanOrEqual(400);
            expect(response.status()).toBeLessThan(500);
            expect(await storedIds(page, api, character.id)).toEqual([own.body.id]);
        }
        expect((await documentForm(actor, character, { [metaKey]: '[]' })).ok()).toBe(true);
        expect(await storedIds(page, api, character.id), 'Explicit empty array intentionally unlinks').toEqual([]);
        expect((await wpRequest(page, api, `wp/v2/media/${own.body.id}`)).id).toBe(own.body.id);
    });
});

test('front-end validates stale attachments and distinguishes gated pages from directly accessible files', async ({ page, browser, baseURL, regressionProbes }, testInfo) => {
    await withDocumentFixture({ page, browser, baseURL }, 'ligatura_player', async ({ api, actor, actorApi, character, suffix, track }) => {
        const files = documentFiles(suffix);
        const uploaded = await upload(actor, actorApi, character.id, files.txt, track);
        expect(uploaded.ok).toBe(true);
        const document = uploaded.body;
        await wpRequest(actor, actorApi, route(character.id), { method: 'POST', body: { ids: [document.id] } });
        const anonymous = await browser.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
        try {
            const visitor = await anonymous.newPage();
            await visitor.goto(character.link);
            await expect(visitor).toHaveURL(/wp-login\.php/);
            await expect(visitor.getByRole('region', { name: 'Documents', exact: true })).toHaveCount(0);
            rejected(await rawJson(visitor, { root: api.root }, route(character.id), { nonce: null }), 'Anonymous document metadata requests must be denied');
            const direct = await anonymous.request.get(document.url);
            expect(direct.status(), 'Normal upload URLs are currently public; this test records that limitation explicitly').toBe(200);
            expect(await direct.body()).toEqual(files.txt.buffer);
            testInfo.annotations.push({ type: 'privacy-limitation', description: 'Anonymous direct upload URL returned the original file while the Character page redirected to login.' });
            await wpRequest(page, api, `wp/v2/ligatura_character/${character.id}`, { method: 'POST', body: { password: `fixture-${suffix}` } });
            await actor.goto(character.link);
            await expect(actor.locator('.post-password-form')).toBeVisible();
            await expect(actor.getByRole('region', { name: 'Documents', exact: true })).toHaveCount(0);
            await expect(actor.getByRole('region', { name: 'Full Sheet', exact: true })).toHaveCount(0);
            await wpRequest(page, api, `wp/v2/ligatura_character/${character.id}`, { method: 'POST', body: { password: '' } });
            await wpRequest(page, api, `wp/v2/media/${document.id}?force=true`, { method: 'DELETE' });
            await actor.goto(character.link);
            await expect(actor.getByRole('region', { name: 'Documents', exact: true })).toHaveCount(0);
            await expect(actor.locator(`a[href="${document.url}"]`)).toHaveCount(0);
            expect((await wpRequest(actor, actorApi, route(character.id))).documents).toEqual([]);
            const documents = await openDocuments(actor, character.id);
            await expect(documents.locator('[data-document-list]')).toContainText(/unavailable/i);
            await expect(documents.locator('[data-document-list] a')).toHaveCount(0);
            await documents.locator('[data-unlink-document]').click();
            await saveEditor(actor);
            expect(await storedIds(page, api, character.id)).toEqual([]);
        } finally {
            await anonymous.close();
        }
    });
});
