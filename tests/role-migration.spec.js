const { test, expect } = require('./support/runtime-probes');
const { getApiSettings, wpRequest } = require('./support/personae-fixture');
const { randomBytes } = require('node:crypto');

test('known legacy roles migrate without losing assignments, independent roles, or individual grants', async ({ page, regressionProbes }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Isolated migration fixtures run once.');
    test.setTimeout(120_000);
    const api = await getApiSettings(page);
    const inventory = await wpRequest(page, api, 'ligatura-manuscripti-illuminati/v1/roles/inventory');
    await testInfo.attach('role-inventory', { body: JSON.stringify(inventory, null, 2), contentType: 'application/json' });
    console.log('Role inventory:', inventory.roles.map(({ slug, user_count, multi_role_count }) => ({ slug, user_count, multi_role_count })));
    const result = await wpRequest(page, api, 'ligatura-manuscripti-illuminati/v1/roles/migration-probe', { method: 'POST' });
    await testInfo.attach('isolated-role-migration', { body: JSON.stringify(result, null, 2), contentType: 'application/json' });
    for (const scenario of ['clean', 'canonical', 'legacy']) {
        const fixture = result[scenario];
        expect(fixture.roles.ligatura_player.name).toBe('Player');
        expect(fixture.roles.ligatura_storyguide.name).toBe('Storyguide');
        expect(fixture.roles.subscriber).toEqual({ name: 'Subscriber', capabilities: { read: true } });
        expect(fixture.roles).not.toHaveProperty('ars_player');
        expect(fixture.roles).not.toHaveProperty('ars_storyguide');
        expect(fixture.version_recorded).toBe(true);
        expect(fixture.repeat_unchanged).toBe(true);
        expect(fixture.repeat_option_writes).toBe(0);
        expect(fixture.assignment_preserved).toBe(true);
        expect(fixture.journal_owner_preserved).toBe(true);
    }
    expect(result.canonical.roles.ligatura_player.capabilities.fixture_canonical_cap).toBe(true);
    expect(result.clean.default_role).toBe('subscriber');
    expect(result.canonical.default_role).toBe('subscriber');
    const legacy = result.legacy;
    expect(legacy.roles.third_party_player).toEqual({ name: 'Player', capabilities: { read: true } });
    expect(legacy.default_role).toBe('ligatura_player');
    expect(legacy.current_roles).toContain('ligatura_player');
    expect(legacy.current_roles).not.toContain('ars_player');
    expect(legacy.access_before).toEqual({ assigned_persona: false, own_journal: false });
    expect(legacy.access_after).toEqual({ assigned_persona: true, own_journal: true });
    expect(legacy.read_before).toEqual([true, true, true, true, true, false]);
    for (const [index, user] of legacy.users.entries()) {
        expect(user.roles).toEqual(expect.arrayContaining(['fixture_other', index === 1 ? 'ligatura_storyguide' : 'ligatura_player']));
        expect(user.roles).not.toContain('ars_player');
        expect(user.roles).not.toContain('ars_storyguide');
        expect(user.direct.fixture_direct_grant).toBe(true);
        expect(user.direct.fixture_denied_cap).toBe(false);
        expect(user.allcaps.fixture_other_cap).toBe(true);
        expect(user.allcaps.read).toBe(index !== 5);
        if (index === 5) expect(user.direct.read).toBe(false);
        if (index !== 1) expect(user.allcaps.fixture_overlap_cap).toBe(index !== 2);
        if (index === 3) {
            expect(user.roles).toContain('ligatura_storyguide');
            expect(user.allcaps.ligatura_manage_storyguide_notes).toBe(true);
        } else if (index !== 1) {
            expect(user.allcaps.fixture_legacy_cap).toBe(index !== 2);
            expect(user.allcaps.fixture_role_denied).toBe(false);
            expect(user.allcaps.ligatura_edit_assigned_personae).toBe(true);
            expect(user.allcaps.create_ligatura_diary_entries).toBe(true);
            expect(user.allcaps.ligatura_manage_storyguide_notes).not.toBe(true);
            expect(user.allcaps.manage_options).not.toBe(true);
        } else {
            expect(user.allcaps.ligatura_manage_storyguide_notes).toBe(true);
        }
    }
    expect(legacy.roles.ligatura_player.capabilities).not.toHaveProperty('fixture_legacy_cap');
    expect(result.failed_write.version_recorded).toBe(false);
    expect(result.failed_write.roles).toHaveProperty('ars_player');
    expect(result.failed_write.users[0].roles).toContain('ars_player');
    expect(result.failed_write.users[0].direct.fixture_direct_grant).toBe(true);
    expect(result.failed_write.assignment_preserved).toBe(true);
    expect(result.failed_write.journal_owner_preserved).toBe(true);
});

test('installed role selector and normal profile updates use the canonical roles', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Disposable profile update runs once.');
    test.setTimeout(90_000);
    const api = await getApiSettings(page);
    const administrator = await wpRequest(page, api, 'wp/v2/users/me?context=edit');
    const suffix = randomBytes(8).toString('hex');
    let userId;
    try {
        const user = await wpRequest(page, api, 'wp/v2/users', {
            method: 'POST',
            body: {
                username: `fixture_roles_${suffix}`,
                password: randomBytes(48).toString('base64url'),
                email: `fixture_roles_${suffix}@example.invalid`,
                roles: ['ligatura_player'],
            },
        });
        userId = user.id;
        await page.goto(`/wp-admin/user-edit.php?user_id=${userId}`, { waitUntil: 'domcontentloaded' });
        const role = page.locator('#role');
        await expect(role).toHaveValue('ligatura_player');
        await expect(role.getByRole('option', { name: 'Player', exact: true })).toHaveCount(1);
        await expect(role.getByRole('option', { name: 'Storyguide', exact: true })).toHaveCount(1);
        await expect(role.locator('option[value="ars_player"], option[value="ars_storyguide"]')).toHaveCount(0);
        await page.locator('#first_name').fill('Role regression fixture');
        await page.locator('#submit').click();
        await expect(page.locator('#message')).toContainText('User updated.');
        await expect(page.locator('#first_name')).toHaveValue('Role regression fixture');
        await expect(page.locator('#role')).toHaveValue('ligatura_player');

        for (const slug of ['ligatura_storyguide', 'ligatura_player']) {
            const updated = await wpRequest(page, api, `wp/v2/users/${userId}`, { method: 'POST', body: { roles: [slug] } });
            expect(updated.roles).toEqual([slug]);
            await page.reload({ waitUntil: 'domcontentloaded' });
            await expect(page.locator('#role')).toHaveValue(slug);
        }
    } finally {
        if (userId) {
            await wpRequest(page, api, `wp/v2/users/${userId}?force=true&reassign=${administrator.id}`, { method: 'DELETE' });
        }
    }
});
