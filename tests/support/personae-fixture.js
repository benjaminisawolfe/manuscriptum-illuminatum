const { expect } = require('@playwright/test');

async function getApiSettings(page) {
    await page.goto('/wp-admin/', { waitUntil: 'domcontentloaded' });
    await expect(page).not.toHaveURL(/wp-login\.php/);

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

async function wpRawRequest(page, api, path, options = {}) {
    return page.evaluate(async ({ root, nonce, path: requestPath, options: requestOptions }) => {
        const response = await fetch(new URL(requestPath, root).toString(), {
            method: requestOptions.method || 'GET',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': nonce,
                ...(requestOptions.body ? { 'Content-Type': 'application/json' } : {}),
            },
            body: requestOptions.body ? JSON.stringify(requestOptions.body) : undefined,
        });

        return {
            ok: response.ok,
            status: response.status,
            body: await response.json(),
        };
    }, { ...api, path, options });

}

async function wpRequest(page, api, path, options = {}) {
    const result = await wpRawRequest(page, api, path, options);

    expect(result.ok, `${path} returned ${result.status}`).toBe(true);
    return result.body;
}

async function uploadFixtureImage(page, api) {
    const result = await page.evaluate(async ({ root, nonce }) => {
        const canvas = document.createElement('canvas');
        canvas.width = 520;
        canvas.height = 720;
        const context = canvas.getContext('2d');

        context.fillStyle = '#ead7aa';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = '#24446b';
        context.beginPath();
        context.moveTo(80, 70);
        context.lineTo(440, 70);
        context.lineTo(410, 500);
        context.quadraticCurveTo(260, 675, 110, 500);
        context.closePath();
        context.fill();
        context.fillStyle = '#b8851d';
        context.beginPath();
        context.arc(260, 265, 92, 0, Math.PI * 2);
        context.fill();
        context.fillRect(238, 340, 44, 190);
        context.fillRect(205, 445, 110, 38);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        const response = await fetch(new URL('wp/v2/media', root).toString(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Disposition': 'attachment; filename="fixture-persona-campaign-image.png"',
                'Content-Type': 'image/png',
                'X-WP-Nonce': nonce,
            },
            body: await blob.arrayBuffer(),
        });

        return {
            ok: response.ok,
            status: response.status,
            body: await response.json(),
        };
    }, api);

    expect(result.ok, `Campaign image upload returned ${result.status}`).toBe(true);

    return result.body;
}

function snapshotCharacterState(character) {
    const rawValue = (field) => character[field]?.raw ?? character[field]?.rendered ?? '';
    const state = {
        title: rawValue('title'),
        content: rawValue('content'),
        excerpt: rawValue('excerpt'),
        status: character.status,
        slug: character.slug,
        author: character.author,
        featured_media: character.featured_media || 0,
        meta: { ...(character.meta || {}) },
        ligatura_character_type: [...(character.ligatura_character_type || [])],
        ligatura_hermetic_house: [...(character.ligatura_hermetic_house || [])],
        ligatura_saga_topic: [...(character.ligatura_saga_topic || [])],
    };

    for (const field of ['date', 'date_gmt', 'password', 'template', 'comment_status', 'ping_status', 'menu_order', 'parent']) {
        if (Object.prototype.hasOwnProperty.call(character, field)) {
            state[field] = character[field];
        }
    }

    return state;
}

async function restorePersonaeFixtureState(page, fixture, options = {}) {
    const cleanupErrors = [];
    let restored = false;

    try {
        await wpRequest(page, fixture.api, `wp/v2/ligatura_character/${fixture.characterId}`, {
            method: 'POST',
            body: fixture.originalState,
        });
        restored = true;
    } catch (error) {
        cleanupErrors.push(new Error(`Failed to restore Persona ${fixture.characterId}: ${error.message}`, { cause: error }));
    }

    if (restored && typeof options.beforeTemporaryMediaDelete === 'function') {
        try {
            await options.beforeTemporaryMediaDelete(fixture);
        } catch (error) {
            cleanupErrors.push(new Error(`Persona ${fixture.characterId} was restored, but the pre-delete cleanup check failed: ${error.message}`, { cause: error }));
        }
    }

    if (restored && fixture.mediaId) {
        try {
            await wpRequest(page, fixture.api, `wp/v2/media/${fixture.mediaId}?force=true`, { method: 'DELETE' });
        } catch (error) {
            cleanupErrors.push(new Error(`Failed to delete temporary Persona media ${fixture.mediaId}: ${error.message}`, { cause: error }));
        }
    }

    if (cleanupErrors.length) {
        throw new AggregateError(cleanupErrors, `Persona fixture cleanup was incomplete for Character ${fixture.characterId}.`);
    }
}

