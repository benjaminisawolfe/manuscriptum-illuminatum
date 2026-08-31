const { test, expect } = require('@playwright/test');
const { randomBytes } = require('node:crypto');
const { getApiSettings, wpRawRequest, wpRequest } = require('./support/personae-fixture');

const assignmentMeta = '_ligatura_assigned_player';

function strongRandomPassword() {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()-_=+[]{};:,.?';

    return Array.from(randomBytes(96), (byte) => alphabet[byte % alphabet.length]).join('');
}

async function createPlayer(page, api, suffix, label) {
    const username = `fixture_player_${suffix}`.toLowerCase();
    const password = strongRandomPassword();
    const nickname = `Player ${label} Nickname ${suffix}`;
    const displayName = `Technical Account ${label} ${suffix}`;
    const result = await wpRawRequest(page, api, 'wp/v2/users', {
        method: 'POST',
        body: {
            username,
            password,
            email: `${username}@example.invalid`,
            nickname,
            name: displayName,
            first_name: 'Technical',
            last_name: `Account ${label}`,
            roles: ['ligatura_player'],
        },
    });

    expect(result.ok, `Player ${label} creation returned ${result.status}: ${result.body?.message || result.body?.code || 'unknown error'}`).toBe(true);
    const user = result.body;

    return { ...user, username, password, nickname, displayName };
}

async function loginAsPlayer(browser, player, baseURL) {
    const context = await browser.newContext({ baseURL });
    const page = await context.newPage();

    await page.goto('/wp-login.php', { waitUntil: 'load' });
    const username = page.locator('#user_login');
    const password = page.locator('#user_pass');
    await username.fill(player.username);
    await password.fill(player.password);
    await expect(username).toHaveValue(player.username);
    await expect(password).toHaveValue(player.password);
    const authenticatedNavigation = page.waitForURL(/\/wp-admin\//, { timeout: 15_000, waitUntil: 'domcontentloaded' });
    await page.locator('#loginform').evaluate((form, credentials) => {
        form.elements.log.value = credentials.username;
        form.elements.pwd.value = credentials.password;
        form.requestSubmit();
    }, { username: player.username, password: player.password });
    await Promise.race([
        authenticatedNavigation,
        page.locator('#login_error').waitFor({ state: 'visible', timeout: 15_000 }),
    ]);

    const loginError = /wp-login\.php/.test(page.url())
        ? (await page.locator('#login_error').textContent())?.replace(/\s+/g, ' ').trim() || 'Unknown WordPress login error'
        : '';

    if (loginError) {
        await context.close();
        throw new Error(`Player login failed: ${loginError}`);
    }

    await page.goto('/wp-admin/', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/\/wp-admin\/edit\.php\?post_type=ligatura_diary/);

    return { context, page };
}

async function getCurrentApiSettings(page) {
    const api = await page.evaluate(() => {
        const settings = window.wpApiSettings || {};
        const apiLink = document.querySelector('link[rel="https://api.w.org/"]');

        return {
            nonce: settings.nonce || '',
            root: settings.root || (apiLink ? apiLink.href : ''),
        };
    });

    expect(api.nonce).toBeTruthy();
    expect(api.root).toBeTruthy();

    return api;
}

async function takeOverPostLock(page) {
    const takeOver = page.locator('a[href*="get-post-lock=1"]');
    await takeOver.first().waitFor({ state: 'attached', timeout: 2_000 }).catch(() => {});

    if (await takeOver.count()) {
        await takeOver.first().click({ force: true });
        await page.waitForLoadState('domcontentloaded');
    }
}

async function openMetaBoxes(page, fieldSelector) {
    const field = page.locator(fieldSelector);

    if (!(await field.isVisible())) {
        const toggle = page.locator('.edit-post-meta-boxes-main__presenter > button[aria-expanded]');
        await toggle.waitFor({ state: 'attached', timeout: 5_000 });
        if (await toggle.getAttribute('aria-expanded') === 'false') {
            await toggle.evaluate((button) => button.click());
        }
    }

    await expect(field).toBeVisible();
    return field;
}

async function uploadPlayerImage(page, api, suffix) {
    const result = await page.evaluate(async ({ root, nonce, filename }) => {
        const canvas = document.createElement('canvas');
        canvas.width = 520;
        canvas.height = 720;
        const context = canvas.getContext('2d');
        context.fillStyle = '#f3e4bf';
        context.fillRect(0, 0, 520, 720);
        context.fillStyle = '#8f1f18';
        context.fillRect(70, 70, 380, 580);
        context.fillStyle = '#d6a21c';
        context.beginPath();
        context.arc(260, 280, 110, 0, Math.PI * 2);
        context.fill();

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        const response = await fetch(new URL('wp/v2/media', root), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Disposition': `attachment; filename="${filename}"`,
                'Content-Type': 'image/png',
                'X-WP-Nonce': nonce,
            },
            body: await blob.arrayBuffer(),
        });

        return { ok: response.ok, status: response.status, body: await response.json() };
    }, { ...api, filename: `fixture-player-${suffix}.png` });

    expect(result.ok, `Player media upload returned ${result.status}`).toBe(true);
    return result.body;
}

