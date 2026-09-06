const { test, expect } = require('./support/runtime-probes');
const {
    createPersonaeImageFixture,
    createPersonaeWithoutImageFixture,
    getApiSettings,
    restorePersonaeImageFixture,
    snapshotCharacterState,
    wpRawRequest,
    wpRequest,
} = require('./support/personae-fixture');

const requiredMessage = 'A Character Type is required before this Persona can be published.';

test('server requires a valid Character Type before publishing or scheduling a Persona', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Server-side publication mutation is verified once.');
    test.setTimeout(60_000);

    const api = await getApiSettings(page);
    const marker = `Fixture Untyped Persona ${Date.now()}`;
    const terms = await wpRequest(page, api, 'wp/v2/ligatura_character_type?per_page=100&hide_empty=false');
    const type = terms.find((term) => term.slug === 'companion') || terms[0];
    let characterId = 0;

    expect(type).toBeTruthy();

    try {
        const draft = await wpRawRequest(page, api, 'wp/v2/ligatura_character', {
            method: 'POST',
            body: {
                title: marker,
                content: 'Temporary server-side Character Type validation fixture.',
                status: 'draft',
            },
        });

        expect(draft.ok).toBe(true);
        expect(draft.body.status).toBe('draft');
        expect(draft.body.ligatura_character_type).toEqual([]);
        characterId = draft.body.id;

        const publishAttempt = await wpRawRequest(page, api, `wp/v2/ligatura_character/${characterId}`, {
            method: 'POST',
            body: { status: 'publish' },
        });

        expect(publishAttempt.ok).toBe(false);
        expect(publishAttempt.status).toBe(400);
        expect(publishAttempt.body.code).toBe('ligatura_character_type_required');
        expect(publishAttempt.body.message).toBe(requiredMessage);

        const scheduledDate = new Date(Date.now() + (7 * 24 * 60 * 60 * 1000)).toISOString();
        const scheduleAttempt = await wpRawRequest(page, api, `wp/v2/ligatura_character/${characterId}`, {
            method: 'POST',
            body: {
                date: scheduledDate,
                status: 'future',
            },
        });

        expect(scheduleAttempt.ok).toBe(false);
        expect(scheduleAttempt.status).toBe(400);
        expect(scheduleAttempt.body.code).toBe('ligatura_character_type_required');
        expect(scheduleAttempt.body.message).toBe(requiredMessage);

        const published = await wpRawRequest(page, api, `wp/v2/ligatura_character/${characterId}`, {
            method: 'POST',
            body: {
                status: 'publish',
                ligatura_character_type: [type.id],
            },
        });

        expect(published.ok).toBe(true);
        expect(published.body.status).toBe('publish');
        expect(published.body.ligatura_character_type).toEqual([type.id]);

        const persisted = await wpRequest(page, api, `wp/v2/ligatura_character/${characterId}?context=edit`);
        expect(persisted.status).toBe('publish');
        expect(persisted.ligatura_character_type).toEqual([type.id]);

        await page.goto(`/characters/?fixture-fixture=${characterId}`);
        const group = page.locator(`.manuscriptum-illuminatum-personae-group[data-character-type="${type.slug}"]`);
        await expect(group.getByRole('link', { name: marker })).toBeVisible();
    } finally {
        if (characterId) {
            await wpRawRequest(page, api, `wp/v2/ligatura_character/${characterId}?force=true`, { method: 'DELETE' });
        }
    }
});

