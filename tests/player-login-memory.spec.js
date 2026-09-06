const { test, expect } = require('./support/runtime-probes');
const { randomBytes } = require('node:crypto');
const { getApiSettings, wpRawRequest, wpRequest } = require('./support/personae-fixture');

const assignmentMeta = '_ligatura_assigned_player';

function strongRandomPassword() {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()-_=+[]{};:,.?';

    return Array.from(randomBytes(96), (byte) => alphabet[byte % alphabet.length]).join('');
}

async function createPlayer(page, api, suffix, label) {
    const username = `fixture_memory_player_${suffix}_${label}`.toLowerCase();
    const result = await wpRawRequest(page, api, 'wp/v2/users', {
        method: 'POST',
        body: {
            username,
            password: strongRandomPassword(),
            email: `${username}@example.invalid`,
            nickname: `Memory Player ${label} ${suffix}`,
            roles: ['ligatura_player'],
        },
    });

    expect(
        result.ok,
        `Player ${label} creation returned ${result.status}: ${result.body?.message || result.body?.code || 'unknown error'}`
    ).toBe(true);
    return { ...result.body, username };
}

async function deleteFixture(page, api, path) {
    return wpRawRequest(page, api, path, { method: 'DELETE' });
}

async function runBootstrapProbe(page, api, fixture, publishCharacterId = 0) {
    const publishCharacter = publishCharacterId > 0 ? `&publish_character_id=${publishCharacterId}` : '';

    return wpRequest(
        page,
        api,
        `ligatura-manuscripti-illuminati/v1/player-access/bootstrap-probe?player_id=${fixture.playerId}`
        + `&assigned_character_id=${fixture.assignedCharacterId}&other_character_id=${fixture.otherCharacterId}`
        + `&own_journal_id=${fixture.ownJournalId}&other_journal_id=${fixture.otherJournalId}${publishCharacter}`
    );
}

async function uploadPlayerOwnedFixture(page, api, playerId, suffix) {
    const upload = await page.evaluate(async ({ root, nonce, filename }) => {
        const image = Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2n9sAAAAASUVORK5CYII='), (character) => character.charCodeAt(0));
        const response = await fetch(new URL('wp/v2/media', root), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Disposition': `attachment; filename="${filename}"`,
                'Content-Type': 'image/png',
                'X-WP-Nonce': nonce,
            },
            body: image,
        });

        return { ok: response.ok, status: response.status, body: await response.json() };
    }, { ...api, filename: `fixture-player-memory-${suffix}.png` });

    expect(upload.ok, `Media creation returned ${upload.status}`).toBe(true);

    return wpRequest(page, api, `wp/v2/media/${upload.body.id}`, {
        method: 'POST',
        body: { author: playerId },
    });
}

