const { randomBytes } = require('node:crypto');
const { expect } = require('@playwright/test');
const { getApiSettings, wpRequest, wpRawRequest } = require('./personae-fixture');

// Credentials stay in memory; callers disable traces for these disposable accounts.
async function withDocumentFixture({ page, browser, baseURL }, role, run) {
    const api = await getApiSettings(page);
    const admin = await wpRequest(page, api, 'wp/v2/users/me');
    const suffix = randomBytes(8).toString('hex');
    const records = [];
    let user, context;
    const track = (type, id) => {
        if (id && !records.some(record => record.type === type && record.id === id)) records.push({ type, id });
    };
    const create = async (type, values = {}) => {
        const post = await wpRequest(page, api, `wp/v2/${type}`, { method: 'POST', body: {
            title: `Fixture documents ${suffix} ${records.length}`, status: 'draft', ...values,
        } });
        track(type, post.id);
        return post;
    };
    try {
        const username = `fixture_documents_${suffix}`;
        const password = randomBytes(48).toString('base64url');
        user = await wpRequest(page, api, 'wp/v2/users', { method: 'POST', body: {
            username, password, email: `${username}@example.invalid`, roles: [role],
        } });
        const types = await wpRequest(page, api, 'wp/v2/ligatura_character_type?per_page=100');
        expect(types.length).toBeGreaterThan(0);
        const character = await create('ligatura_character', {
            status: 'publish', ligatura_character_type: [types[0].id],
            meta: { _ligatura_assigned_player: user.id, ligatura_full_sheet: '<p>Fixture full sheet.</p>' },
        });
        context = await browser.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
        const actor = await context.newPage();
        await actor.goto('/wp-login.php');
        await actor.locator('#user_login').fill(username);
        await actor.locator('#user_pass').fill(password);
        await Promise.all([
            actor.waitForURL(/\/wp-admin\//, { waitUntil: 'domcontentloaded' }),
            actor.locator('#wp-submit').click(),
        ]);
        await actor.goto(`/wp-admin/post.php?post=${character.id}&action=edit`);
        const actorApi = await actor.evaluate(() => ({ root: wpApiSettings.root, nonce: wpApiSettings.nonce }));
        await run({ api, admin, actor, actorApi, character, user, suffix, create, track });
    } finally {
        await context?.close();
        const failures = [];
        for (const record of records.reverse()) {
            try {
                const result = await wpRawRequest(page, api, `wp/v2/${record.type}/${record.id}?force=true`, { method: 'DELETE' });
                if (!result.ok && result.status !== 404) failures.push(`${record.type} cleanup HTTP ${result.status}`);
            } catch { failures.push('Fixture cleanup request failed'); }
        }
        if (user) {
            const result = await wpRawRequest(page, api, `wp/v2/users/${user.id}?force=true&reassign=${admin.id}`, { method: 'DELETE' });
            if (!result.ok) failures.push(`User cleanup HTTP ${result.status}`);
        }
        expect(failures, 'Disposable document fixtures must be removed').toEqual([]);
    }
}

module.exports = { withDocumentFixture };