test('persisted taxonomy recheck reverses a direct publication when tax_input is not saved', async ({ page, regressionProbes }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The direct wp_insert_post persistence probe is verified once.');

    const api = await getApiSettings(page);
    const terms = await wpRequest(page, api, 'wp/v2/ligatura_character_type?per_page=100&hide_empty=false');
    const type = terms.find((term) => term.slug === 'companion') || terms[0];
    let characterId = 0;

    expect(type).toBeTruthy();

    try {
        const probe = await wpRequest(page, api, 'ligatura-manuscripti-illuminati/v1/character-publication/persistence-probe', {
            method: 'POST',
            body: { term_id: type.id },
        });

        characterId = probe.id;
        expect(probe.initial_status).toBe('publish');
        expect(probe.persisted_terms).toEqual([]);

        const persisted = await wpRequest(page, api, `wp/v2/ligatura_character/${characterId}?context=edit`);
        expect(persisted.ligatura_character_type).toEqual([]);
        expect(persisted.status).toBe('draft');
    } finally {
        if (characterId) {
            await wpRawRequest(page, api, `wp/v2/ligatura_character/${characterId}?force=true`, { method: 'DELETE' });
        }
    }
});

test('failed Persona fixture setup restores canonical data before deleting temporary media', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The test mutation and cleanup regression is verified once.');
    test.setTimeout(60_000);

    const api = await getApiSettings(page);
    const characters = await wpRequest(page, api, 'wp/v2/ligatura_character?slug=aveline-of-bonisagus&context=edit');
    const terms = await wpRequest(page, api, 'wp/v2/ligatura_character_type?per_page=100&hide_empty=false');
    const character = characters[0];
    const alternateType = terms.find((term) => !character.ligatura_character_type.includes(term.id));
    const marker = `Intentional fixture failure ${Date.now()}`;
    let failedFixture;
    let restoredBeforeDelete = false;

    expect(character).toBeTruthy();
    expect(alternateType).toBeTruthy();

    await expect(createPersonaeImageFixture(page, {
        fields: { title: marker },
        meta: { ligatura_occupation: marker },
        taxonomies: { ligatura_character_type: [alternateType.id] },
        afterMutation: async (fixture, updatedCharacter) => {
            failedFixture = fixture;
            expect(updatedCharacter.title.raw).toBe(marker);
            expect(updatedCharacter.featured_media).toBe(fixture.mediaId);
            expect(updatedCharacter.meta.ligatura_occupation).toBe(marker);
            expect(updatedCharacter.ligatura_character_type).toEqual([alternateType.id]);
            throw new Error(marker);
        },
        beforeTemporaryMediaDelete: async (fixture) => {
            const restored = await wpRequest(page, fixture.api, `wp/v2/ligatura_character/${fixture.characterId}?context=edit`);
            const media = await wpRawRequest(page, fixture.api, `wp/v2/media/${fixture.mediaId}?context=edit`);

            expect(snapshotCharacterState(restored)).toEqual(fixture.originalState);
            expect(media.ok).toBe(true);
            restoredBeforeDelete = true;
        },
    })).rejects.toThrow(marker);

    expect(failedFixture).toBeTruthy();
    expect(restoredBeforeDelete).toBe(true);

    const restored = await wpRequest(page, api, `wp/v2/ligatura_character/${failedFixture.characterId}?context=edit`);
    const deletedMedia = await wpRawRequest(page, api, `wp/v2/media/${failedFixture.mediaId}?context=edit`);

    expect(snapshotCharacterState(restored)).toEqual(failedFixture.originalState);
    expect(deletedMedia.ok).toBe(false);
    expect(deletedMedia.status).toBe(404);
});