async function createPersonaeImageFixture(page, options = {}) {
    const api = await getApiSettings(page);
    const characters = await wpRequest(page, api, 'wp/v2/ligatura_character?per_page=100&context=edit&orderby=title&order=asc');
    const preferredTitle = options.characterTitle || 'Aveline';
    const character = characters.find((item) => item.title.rendered.includes(preferredTitle)) || characters[0];

    expect(character).toBeTruthy();

    const fixture = {
        api,
        characterId: character.id,
        mediaId: 0,
        characterPath: new URL(character.link).pathname,
        originalState: snapshotCharacterState(character),
    };

    try {
        const media = await uploadFixtureImage(page, api);
        fixture.mediaId = media.id;

        await wpRequest(page, api, `wp/v2/media/${fixture.mediaId}`, {
            method: 'POST',
            body: { alt_text: 'Gold key on a blue heraldic shield' },
        });
        const fixtureMeta = options.meta || {};
        const fixtureFields = options.fields || {};
        const fixtureTaxonomies = options.taxonomies || {};
        const updatedCharacter = await wpRequest(page, api, `wp/v2/ligatura_character/${character.id}`, {
            method: 'POST',
            body: {
                ...fixtureFields,
                ...fixtureTaxonomies,
                featured_media: fixture.mediaId,
                ...(Object.keys(fixtureMeta).length ? { meta: fixtureMeta } : {}),
            },
        });

        for (const [key, value] of Object.entries(fixtureMeta)) {
            expect(updatedCharacter.meta?.[key]).toBe(value);
        }

        if (typeof options.afterMutation === 'function') {
            await options.afterMutation(fixture, updatedCharacter);
        }
    } catch (error) {
        try {
            await restorePersonaeFixtureState(page, fixture, {
                beforeTemporaryMediaDelete: options.beforeTemporaryMediaDelete,
            });
        } catch (cleanupError) {
            throw new AggregateError(
                [error, cleanupError],
                `Persona fixture setup failed and test cleanup was incomplete for Character ${fixture.characterId}.`
            );
        }

        throw error;
    }

    return fixture;
}

async function createPersonaeWithoutImageFixture(page) {
    const api = await getApiSettings(page);
    const characters = await wpRequest(page, api, 'wp/v2/ligatura_character?per_page=100&context=edit&orderby=title&order=asc');
    const character = characters.find((item) => item.featured_media) || characters[0];

    expect(character).toBeTruthy();

    const fixture = {
        api,
        characterId: character.id,
        mediaId: 0,
        characterPath: new URL(character.link).pathname,
        originalState: snapshotCharacterState(character),
    };

    try {
        const updatedCharacter = await wpRequest(page, api, `wp/v2/ligatura_character/${character.id}`, {
            method: 'POST',
            body: { featured_media: 0 },
        });

        expect(updatedCharacter.featured_media).toBe(0);
    } catch (error) {
        try {
            await restorePersonaeFixtureState(page, fixture);
        } catch (cleanupError) {
            throw new AggregateError(
                [error, cleanupError],
                `Image-free Persona fixture setup failed and test cleanup was incomplete for Character ${fixture.characterId}.`
            );
        }

        throw error;
    }

    return fixture;
}

async function restorePersonaeImageFixture(page, fixture) {
    if (!fixture) {
        return;
    }

    await restorePersonaeFixtureState(page, fixture);
}

module.exports = {
    createPersonaeImageFixture,
    createPersonaeWithoutImageFixture,
    getApiSettings,
    restorePersonaeImageFixture,
    snapshotCharacterState,
    uploadFixtureImage,
    wpRawRequest,
    wpRequest,
};
