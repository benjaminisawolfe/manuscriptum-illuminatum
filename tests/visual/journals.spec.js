const { test, expect } = require('@playwright/test');
const { attachJournalTag, restoreJournalTags } = require('../helpers/journal-tags');
const { getJournalPersonaCollection } = require('../helpers/journal-persona');
const {
    createPersonaeImageFixture,
    getApiSettings,
    restorePersonaeImageFixture,
    uploadFixtureImage,
    wpRequest,
} = require('../support/personae-fixture');
const { preserveUnrelatedNavigationBaseline } = require('./support');

async function attachFeaturedImage(page, postType, slug) {
    const api = await getApiSettings(page);
    const records = await wpRequest(page, api, `wp/v2/${postType}?slug=${slug}&context=edit`);
    const record = records[0];
    const media = await uploadFixtureImage(page, api);

    expect(record).toBeTruthy();
    await wpRequest(page, api, `wp/v2/media/${media.id}`, {
        method: 'POST',
        body: { alt_text: 'Gold key on a blue heraldic shield' },
    });
    await wpRequest(page, api, `wp/v2/${postType}/${record.id}`, {
        method: 'POST',
        body: { featured_media: media.id },
    });

    return { api, postType, recordId: record.id, featuredMedia: record.featured_media || 0, mediaId: media.id };
}

async function restoreFeaturedImage(page, fixture) {
    if (!fixture) return;
    await wpRequest(page, fixture.api, `wp/v2/${fixture.postType}/${fixture.recordId}`, {
        method: 'POST',
        body: { featured_media: fixture.featuredMedia },
    });
    await wpRequest(page, fixture.api, `wp/v2/media/${fixture.mediaId}?force=true`, { method: 'DELETE' });
}

test('Commentarii directory presentation', async ({ page }) => {
    await page.goto('/journals/');
    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expect(page.locator('.manuscriptum-illuminatum-journal-directory .manuscriptum-illuminatum-journal-row').first()).toBeVisible();
	await preserveUnrelatedNavigationBaseline(page);

    await expect(page).toHaveScreenshot('commentarii-directory.png', {
        fullPage: true,
    });
});

test('single Commentarium presentation', async ({ page }) => {
    let fixture;
    let journalImage;
    let personaImage;

    try {
        personaImage = await createPersonaeImageFixture(page, { characterTitle: 'Guarin' });
        journalImage = await attachFeaturedImage(page, 'ligatura_diary', 'guarin-begins-courteous-audit');
        fixture = await attachJournalTag(page, 'guarin-begins-courteous-audit', 'Covenant Affairs', 'fixture-journal-covenant-affairs');
        await page.goto('/journals/guarin-begins-courteous-audit/');
        await expect(page.locator('.manuscriptum-illuminatum-journal-content__image img')).toBeVisible();
        await expect(page.locator('.manuscriptum-illuminatum-journal-header__portrait')).toBeVisible();
        await expect(page.locator('.manuscriptum-illuminatum-journal-footer__topics')).toContainText('Covenant Affairs');
        await expect(page.locator('.manuscriptum-illuminatum-journal-footer__tags')).toContainText('Covenant Affairs');
		await preserveUnrelatedNavigationBaseline(page);

        await expect(page).toHaveScreenshot('commentarium-entry.png', {
            fullPage: true,
        });
    } finally {
        if (fixture) await restoreJournalTags(page, fixture);
        await restoreFeaturedImage(page, journalImage);
        await restorePersonaeImageFixture(page, personaImage);
    }
});

test('Persona-specific Commentarii collection presentation', async ({ page }) => {
    const persona = await getJournalPersonaCollection(page, 'Guarin of Tremere');
    await page.goto(persona.path);
    await expect(page.locator('.manuscriptum-illuminatum-commentarii-persona-context')).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-journal-directory .manuscriptum-illuminatum-journal-row').first()).toBeVisible();
    await preserveUnrelatedNavigationBaseline(page);

    await expect(page).toHaveScreenshot('commentarii-persona-collection.png', { fullPage: true });
});

test('Covenant Records directory presentation', async ({ page }) => {
    await page.goto('/covenant-records/');
    await expect(page.locator('.manuscriptum-illuminatum-covenant-directory .manuscriptum-illuminatum-covenant-record-row').first()).toBeVisible();
    await preserveUnrelatedNavigationBaseline(page);

    await expect(page).toHaveScreenshot('covenant-records-directory.png', { fullPage: true });
});

test('single Covenant Record presentation', async ({ page }) => {
    let fixture;

    try {
        fixture = await attachFeaturedImage(page, 'ligatura_covenant', 'charter-chest-three-mismatched-keys');
        await page.goto('/covenant-records/charter-chest-three-mismatched-keys/');
        await expect(page.locator('.manuscriptum-illuminatum-covenant-content')).toBeVisible();
        await expect(page.locator('.manuscriptum-illuminatum-article-campaign-image img')).toBeVisible();
        await preserveUnrelatedNavigationBaseline(page);

        await expect(page).toHaveScreenshot('covenant-record-entry.png', { fullPage: true });
    } finally {
        await restoreFeaturedImage(page, fixture);
    }
});

test('Covenant Records Entry Type collection presentation', async ({ page }) => {
    await page.goto('/covenant-records/type/artifact/');
    await expect(page.locator('.manuscriptum-illuminatum-directory-context')).toContainText('Artifact');
    await expect(page.locator('.manuscriptum-illuminatum-covenant-record-row').first()).toBeVisible();
    await preserveUnrelatedNavigationBaseline(page);

    await expect(page).toHaveScreenshot('covenant-records-type-collection.png', { fullPage: true });
});

test('Covenant Records Saga Topic collection presentation', async ({ page }) => {
    await page.goto('/covenant-records/topics/covenant-affairs/');
    await expect(page.locator('.manuscriptum-illuminatum-directory-context')).toContainText('Covenant Affairs');
    await expect(page.locator('.manuscriptum-illuminatum-covenant-record-row').first()).toBeVisible();
    await preserveUnrelatedNavigationBaseline(page);

    await expect(page).toHaveScreenshot('covenant-records-topic-collection.png', { fullPage: true });
});