test('single Persona uses the compact header, six-field summary, two-column fields, and full-width Notes', async ({ page }, testInfo) => {
    test.setTimeout(60_000);
    let fixture;

    try {
        fixture = await createPersonaeImageFixture(page, {
            meta: {
                ligatura_apparent_age: 32,
                ligatura_occupation: 'Hermetic researcher',
            },
        });
        await page.goto(`${fixture.characterPath}?fixture-fixture=${fixture.mediaId}`);

        await expect(page).not.toHaveURL(/wp-login\.php/);
        const entry = page.locator('.manuscriptum-illuminatum-entry--character');
        const header = entry.locator('.manuscriptum-illuminatum-persona-header');
        const headerImage = header.locator('.manuscriptum-illuminatum-persona-header__image');

        await expect(entry.locator('.manuscriptum-illuminatum-entry-hero')).toHaveCount(0);
        await expect(header).toBeVisible();
        await expect(header.getByRole('heading', { level: 1, name: /Aveline of Bonisagus/i })).toBeVisible();
        await expect(header.getByText(/Patient, exacting/i)).toBeVisible();
        await expect(headerImage).toBeVisible();
        await expect(headerImage).toHaveAttribute('alt', 'Gold key on a blue heraldic shield');

        const headerLayout = await header.evaluate((element) => {
            const text = element.querySelector('.manuscriptum-illuminatum-persona-header__text').getBoundingClientRect();
            const descriptionElement = element.querySelector('.manuscriptum-illuminatum-persona-header__text p');
            const description = descriptionElement.getBoundingClientRect();
            const image = element.querySelector('.manuscriptum-illuminatum-persona-header__image').getBoundingClientRect();
            const style = getComputedStyle(element);
            const box = element.getBoundingClientRect();

            return {
                backgroundColor: style.backgroundColor,
                borderStyle: style.borderStyle,
                height: box.height,
                imageHeight: image.height,
                imageWidth: image.width,
                imageX: image.x,
                remainingTextWidth: image.x - parseFloat(style.columnGap) - text.x,
                textWidth: text.width,
                textX: text.x,
                descriptionMaxWidth: getComputedStyle(descriptionElement).maxWidth,
                descriptionWidth: description.width,
            };
        });

        expect(headerLayout.backgroundColor).not.toBe('rgba(32, 23, 15, 0.92)');
        expect(headerLayout.borderStyle).toBe('solid');
        expect(headerLayout.height).toBeLessThan(220);
        expect(headerLayout.imageX).toBeGreaterThan(headerLayout.textX);
        expect(headerLayout.imageHeight).toBeLessThanOrEqual(140);
        expect(headerLayout.imageWidth).toBeLessThanOrEqual(112);
        expect(Math.abs(headerLayout.textWidth - headerLayout.remainingTextWidth)).toBeLessThanOrEqual(2);
        expect(Math.abs(headerLayout.descriptionWidth - headerLayout.textWidth)).toBeLessThanOrEqual(2);
        expect(headerLayout.descriptionMaxWidth).toBe('none');

        const summary = entry.locator('.manuscriptum-illuminatum-persona-summary');
        const summaryLabels = await summary.locator('dt').allTextContents();
        expect(summaryLabels).toEqual(['House', 'Tradition', 'Player', 'Apparent Age', 'Origin', 'Occupation']);
        await expect(summary.getByText('32', { exact: true })).toBeVisible();
        await expect(summary.getByText('Hermetic researcher', { exact: true })).toBeVisible();
        expect(summaryLabels).not.toEqual(expect.arrayContaining(['Status', 'Age', 'Birth Year', 'Saga Role', 'Covenant Role']));

        const summaryColumns = await summary.locator('.manuscriptum-illuminatum-meta-list').evaluate(
            (element) => getComputedStyle(element).gridTemplateColumns.split(' ').filter(Boolean).length
        );
        expect(summaryColumns).toBe(testInfo.project.name === 'mobile' ? 2 : 6);

        const fields = entry.locator('.manuscriptum-illuminatum-character-fields');
        const fieldHeadings = fields.getByRole('heading', { level: 2 });
        const headingNames = await fieldHeadings.allTextContents();
        expect(headingNames).toEqual([
            'Characteristics',
            'Personality Traits',
            'Virtues',
            'Flaws',
            'Abilities',
            'Hermetic Arts',
            'Spells',
            'Equipment',
            'Wounds',
            'Warping',
            'Confidence',
            'Reputations',
        ]);

        const fieldColumns = await fields.evaluate(
            (element) => getComputedStyle(element).gridTemplateColumns.split(' ').filter(Boolean).length
        );
        expect(fieldColumns).toBe(testInfo.project.name === 'mobile' ? 1 : 2);

        for (const size of await fieldHeadings.evaluateAll((headings) => headings.map((heading) => parseFloat(getComputedStyle(heading).fontSize)))) {
            expect(size).toBeLessThanOrEqual(14);
        }

        await expect(entry.getByRole('heading', { name: 'Full Sheet', exact: true })).toHaveCount(0);
        await expect(entry.getByRole('heading', { name: 'Public Notes', exact: true })).toHaveCount(0);

        const notes = entry.locator('.manuscriptum-illuminatum-character-notes');
        const notesHeading = notes.getByRole('heading', { level: 2, name: 'Notes', exact: true });
        const notesBody = notes.locator('.manuscriptum-illuminatum-character-notes__body');
        const notesParagraph = notesBody.locator('p').first();
        const mainContent = entry.locator(':scope > .manuscriptum-illuminatum-content').first();

        await expect(notesHeading).toBeVisible();
        await expect(notesParagraph).toBeVisible();
        expect(await notesHeading.evaluate((element) => parseFloat(getComputedStyle(element).fontSize))).toBeLessThanOrEqual(14);

        const notesLayout = await notes.evaluate((element) => {
            const heading = element.querySelector(':scope > h2').getBoundingClientRect();
            const body = element.querySelector('.manuscriptum-illuminatum-character-notes__body').getBoundingClientRect();
            const paragraph = element.querySelector('.manuscriptum-illuminatum-character-notes__body p').getBoundingClientRect();
            const initial = getComputedStyle(element.querySelector('.manuscriptum-illuminatum-character-notes__body p'), '::first-letter');

            return {
                bodyWidth: body.width,
                firstLetterFloat: initial.cssFloat,
                headingBottom: heading.bottom,
                paragraphTop: paragraph.top,
                viewportOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            };
        });
        const mainBox = await mainContent.boundingBox();

        expect(notesLayout.paragraphTop - notesLayout.headingBottom).toBeGreaterThanOrEqual(12);
        expect(notesLayout.firstLetterFloat).toBe('none');
        expect(Math.abs(notesLayout.bodyWidth - mainBox.width)).toBeLessThanOrEqual(2);
        expect(notesLayout.viewportOverflow).toBeLessThanOrEqual(0);
    } finally {
        await restorePersonaeImageFixture(page, fixture);
    }
});