test('Player post-authentication bootstrap and repeated capability checks stay bounded', async ({ page, regressionProbes }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Security mutations are exercised once.');
    test.setTimeout(180_000);

    const api = await getApiSettings(page);
    const suffix = Date.now();
    const created = { users: [], characters: [], journals: [], media: [] };
    let defaultAdminId = 0;

    try {
        const status = await wpRequest(page, api, 'ligatura-manuscripti-illuminati/v1/player-access/status');
        defaultAdminId = status.default_admin;

        const playerA = await createPlayer(page, api, suffix, 'a');
        const playerB = await createPlayer(page, api, suffix, 'b');
        created.users.push(playerA.id, playerB.id);

        const characterA = await wpRequest(page, api, 'wp/v2/ligatura_character', {
            method: 'POST',
            body: {
                title: `Fixture Memory Persona A ${suffix}`,
                status: 'draft',
                meta: { [assignmentMeta]: playerA.id },
            },
        });
        const characterB = await wpRequest(page, api, 'wp/v2/ligatura_character', {
            method: 'POST',
            body: {
                title: `Fixture Memory Persona B ${suffix}`,
                status: 'draft',
                meta: { [assignmentMeta]: playerB.id },
            },
        });
        created.characters.push(characterA.id, characterB.id);

        const journalA = await wpRequest(page, api, 'wp/v2/ligatura_diary', {
            method: 'POST',
            body: { title: `Fixture Memory Journal A ${suffix}`, status: 'draft', author: playerA.id },
        });
        const journalB = await wpRequest(page, api, 'wp/v2/ligatura_diary', {
            method: 'POST',
            body: { title: `Fixture Memory Journal B ${suffix}`, status: 'draft', author: playerB.id },
        });
        created.journals.push(journalA.id, journalB.id);

        const media = await uploadPlayerOwnedFixture(page, api, playerA.id, suffix);
        created.media.push(media.id);

        const baseProbeFixture = {
            playerId: playerA.id,
            assignedCharacterId: characterA.id,
            otherCharacterId: characterB.id,
            ownJournalId: journalA.id,
            otherJournalId: journalB.id,
        };
        const probe = await runBootstrapProbe(page, api, baseProbeFixture);

        expect(probe.authenticated).toBe(true);
        expect(probe.player).toBe(true);
        expect(probe.restricted_player).toBe(true);
        expect(probe.iterations).toBe(25);
        expect(probe.capabilities).toEqual({
            read: true,
            upload_files: true,
            create_journal: true,
            publish_journal: true,
            create_persona: false,
            edit_assigned_persona: true,
            delete_assigned_persona: false,
            edit_other_persona: false,
            edit_own_journal: true,
            edit_other_journal: false,
            manage_options: false,
            assign_persona_players: false,
        });
        expect(probe.query_count).toBeLessThan(100);
        expect(probe.memory_growth_bytes).toBeLessThan(16 * 1024 * 1024);
        expect(probe.memory_peak_bytes).toBeLessThan(probe.memory_limit_bytes / 2);

        expect(probe.rest.assigned_persona.status).toBe(200);
        expect(probe.rest.other_persona.status).toBe(403);
        expect(probe.rest.character_collection.status).toBe(200);
        expect(probe.rest.character_collection.ids).toEqual([characterA.id]);
        expect(probe.rest.own_journal.status).toBe(200);
        expect(probe.rest.other_journal.status).toBe(403);
        expect(probe.rest.media_collection.status).toBe(200);
        expect(probe.rest.media_collection.ids).toContain(media.id);
        expect(probe.rest.media_collection.authors).toEqual([playerA.id]);
        expect(probe.rest.publish_own_journal.status).toBe(200);
        expect(probe.rest.publish_own_journal.author).toBe(playerA.id);
        expect(probe.rest.publish_own_journal.character_author).toBe(characterA.id);
        expect(probe.rest.forge_character_author.status).toBe(403);
        expect(probe.rest.forge_character_author.code).toBe('ligatura_character_author_forbidden');
        expect(probe.rest.update_assigned_persona.status).toBe(200);
        expect(probe.rest.update_assigned_persona.occupation).toBe('Updated by assigned Player probe');
        expect(probe.rest.forge_persona_assignment.status).not.toBe(200);
        expect(probe.rest.delete_assigned_persona.status).toBe(403);
        expect(probe.rest.write_other_journal.status).toBe(403);
        expect(probe.admin_routing).toEqual({
            dashboard: 'redirect',
            landing: 'allow',
            forbidden: 'deny',
            assigned_persona_edit: 'allow',
            other_persona_edit: 'deny',
            character_query: {
                author: '',
                meta_key: assignmentMeta,
                meta_value: playerA.id,
            },
        });

        const characterA2 = await wpRequest(page, api, 'wp/v2/ligatura_character', {
            method: 'POST',
            body: {
                title: `Fixture Memory Persona A2 ${suffix}`,
                status: 'draft',
                meta: { [assignmentMeta]: playerA.id },
            },
        });
        const multipleJournal = await wpRequest(page, api, 'wp/v2/ligatura_diary', {
            method: 'POST',
            body: { title: `Fixture Multiple Persona Journal ${suffix}`, status: 'draft', author: playerA.id },
        });
        created.characters.push(characterA2.id);
        created.journals.push(multipleJournal.id);

        const multipleFixture = { ...baseProbeFixture, ownJournalId: multipleJournal.id };
        const missingMultipleChoice = await runBootstrapProbe(page, api, multipleFixture);
        expect(missingMultipleChoice.rest.publish_own_journal.status).toBe(400);
        expect(missingMultipleChoice.rest.publish_own_journal.code).toBe('ligatura_character_author_required');

        const explicitMultipleChoice = await runBootstrapProbe(page, api, multipleFixture, characterA.id);
        expect(explicitMultipleChoice.rest.publish_own_journal.status).toBe(200);
        expect(explicitMultipleChoice.rest.publish_own_journal.character_author).toBe(characterA.id);

        const playerC = await createPlayer(page, api, suffix, 'c');
        created.users.push(playerC.id);
        const zeroPersonaJournal = await wpRequest(page, api, 'wp/v2/ligatura_diary', {
            method: 'POST',
            body: { title: `Fixture Zero Persona Journal ${suffix}`, status: 'draft', author: playerC.id },
        });
        created.journals.push(zeroPersonaJournal.id);
        const zeroPersonaProbe = await runBootstrapProbe(page, api, {
            ...baseProbeFixture,
            playerId: playerC.id,
            ownJournalId: zeroPersonaJournal.id,
        });
        expect(zeroPersonaProbe.rest.publish_own_journal.status).toBe(400);
        expect(zeroPersonaProbe.rest.publish_own_journal.message).toContain('need an assigned Persona');

        await testInfo.attach('player-bootstrap-probe.json', {
            body: JSON.stringify(probe, null, 2),
            contentType: 'application/json',
        });
    } finally {
        const cleanupFailures = [];
        const cleanup = async (label, path) => {
            const result = await deleteFixture(page, api, path).catch((error) => ({
                ok: false,
                status: 0,
                body: { message: error.message },
            }));

            if (!result.ok) {
                cleanupFailures.push(`${label}: ${result.status} ${result.body?.message || result.body?.code || ''}`);
            }
        };

        for (const mediaId of [...created.media].reverse()) {
            await cleanup(`Media ${mediaId}`, `wp/v2/media/${mediaId}?force=true`);
        }
        for (const journalId of [...created.journals].reverse()) {
            await cleanup(`Journal ${journalId}`, `wp/v2/ligatura_diary/${journalId}?force=true`);
        }
        for (const characterId of [...created.characters].reverse()) {
            await cleanup(`Persona ${characterId}`, `wp/v2/ligatura_character/${characterId}?force=true`);
        }
        for (const userId of [...created.users].reverse()) {
            if (defaultAdminId > 0) {
                await cleanup(`Player ${userId}`, `wp/v2/users/${userId}?force=true&reassign=${defaultAdminId}`);
            }
        }

        expect(cleanupFailures, `Player memory fixture cleanup failures:\n${cleanupFailures.join('\n')}`).toEqual([]);
    }
});
