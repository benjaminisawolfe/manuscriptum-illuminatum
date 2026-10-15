const { randomBytes } = require('node:crypto');
const { test, expect } = require('./support/runtime-probes');
const { getApiSettings, wpRequest, wpRawRequest } = require('./support/personae-fixture');

const assignmentKey = '_ligatura_assigned_player';
const metaNonceKey = 'ligatura_manuscripti_illuminati_meta_nonce';
const privateNotesKey = 'ligatura_storyguide_notes';

// These tests enter generated credentials and inspect authenticated forms.
test.use({ actionTimeout: 15_000, navigationTimeout: 15_000, trace: 'off' });
test.beforeEach(async ({}, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Disposable security fixtures run once.');
    test.setTimeout(120_000);
});

async function withPlayer({ page, browser, baseURL }, run) {
    const adminApi = await getApiSettings(page);
    const admin = await wpRequest(page, adminApi, 'wp/v2/users/me');
    const suffix = randomBytes(8).toString('hex');
    const username = `fixture_boundary_${suffix}`;
    const password = randomBytes(48).toString('base64url');
    const created = [];
    let user;
    let context;
    try {
        user = await wpRequest(page, adminApi, 'wp/v2/users', { method: 'POST', body: {
            username, password, email: `${username}@example.invalid`, roles: ['ligatura_player'],
        } });
        context = await browser.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
        const playerPage = await context.newPage();
        await playerPage.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
        await playerPage.locator('#user_login').fill(username);
        await playerPage.locator('#user_pass').fill(password);
        await Promise.all([
            playerPage.waitForURL(/\/wp-admin\//, { waitUntil: 'domcontentloaded', timeout: 15_000 }),
            playerPage.locator('#wp-submit').click(),
        ]);
        const trackPost = (type, id) => {
            if (Number.isInteger(id) && id > 0 && !created.some(post => post.type === type && post.id === id)) created.push({ type, id });
        };
        const create = async (type, values = {}) => {
            const post = await wpRequest(page, adminApi, `wp/v2/${type}`, { method: 'POST', body: {
                title: `Fixture boundary ${suffix} ${created.length}`, status: 'draft', ...values,
            } });
            trackPost(type, post.id);
            return post;
        };
        const bootstrap = await create('ligatura_character', { meta: { [assignmentKey]: user.id } });
        await formCredentials(playerPage, bootstrap);
        const playerApi = await playerPage.evaluate(() => ({
            root: window.wpApiSettings?.root || '', nonce: window.wpApiSettings?.nonce || '',
        }));
        expect(Boolean(playerApi.root && playerApi.nonce), 'Assigned editor must expose authenticated REST settings').toBe(true);
        const read = (type, id) => wpRequest(page, adminApi, `wp/v2/${type}/${id}?context=edit`);
        const stored = id => wpRequest(page, adminApi, `ligatura-manuscripti-illuminati/v1/meta-saving/stored?post_id=${id}`);
        await run({ user, admin, suffix, bootstrap, playerPage, playerApi, create, trackPost, read, stored });
    } finally {
        await context?.close().catch(() => {});
        const failures = [];
        for (const post of created.reverse()) {
            try {
                const result = await wpRawRequest(page, adminApi, `wp/v2/${post.type}/${post.id}?force=true`, { method: 'DELETE' });
                if (!result.ok) failures.push(`post cleanup HTTP ${result.status}`);
            } catch { failures.push('post cleanup request failed'); }
        }
        if (user) {
            try {
                const result = await wpRawRequest(page, adminApi, `wp/v2/users/${user.id}?force=true&reassign=${admin.id}`, { method: 'DELETE' });
                if (!result.ok) failures.push(`user cleanup HTTP ${result.status}`);
            } catch { failures.push('user cleanup request failed'); }
        }
        expect(failures, 'All disposable records and the generated account must be removed').toEqual([]);
    }
}

async function formCredentials(page, post) {
    const id = post.id;
    const response = await page.goto(`/wp-admin/post.php?post=${id}&action=edit`, { waitUntil: 'domcontentloaded' });
    expect(response.status(), 'Assigned editor screen must be accessible').toBe(200);
    const takeover = page.locator('a[href*="get-post-lock=1"]');
    if (await takeover.count()) {
        await page.goto(await takeover.first().getAttribute('href'), { waitUntil: 'domcontentloaded' });
    }
    // Welcome dialogs do not prevent attached hidden form fields being read.
    await expect(page.locator(`[name="${metaNonceKey}"]`)).toBeAttached();
    return {
        _wpnonce: await page.locator('#_wpnonce').inputValue(),
        [metaNonceKey]: await page.locator(`[name="${metaNonceKey}"]`).inputValue(),
        post_type: post.type,
        post_author: String(post.author),
    };
}

async function postForm(page, id, credentials, fields) {
    // Preserve the real editor's query ID for the Player admin routing guard.
    return page.request.post(`/wp-admin/post.php?post=${id}`, { form: {
        action: 'editpost', post_ID: String(id), ...credentials, ...fields,
    }, timeout: 15_000 });
}

test('sole-assigned Player can open New Journal and create a REST Journal with the default Character Author', async ({ page, browser, baseURL }) => {
    await withPlayer({ page, browser, baseURL }, async ({ user, suffix, bootstrap, playerPage, playerApi, trackPost, read }) => {
        // withPlayer's bootstrap is deliberately the sole assigned Character.
        const response = await playerPage.goto('/wp-admin/post-new.php?post_type=ligatura_diary', { waitUntil: 'domcontentloaded' });
        const idField = playerPage.locator('#post_ID');
        if (await idField.count()) trackPost('ligatura_diary', Number(await idField.inputValue()));
        expect(response.status(), 'New Journal must not reject a server-derived default as an unverified form write').toBe(200);
        await expect(idField).toBeAttached();
        const autoDraftId = Number(await idField.inputValue());
        trackPost('ligatura_diary', autoDraftId);
        await expect(playerPage.locator('select[name="ligatura_diary_character"]')).toHaveValue(String(bootstrap.id));
        const autoDraft = await read('ligatura_diary', autoDraftId);
        expect(autoDraft.author).toBe(user.id);
        expect(autoDraft.status).toBe('auto-draft');

        const created = await wpRawRequest(playerPage, playerApi, 'wp/v2/ligatura_diary', {
            method: 'POST', body: { title: `Fixture default Journal ${suffix}`, status: 'draft' },
        });
        trackPost('ligatura_diary', created.body?.id);
        expect(created.status, 'REST creation validates registered metadata without requiring a classic form nonce').toBe(201);
        const saved = await read('ligatura_diary', created.body.id);
        expect(saved.author).toBe(user.id);
        expect(saved.status).toBe('draft');
        expect(saved.meta.ligatura_diary_character).toBe(bootstrap.id);
    });
});

test('assigned Player saves metadata on draft, pending, private, and scheduled Characters', async ({ page, browser, baseURL }) => {
    await withPlayer({ page, browser, baseURL }, async ({ user, playerPage, playerApi, create, read }) => {
        const adminApi = await getApiSettings(page);
        const types = await wpRequest(page, adminApi, 'wp/v2/ligatura_character_type?per_page=100');
        expect(types.length > 0, 'The staging campaign must have a Character Type').toBe(true);
        for (const status of ['draft', 'pending', 'private', 'future']) {
            const post = await create('ligatura_character', {
                status, ...(status === 'future' ? { date: '2099-01-01T12:00:00' } : {}),
                ligatura_character_type: [types[0].id],
                meta: { [assignmentKey]: user.id, ligatura_occupation: 'Original', ligatura_notes_public: '<p>Keep.</p>' },
            });
            const updated = await wpRawRequest(playerPage, playerApi, `wp/v2/ligatura_character/${post.id}`, {
                method: 'POST', body: { meta: { ligatura_occupation: `REST ${status}` } },
            });
            expect(updated.status, `Assigned ${status} Character REST update`).toBe(200);
            let saved = await read('ligatura_character', post.id);
            expect(saved.meta.ligatura_occupation).toBe(`REST ${status}`);
            const credentials = await formCredentials(playerPage, post);
            const result = await postForm(playerPage, post.id, credentials, { ligatura_occupation: `Form ${status}` });
            expect(result.ok(), `Assigned ${status} Character form update`).toBe(true);
            saved = await read('ligatura_character', post.id);
            expect(saved.meta.ligatura_occupation).toBe(`Form ${status}`);
            expect(saved.meta.ligatura_notes_public).toBe('<p>Keep.</p>');
            expect(saved.meta[assignmentKey]).toBe(user.id);
            expect(saved.status).toBe(status);
            expect(saved.author).toBe(post.author);
        }
    });
});

test('Player own Journal metadata persists through REST and a partial classic form', async ({ page, browser, baseURL }) => {
    await withPlayer({ page, browser, baseURL }, async ({ user, playerPage, playerApi, create, read }) => {
        const character = await create('ligatura_character', { meta: { [assignmentKey]: user.id } });
        const journal = await create('ligatura_diary', { author: user.id, meta: {
            ligatura_diary_character: character.id, ligatura_diary_session_date: '2026-01-02', ligatura_diary_saga_date: '1207-01-02',
        } });
        const update = await wpRawRequest(playerPage, playerApi, `wp/v2/ligatura_diary/${journal.id}`, {
            method: 'POST', body: { meta: { ligatura_diary_session_date: '2026-02-03' } },
        });
        expect(update.status).toBe(200);
        expect((await read('ligatura_diary', journal.id)).meta.ligatura_diary_session_date).toBe('2026-02-03');
        const credentials = await formCredentials(playerPage, journal);
        expect((await postForm(playerPage, journal.id, credentials, { ligatura_diary_session_date: '2026-03-04' })).ok()).toBe(true);
        const saved = await read('ligatura_diary', journal.id);
        expect(saved.meta.ligatura_diary_session_date).toBe('2026-03-04');
        expect(saved.meta.ligatura_diary_saga_date).toBe('1207-01-02');
        expect(saved.meta.ligatura_diary_character).toBe(character.id);
    });
});

test('Player unassigned Character UI and direct REST writes are denied without changes', async ({ page, browser, baseURL }) => {
    await withPlayer({ page, browser, baseURL }, async ({ admin, playerPage, playerApi, create, read }) => {
        const post = await create('ligatura_character', { meta: { [assignmentKey]: admin.id, ligatura_occupation: 'Preserved' } });
        const response = await playerPage.goto(`/wp-admin/post.php?post=${post.id}&action=edit`, { waitUntil: 'domcontentloaded' });
        expect(response.status()).toBe(403);
        const update = await wpRawRequest(playerPage, playerApi, `wp/v2/ligatura_character/${post.id}`, {
            method: 'POST', body: { meta: { ligatura_occupation: 'Forged' } },
        });
        expect(update.status).toBe(403);
        const saved = await read('ligatura_character', post.id);
        expect(saved.meta.ligatura_occupation).toBe('Preserved');
        expect(saved.meta[assignmentKey]).toBe(admin.id);
    });
});

test('Player forged assignment REST and classic form writes return a rejection and preserve assignment', async ({ page, browser, baseURL }) => {
    await withPlayer({ page, browser, baseURL }, async ({ user, admin, playerPage, playerApi, create, read }) => {
        const post = await create('ligatura_character', { meta: { [assignmentKey]: user.id, ligatura_occupation: 'Preserved' } });
        const credentials = await formCredentials(playerPage, post);
        const rest = await wpRawRequest(playerPage, playerApi, `wp/v2/ligatura_character/${post.id}`, {
            method: 'POST', body: { meta: { [assignmentKey]: admin.id } },
        });
        expect(rest.status).toBe(403);
        expect((await read('ligatura_character', post.id)).meta[assignmentKey]).toBe(user.id);
        const classic = await postForm(playerPage, post.id, credentials, { [assignmentKey]: String(admin.id) });
        expect(classic.status(), 'Forbidden fields must produce a visible rejection, not apparent save success').toBe(403);
        const saved = await read('ligatura_character', post.id);
        expect(saved.meta[assignmentKey]).toBe(user.id);
        expect(saved.meta.ligatura_occupation).toBe('Preserved');
        const withoutMetaNonces = { ...credentials };
        delete withoutMetaNonces[metaNonceKey];
        const noNonce = await postForm(playerPage, post.id, withoutMetaNonces, { [assignmentKey]: String(admin.id) });
        expect(noNonce.status(), 'Omitting both campaign nonces must not turn assignment forgery into apparent success').toBe(403);
        expect((await read('ligatura_character', post.id)).meta[assignmentKey]).toBe(user.id);
    });
});

test('assigned Player forms preserve publication dates and reject date-derived status or visibility changes', async ({ page, browser, baseURL }) => {
    await withPlayer({ page, browser, baseURL }, async ({ user, playerPage, create, read }) => {
        const adminApi = await getApiSettings(page);
        const types = await wpRequest(page, adminApi, 'wp/v2/ligatura_character_type?per_page=100');
        expect(types.length > 0).toBe(true);
        for (const status of ['publish', 'future']) {
            const date = status === 'publish' ? '2001-01-02T12:03:04' : '2099-01-02T12:03:04';
            const post = await create('ligatura_character', {
                status, date, ligatura_character_type: [types[0].id],
                meta: { [assignmentKey]: user.id, ligatura_occupation: 'Original' },
            });
            const [aa, mm, jj, hh, mn, ss] = post.date.split(/[-T:]/);
            const timestamp = { aa, mm, jj, hh, mn, ss };
            const hiddenTimestamp = Object.fromEntries(Object.entries(timestamp).map(([key, value]) => [`hidden_${key}`, value]));
            const credentials = await formCredentials(playerPage, post);
            const unchanged = await postForm(playerPage, post.id, credentials, {
                ...timestamp, ...hiddenTimestamp, edit_date: '1',
                post_date: post.date.replace('T', ' '), post_date_gmt: post.date_gmt.replace('T', ' '),
                post_status: status, visibility: 'public', post_password: '', ligatura_occupation: 'Safe update',
            });
            expect(unchanged.ok(), 'Unchanged hidden editor timestamps must remain valid').toBe(true);
            const otherDate = status === 'publish' ? '2099-01-02 12:03:04' : '2001-01-02 12:03:04';
            for (const trigger of [
                { edit_date: '1' },
                { hidden_aa: otherDate.slice(0, 4) },
                { mm, jj, hh, mn, ss, hidden_aa: otherDate.slice(0, 4) },
            ]) {
                const fresh = await formCredentials(playerPage, post);
                const normalized = await postForm(playerPage, post.id, fresh, { post_status: status, ...trigger });
                expect(normalized.ok(), 'Date-only triggers with omitted components are normalized safely').toBe(true);
                const saved = await read('ligatura_character', post.id);
                expect(saved.status).toBe(status);
                expect(saved.date).toBe(post.date);
                expect(saved.date_gmt).toBe(post.date_gmt);
                expect(saved.meta.ligatura_occupation).toBe('Safe update');
            }
            for (const fields of [
                { post_date: otherDate }, { post_date_gmt: otherDate },
                { ...timestamp, ...hiddenTimestamp, aa: otherDate.slice(0, 4), edit_date: '1' },
                { ...timestamp, ...hiddenTimestamp, aa: otherDate.slice(0, 4) },
                { visibility: 'private' }, { post_password: 'forged-visibility' },
            ]) {
                const fresh = await formCredentials(playerPage, post);
                const forged = await postForm(playerPage, post.id, fresh, { post_status: status, ...fields, ligatura_occupation: 'Forged' });
                expect(forged.status(), 'Changing dates or visibility must return a rejection').toBe(403);
                const saved = await read('ligatura_character', post.id);
                expect(saved.status).toBe(status);
                expect(saved.date).toBe(post.date);
                expect(saved.date_gmt).toBe(post.date_gmt);
                expect(saved.author).toBe(post.author);
                expect(saved.password).toBe('');
                expect(saved.meta.ligatura_occupation).toBe('Safe update');
            }
        }
    });
});

test('assigned Character forms preserve omitted authors and reject forged author, type, status, and nonce', async ({ page, browser, baseURL }) => {
    await withPlayer({ page, browser, baseURL }, async ({ user, playerPage, create, read }) => {
        const post = await create('ligatura_character', { meta: { [assignmentKey]: user.id, ligatura_occupation: 'Original' } });
        const credentials = await formCredentials(playerPage, post);
        const withoutAuthor = { ...credentials };
        delete withoutAuthor.post_author;
        expect((await postForm(playerPage, post.id, withoutAuthor, { ligatura_occupation: 'Author preserved' })).ok()).toBe(true);
        let saved = await read('ligatura_character', post.id);
        expect(saved.author, 'Omitting an author must not transfer technical ownership to the Player').toBe(post.author);
        expect(saved.meta.ligatura_occupation).toBe('Author preserved');
        for (const [field, value] of [
            ['post_author', String(user.id)], ['post_author_override', String(user.id)],
            ['post_type', 'post'], ['post_status', 'pending'], ['_wpnonce', 'invalid'],
        ]) {
            const fresh = await formCredentials(playerPage, post);
            const forged = await postForm(playerPage, post.id, fresh, { [field]: value, ligatura_occupation: 'Forged' });
            expect(forged.status(), `${field} changes require an explicit rejection`).toBe(403);
            saved = await read('ligatura_character', post.id);
            expect(saved.author).toBe(post.author);
            expect(saved.type).toBe('ligatura_character');
            expect(saved.status).toBe('draft');
            expect(saved.meta.ligatura_occupation).toBe('Author preserved');
        }
    });
});

test('Player forged private Wiki notes are rejected through REST and classic form', async ({ page, browser, baseURL, regressionProbes }) => {
    await withPlayer({ page, browser, baseURL }, async ({ user, playerPage, playerApi, create, stored }) => {
        const character = await create('ligatura_character', { meta: { [assignmentKey]: user.id } });
        const wiki = await create('ligatura_wiki');
        const adminCredentials = await formCredentials(page, wiki);
        expect((await postForm(page, wiki.id, adminCredentials, { [privateNotesKey]: '<p>Private original.</p>' })).ok()).toBe(true);
        const playerCredentials = await formCredentials(playerPage, character);
        const rest = await wpRawRequest(playerPage, playerApi, `wp/v2/ligatura_wiki/${wiki.id}`, {
            method: 'POST', body: { meta: { [privateNotesKey]: '<p>Forged.</p>' } },
        });
        expect(rest.status).toBe(403);
        expect((await stored(wiki.id)).meta[privateNotesKey]).toBe('<p>Private original.</p>');
        const classic = await postForm(playerPage, wiki.id, playerCredentials, { [privateNotesKey]: '<p>Forged.</p>' });
        expect(classic.status()).toBe(403);
        expect((await stored(wiki.id)).meta[privateNotesKey]).toBe('<p>Private original.</p>');
    });
});

test('invalid Saga Date form input preserves the stored date and provides feedback', async ({ page, browser, baseURL, regressionProbes }) => {
    await withPlayer({ page, browser, baseURL }, async ({ user, playerPage, create, stored }) => {
        const character = await create('ligatura_character', { meta: { [assignmentKey]: user.id } });
        const journal = await create('ligatura_diary', { author: user.id, meta: {
            ligatura_diary_character: character.id, ligatura_diary_saga_date: '1207-01-02',
        } });
        const credentials = await formCredentials(playerPage, journal);
        const response = await postForm(playerPage, journal.id, credentials, { ligatura_diary_saga_date: '1207-99-99' });
        expect((await stored(journal.id)).meta.ligatura_diary_saga_date).toBe('1207-01-02');
        // Either a request error or a redirect-rendered editor notice communicates
        // rejection; a plain successful save with no explanation does neither.
        if (response.status() >= 400) {
            expect(await response.text()).toMatch(/saga date/i);
        } else {
            await playerPage.goto(response.url(), { waitUntil: 'domcontentloaded' });
            await expect(playerPage.locator('.notice-error, .notice-warning')).toContainText(/saga date/i);
        }
    });
});