test('single Persona without a campaign image omits media and gives text the header width', async ({ page }) => {
    let fixture;

    try {
        fixture = await createPersonaeWithoutImageFixture(page);
        await page.goto(`${fixture.characterPath}?fixture-fixture=no-image-${fixture.characterId}`);

        const header = page.locator('.manuscriptum-illuminatum-persona-header');
        await expect(header).toBeVisible();
        await expect(header.locator('.manuscriptum-illuminatum-persona-header__media')).toHaveCount(0);
        await expect(header.locator('img')).toHaveCount(0);
        await expect(header).not.toHaveClass(/manuscriptum-illuminatum-persona-header--with-image/);
        expect(await header.evaluate((element) => getComputedStyle(element).gridTemplateColumns.split(' ').filter(Boolean).length)).toBe(1);
    } finally {
        await restorePersonaeImageFixture(page, fixture);
    }
});

test('Persona editor exposes independent Apparent Age and Occupation fields', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Editor field structure is verified once.');

    const api = await getApiSettings(page);
    const characters = await wpRequest(page, api, 'wp/v2/ligatura_character?slug=aveline-of-bonisagus&context=edit');
    expect(characters.length).toBe(1);

    await page.goto(`/wp-admin/post.php?post=${characters[0].id}&action=edit`, { waitUntil: 'domcontentloaded' });
    const identity = page.locator('#manuscriptum-illuminatum-character-identity');
    await expect(identity.locator('label[for="ligatura_apparent_age"]')).toHaveText('Apparent Age');
    await expect(identity.locator('#ligatura_apparent_age')).toHaveAttribute('type', 'number');
    await expect(identity.locator('label[for="ligatura_occupation"]')).toHaveText('Occupation');
    await expect(identity.locator('#ligatura_occupation')).toHaveAttribute('type', 'text');
    await expect(identity.locator('#ligatura_age')).toHaveCount(1);
});