async function expectAdminDenied(page, path) {
    const response = await page.request.get(path);
    expect(response.status(), `${path} should return HTTP 403`).toBe(403);
}

async function deleteFixture(page, api, path) {
    const response = await page.request.delete(new URL(path, api.root).toString(), {
        headers: { 'X-WP-Nonce': api.nonce },
    });
    const body = await response.json().catch(() => ({}));

    return { ok: response.ok(), status: response.status(), body };
}

test('Player assignment, least-privilege admin, media, Journal authorship, and reassignment are enforced', async ({ page, browser, baseURL }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Security mutations and separate Player sessions are exercised once.');
    test.setTimeout(360_000);

    const api = await getApiSettings(page);
    const suffix = Date.now();
    const created = { users: [], characters: [], journals: [], media: [] };
    let playerAContext;
    let playerBContext;
    let defaultAdminId = 0;

    try {
        const status = await wpRequest(page, api, 'ligatura-manuscripti-illuminati/v1/player-access/status');
        const expectedCaps = [
            'read',
            'upload_files',
            'ligatura_assign_campaign_terms',
            'ligatura_edit_assigned_personae',
            'edit_ligatura_characters',
            'read_ligatura_diary_entry',
            'edit_ligatura_diary_entry',
            'delete_ligatura_diary_entry',
            'edit_ligatura_diary_entries',
            'create_ligatura_diary_entries',
            'publish_ligatura_diary_entries',
            'delete_ligatura_diary_entries',
            'edit_published_ligatura_diary_entries',
            'delete_published_ligatura_diary_entries',
        ].sort();

        expect(status.role).toBe('ligatura_player');
        expect(status.role_name).toBe('Player');
        expect([...status.capabilities].sort()).toEqual(expect.arrayContaining(expectedCaps));
        expect(status.default_admin).toBeGreaterThan(0);
        defaultAdminId = status.default_admin;

        const [playerA, playerB] = await Promise.all([
            createPlayer(page, api, `${suffix}a`, 'A'),
            createPlayer(page, api, `${suffix}b`, 'B'),
        ]);
        created.users.push(playerA.id, playerB.id);

        const [types, topics, wikiEntries, adminMedia] = await Promise.all([
            wpRequest(page, api, 'wp/v2/ligatura_character_type?per_page=100&hide_empty=false'),
            wpRequest(page, api, 'wp/v2/ligatura_saga_topic?per_page=100&hide_empty=false'),
            wpRequest(page, api, 'wp/v2/ligatura_wiki?per_page=1&context=edit'),
            wpRequest(page, api, 'wp/v2/media?per_page=1&context=edit'),
        ]);
        const characterType = types.find((term) => term.slug === 'companion') || types[0];
        const sagaTopic = topics.find((term) => term.slug === 'covenant-affairs') || topics[0];
        const adminMediaId = adminMedia[0]?.id || 0;

        expect(characterType).toBeTruthy();
        expect(sagaTopic).toBeTruthy();
        expect(wikiEntries[0]).toBeTruthy();

        const characterA = await wpRequest(page, api, 'wp/v2/ligatura_character', {
            method: 'POST',
            body: {
                title: `Fixture Persona A ${suffix}`,
                content: '<p>Assigned Persona A fixture.</p>',
                status: 'publish',
                ligatura_character_type: [characterType.id],
                meta: { [assignmentMeta]: playerA.id, ligatura_brief_description: 'Player A assigned Persona.' },
            },
        });
        const characterB = await wpRequest(page, api, 'wp/v2/ligatura_character', {
            method: 'POST',
            body: {
                title: `Fixture Persona B ${suffix}`,
                content: '<p>Assigned Persona B fixture.</p>',
                status: 'publish',
                ligatura_character_type: [characterType.id],
                meta: { [assignmentMeta]: playerB.id, ligatura_brief_description: 'Player B assigned Persona.' },
            },
        });
        created.characters.push(characterA.id, characterB.id);

        await page.goto('/wp-admin/edit.php?post_type=ligatura_character', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('link', { name: characterA.title.rendered, exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: characterB.title.rendered, exact: true })).toBeVisible();
        await expect(page.locator('.page-title-action')).toBeVisible();

        await page.goto(`/wp-admin/post.php?post=${characterA.id}&action=edit`, { waitUntil: 'domcontentloaded' });
        const assignment = page.locator(`#${assignmentMeta}`);
        await expect(page.locator('#manuscriptum-illuminatum-assigned-player')).toContainText('Assigned Player');
        await expect(assignment.locator(`option[value="${playerA.id}"]`)).toHaveText(playerA.nickname);
        await expect(assignment.locator(`option[value="${playerB.id}"]`)).toHaveText(playerB.nickname);
        await expect(assignment).not.toContainText(playerA.email);

        await page.goto('/wp-admin/', { waitUntil: 'domcontentloaded' });

        const playerASession = await loginAsPlayer(browser, playerA, baseURL);
        playerAContext = playerASession.context;
        const playerAPage = playerASession.page;
        let playerAApi;

        await expect(playerAPage).toHaveURL(/\/wp-admin\/edit\.php\?post_type=ligatura_diary/);
        const menuLabels = await playerAPage.locator('#adminmenu > li:not(#collapse-menu) .wp-menu-name').allTextContents();
        expect(menuLabels).toEqual(expect.arrayContaining(['Journals', 'Characters', 'Media', 'Profile']));
        expect(menuLabels).not.toEqual(expect.arrayContaining(['Dashboard', 'Posts', 'Pages', 'Wiki Entries', 'Covenant Records', 'Settings', 'Appearance', 'Plugins', 'Users']));

        for (const path of [
            '/wp-admin/edit.php',
            '/wp-admin/edit.php?post_type=page',
            '/wp-admin/edit.php?post_type=ligatura_wiki',
            '/wp-admin/edit.php?post_type=ligatura_covenant',
            '/wp-admin/options-general.php',
            '/wp-admin/themes.php',
            '/wp-admin/plugins.php',
            '/wp-admin/users.php',
            `/wp-admin/user-edit.php?user_id=${status.default_admin}`,
        ]) {
            await expectAdminDenied(playerAPage, path);
        }

        await playerAPage.goto('/wp-admin/profile.php', { waitUntil: 'domcontentloaded' });
        await expect(playerAPage.locator('#your-profile')).toBeVisible();

        const assignedEditResponse = await playerAPage.goto(
            `/wp-admin/post.php?post=${characterA.id}&action=edit`,
            { waitUntil: 'domcontentloaded' }
        );
        expect(assignedEditResponse.status()).toBe(200);
        await takeOverPostLock(playerAPage);
        const characterListResponse = await playerAPage.goto('/wp-admin/edit.php?post_type=ligatura_character', { waitUntil: 'domcontentloaded' });
        expect(characterListResponse.status(), JSON.stringify(await characterListResponse.allHeaders())).toBe(200);
        await expect(playerAPage.getByRole('link', { name: characterA.title.rendered, exact: true })).toBeVisible();
        await expect(playerAPage.getByText(characterB.title.rendered, { exact: true })).toHaveCount(0);
        await expect(playerAPage.locator('.page-title-action')).toHaveCount(0);

        await playerAPage.goto(`/wp-admin/post.php?post=${characterA.id}&action=edit`, { waitUntil: 'domcontentloaded' });
        await takeOverPostLock(playerAPage);
        const occupationValue = `Updated by assigned Player A ${suffix}`;
        const occupationField = await openMetaBoxes(playerAPage, '#ligatura_occupation');
        await occupationField.fill(occupationValue);
        await expect(playerAPage.getByRole('button', { name: 'Save', exact: true })).toBeVisible();
        await expect(playerAPage.getByRole('button', { name: /submit for review/i })).toHaveCount(0);
        await expect(playerAPage.locator('#manuscriptum-illuminatum-assigned-player')).toHaveCount(0);
        await expect(playerAPage.getByRole('button', { name: /move to trash/i })).toHaveCount(0);
        playerAApi = await getCurrentApiSettings(playerAPage);

        const assignedCharacterRead = await wpRawRequest(playerAPage, playerAApi, `wp/v2/ligatura_character/${characterA.id}?context=edit`);
        expect(assignedCharacterRead.ok).toBe(true);
        expect(assignedCharacterRead.status).toBe(200);
        expect(assignedCharacterRead.body._links?.['wp:action-publish']).toBeTruthy();

        const playerCharacterUpdate = await wpRawRequest(playerAPage, playerAApi, `wp/v2/ligatura_character/${characterA.id}`, {
            method: 'POST',
            body: { status: 'publish', meta: { ligatura_occupation: occupationValue }, ligatura_saga_topic: [sagaTopic.id] },
        });
        expect(playerCharacterUpdate.ok).toBe(true);
        expect(playerCharacterUpdate.body.status).toBe('publish');
        expect(playerCharacterUpdate.body.meta.ligatura_occupation).toBe(occupationValue);
        expect(playerCharacterUpdate.body.ligatura_saga_topic).toContain(sagaTopic.id);

        const playerStatusChange = await wpRawRequest(playerAPage, playerAApi, `wp/v2/ligatura_character/${characterA.id}`, {
            method: 'POST', body: { status: 'pending' },
        });
        expect(playerStatusChange.ok).toBe(false);
        expect(playerStatusChange.status).toBe(403);
        expect(playerStatusChange.body.code).toBe('ligatura_player_character_status_forbidden');

        await playerAPage.reload({ waitUntil: 'domcontentloaded' });
        await expect(await openMetaBoxes(playerAPage, '#ligatura_occupation')).toHaveValue(occupationValue);
        await expectAdminDenied(playerAPage, `/wp-admin/post.php?post=${characterB.id}&action=edit`);
        await expectAdminDenied(playerAPage, '/wp-admin/post-new.php?post_type=ligatura_character');

        await playerAPage.goto('/wp-admin/post-new.php?post_type=ligatura_diary', { waitUntil: 'domcontentloaded' });
        const characterAuthorSelect = await openMetaBoxes(playerAPage, '#manuscriptum-illuminatum-diary-details select[name="ligatura_diary_character"]');
        await expect(playerAPage.locator('label[for="ligatura_diary_character"]')).toHaveText('Character Author');
        await expect(characterAuthorSelect.locator(`option[value="${characterA.id}"]`)).toHaveText(characterA.title.rendered);
        await expect(characterAuthorSelect.locator(`option[value="${characterB.id}"]`)).toHaveCount(0);
        await expect(characterAuthorSelect).toHaveValue(String(characterA.id));
        await expect(playerAPage.locator('#authordiv')).toHaveCount(0);

        const storyguideNotes = await wpRawRequest(playerAPage, playerAApi, `ligatura-manuscripti-illuminati/v1/storyguide-notes/${wikiEntries[0].id}`);
        expect(storyguideNotes.ok).toBe(false);
        expect(storyguideNotes.status).toBe(403);

        const otherCharacterRead = await wpRawRequest(playerAPage, playerAApi, `wp/v2/ligatura_character/${characterB.id}?context=edit`);
        expect(otherCharacterRead.ok).toBe(false);
        expect(otherCharacterRead.status).toBe(403);
        const otherCharacterUpdate = await wpRawRequest(playerAPage, playerAApi, `wp/v2/ligatura_character/${characterB.id}`, {
            method: 'POST', body: { content: 'Forged edit.' },
        });
        expect(otherCharacterUpdate.ok).toBe(false);
        expect(otherCharacterUpdate.status).toBe(403);

        const assignedCharacterDelete = await wpRawRequest(playerAPage, playerAApi, `wp/v2/ligatura_character/${characterA.id}?force=true`, {
            method: 'DELETE',
        });
        expect(assignedCharacterDelete.ok).toBe(false);
        expect(assignedCharacterDelete.status).toBe(403);

        const termCreation = await wpRawRequest(playerAPage, playerAApi, 'wp/v2/ligatura_saga_topic', {
            method: 'POST', body: { name: `Forbidden Topic ${suffix}` },
        });
        expect(termCreation.ok).toBe(false);
        expect(termCreation.status).toBe(403);

        const playerMedia = await uploadPlayerImage(playerAPage, playerAApi, suffix);
        created.media.push(playerMedia.id);
        expect(playerMedia.author).toBe(playerA.id);

        const setPersonaImage = await wpRequest(playerAPage, playerAApi, `wp/v2/ligatura_character/${characterA.id}`, {
            method: 'POST', body: { featured_media: playerMedia.id },
        });
        expect(setPersonaImage.featured_media).toBe(playerMedia.id);

        const visibleMedia = await wpRequest(playerAPage, playerAApi, 'wp/v2/media?per_page=100');
        expect(visibleMedia.map((item) => item.id)).toContain(playerMedia.id);
        expect(visibleMedia.every((item) => item.author === playerA.id)).toBe(true);
        const administratorMedia = await wpRequest(page, api, 'wp/v2/media?per_page=100&context=edit');
        expect(administratorMedia.map((item) => item.id)).toContain(playerMedia.id);
        if (adminMediaId) {
            expect(visibleMedia.map((item) => item.id)).not.toContain(adminMediaId);
            const otherMedia = await wpRawRequest(playerAPage, playerAApi, `wp/v2/media/${adminMediaId}?context=edit`);
            expect(otherMedia.ok).toBe(false);
            expect(otherMedia.status).toBe(403);

            const otherFeaturedMedia = await wpRawRequest(playerAPage, playerAApi, `wp/v2/ligatura_character/${characterA.id}`, {
                method: 'POST', body: { featured_media: adminMediaId },
            });
            expect(otherFeaturedMedia.ok).toBe(false);
            expect(otherFeaturedMedia.status).toBe(403);

            const preservedPersonaImage = await wpRequest(playerAPage, playerAApi, `wp/v2/ligatura_character/${characterA.id}?context=edit`);
            expect(preservedPersonaImage.featured_media).toBe(playerMedia.id);
        }

        const draft = await wpRequest(playerAPage, playerAApi, 'wp/v2/ligatura_diary', {
            method: 'POST',
            body: { title: `Fixture Player A Journal ${suffix}`, content: '<p>Player A technical ownership fixture.</p>', status: 'draft' },
        });
        created.journals.push(draft.id);
        const persistedDraft = await wpRequest(page, api, `wp/v2/ligatura_diary/${draft.id}?context=edit`);
        expect(persistedDraft.author).toBe(playerA.id);

        const forgedTechnicalAuthor = await wpRawRequest(playerAPage, playerAApi, `wp/v2/ligatura_diary/${draft.id}`, {
            method: 'POST', body: { author: playerB.id },
        });
        if (forgedTechnicalAuthor.ok) {
            expect(forgedTechnicalAuthor.body.author).toBe(playerA.id);
        } else {
            expect([400, 403]).toContain(forgedTechnicalAuthor.status);
        }
        const unchangedTechnicalAuthor = await wpRequest(page, api, `wp/v2/ligatura_diary/${draft.id}?context=edit`);
        expect(unchangedTechnicalAuthor.author).toBe(playerA.id);

        const defaultedAuthor = await wpRawRequest(playerAPage, playerAApi, `wp/v2/ligatura_diary/${draft.id}`, {
            method: 'POST', body: { status: 'publish' },
        });
        expect(defaultedAuthor.ok).toBe(true);
        expect(defaultedAuthor.status).toBe(200);
        expect(defaultedAuthor.body.meta.ligatura_diary_character).toBe(characterA.id);

        const forgedAuthor = await wpRawRequest(playerAPage, playerAApi, `wp/v2/ligatura_diary/${draft.id}`, {
            method: 'POST', body: { status: 'publish', meta: { ligatura_diary_character: characterB.id } },
        });
        expect(forgedAuthor.ok).toBe(false);
        expect(forgedAuthor.status).toBe(403);
        expect(forgedAuthor.body.code).toBe('ligatura_character_author_forbidden');

        const published = await wpRequest(playerAPage, playerAApi, `wp/v2/ligatura_diary/${draft.id}`, {
            method: 'POST',
            body: { status: 'publish', featured_media: playerMedia.id, meta: { ligatura_diary_character: characterA.id, ligatura_diary_saga_date: '1207-03-04' } },
        });
        expect(published.meta.ligatura_diary_character).toBe(characterA.id);
        expect(published.featured_media).toBe(playerMedia.id);

        await playerAPage.goto(new URL(published.link).pathname);
        await expect(playerAPage.locator('.manuscriptum-illuminatum-journal-header__author')).toHaveText(characterA.title.rendered);
        await expect(playerAPage.locator('.manuscriptum-illuminatum-journal-header__portrait')).toHaveAttribute('src', /fixture-player-/);
        await expect(playerAPage.locator('.manuscriptum-illuminatum-entry--diary')).not.toContainText(playerA.nickname);
        await expect(playerAPage.locator('.manuscriptum-illuminatum-entry--diary')).not.toContainText(playerA.displayName);
        await expect(playerAPage.locator('.manuscriptum-illuminatum-entry--diary')).not.toHaveClass(new RegExp(`author-${playerA.username}`));
        await expect(playerAPage.getByText('Related Persona:', { exact: false })).toHaveCount(0);

        await playerAPage.goto(`/journals/persona/${characterA.slug}/`);
        await expect(playerAPage.getByRole('heading', { name: `Commentarii by ${characterA.title.rendered}` })).toBeVisible();
        await expect(playerAPage.getByRole('link', { name: published.title.rendered })).toBeVisible();

        const forgedAssignment = await wpRawRequest(playerAPage, playerAApi, `wp/v2/ligatura_character/${characterA.id}`, {
            method: 'POST', body: { meta: { [assignmentMeta]: playerB.id } },
        });
        expect(forgedAssignment.ok).toBe(false);
        expect(forgedAssignment.status).toBe(403);
        expect(forgedAssignment.body.code).toBe('ligatura_player_assignment_forbidden');

        const clearedAssignment = await wpRequest(page, api, `wp/v2/ligatura_character/${characterB.id}`, {
            method: 'POST', body: { meta: { [assignmentMeta]: 0 } },
        });
        expect(clearedAssignment.meta[assignmentMeta]).toBe(status.default_admin);

        await wpRequest(page, api, `wp/v2/ligatura_character/${characterA.id}`, {
            method: 'POST', body: { meta: { [assignmentMeta]: playerB.id } },
        });

        const lostCharacterAccess = await wpRawRequest(playerAPage, playerAApi, `wp/v2/ligatura_character/${characterA.id}`, {
            method: 'POST', body: { content: 'Player A must no longer edit this.' },
        });
        expect(lostCharacterAccess.ok).toBe(false);
        expect(lostCharacterAccess.status).toBe(403);

        const afterReassignmentDraft = await wpRequest(playerAPage, playerAApi, 'wp/v2/ligatura_diary', {
            method: 'POST', body: { title: `Blocked after reassignment ${suffix}`, status: 'draft' },
        });
        created.journals.push(afterReassignmentDraft.id);
        const afterReassignmentPublish = await wpRawRequest(playerAPage, playerAApi, `wp/v2/ligatura_diary/${afterReassignmentDraft.id}`, {
            method: 'POST', body: { status: 'publish', meta: { ligatura_diary_character: characterA.id } },
        });
        expect(afterReassignmentPublish.ok).toBe(false);
        expect(afterReassignmentPublish.status).toBe(403);

        const playerBSession = await loginAsPlayer(browser, playerB, baseURL);
        playerBContext = playerBSession.context;
        const playerBPage = playerBSession.page;
        await playerBPage.goto(`/wp-admin/post.php?post=${characterA.id}&action=edit`, { waitUntil: 'domcontentloaded' });
        await takeOverPostLock(playerBPage);
        const playerBApi = await getCurrentApiSettings(playerBPage);

        const gainedCharacterAccess = await wpRequest(playerBPage, playerBApi, `wp/v2/ligatura_character/${characterA.id}`, {
            method: 'POST', body: { meta: { ligatura_occupation: 'Updated by Player B after reassignment' } },
        });
        expect(gainedCharacterAccess.meta.ligatura_occupation).toBe('Updated by Player B after reassignment');

        const playerBJournal = await wpRequest(playerBPage, playerBApi, 'wp/v2/ligatura_diary', {
            method: 'POST',
            body: {
                title: `Fixture Player B Journal ${suffix}`,
                status: 'publish',
                meta: { ligatura_diary_character: characterA.id, ligatura_diary_saga_date: '1207-03-05' },
            },
        });
        created.journals.push(playerBJournal.id);
        expect(playerBJournal.meta.ligatura_diary_character).toBe(characterA.id);
        const persistedPlayerBJournal = await wpRequest(page, api, `wp/v2/ligatura_diary/${playerBJournal.id}?context=edit`);
        expect(persistedPlayerBJournal.author).toBe(playerB.id);

        const otherJournalEdit = await wpRawRequest(playerAPage, playerAApi, `wp/v2/ligatura_diary/${playerBJournal.id}`, {
            method: 'POST', body: { content: 'Player A must not edit Player B technical ownership.' },
        });
        expect(otherJournalEdit.ok).toBe(false);
        expect(otherJournalEdit.status).toBe(403);

        const historical = await wpRequest(page, api, `wp/v2/ligatura_diary/${draft.id}?context=edit`);
        expect(historical.author).toBe(playerA.id);
        expect(historical.meta.ligatura_diary_character).toBe(characterA.id);

        await wpRequest(page, api, `wp/v2/users/${playerB.id}?force=true&reassign=${status.default_admin}`, { method: 'DELETE' });
        created.users = created.users.filter((id) => id !== playerB.id);
        const deletionSafeCharacter = await wpRequest(page, api, `wp/v2/ligatura_character/${characterA.id}?context=edit`);
        expect(deletionSafeCharacter.meta[assignmentMeta]).toBe(status.default_admin);
    } finally {
        await playerAContext?.close().catch(() => {});
        await playerBContext?.close().catch(() => {});

        const cleanupFailures = [];

        for (const journalId of [...created.journals].reverse()) {
            const result = await deleteFixture(page, api, `wp/v2/ligatura_diary/${journalId}?force=true`).catch((error) => ({ ok: false, status: 0, body: { message: error.message } }));
            if (!result.ok) cleanupFailures.push(`Journal ${journalId}: ${result.status} ${result.body?.message || result.body?.code || ''}`);
        }
        for (const characterId of [...created.characters].reverse()) {
            const result = await deleteFixture(page, api, `wp/v2/ligatura_character/${characterId}?force=true`).catch((error) => ({ ok: false, status: 0, body: { message: error.message } }));
            if (!result.ok) cleanupFailures.push(`Persona ${characterId}: ${result.status} ${result.body?.message || result.body?.code || ''}`);
        }
        for (const mediaId of [...created.media].reverse()) {
            const result = await deleteFixture(page, api, `wp/v2/media/${mediaId}?force=true`).catch((error) => ({ ok: false, status: 0, body: { message: error.message } }));
            if (!result.ok) cleanupFailures.push(`Media ${mediaId}: ${result.status} ${result.body?.message || result.body?.code || ''}`);
        }
        for (const userId of [...created.users].reverse()) {
            if (defaultAdminId > 0) {
                const result = await deleteFixture(page, api, `wp/v2/users/${userId}?force=true&reassign=${defaultAdminId}`).catch((error) => ({ ok: false, status: 0, body: { message: error.message } }));
                if (!result.ok) cleanupFailures.push(`Player ${userId}: ${result.status} ${result.body?.message || result.body?.code || ''}`);
            }
        }

        expect(cleanupFailures, `Player fixture cleanup failures:\n${cleanupFailures.join('\n')}`).toEqual([]);
    }
});
