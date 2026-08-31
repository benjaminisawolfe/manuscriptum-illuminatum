const { test, expect } = require('@playwright/test');
const { getJournalPersonaCollection } = require('./helpers/journal-persona');

test('authenticated WordPress test site loads', async ({ page }) => {
    await page.goto('/');

    await expect(page).not.toHaveURL(/wp-login\.php/);

    await expect(page.locator('body')).toBeVisible();
});

test('Annales Posts Page loads with ordinary Posts', async ({ page }) => {
	await page.goto('/news/');

	await expect(page).not.toHaveURL(/wp-login\.php/);
	await expect(page.getByRole('heading', { level: 1, name: /^Annales$/ })).toBeVisible();
	await expect(page.locator('.manuscriptum-illuminatum-annales-directory .manuscriptum-illuminatum-annal-row').first()).toBeVisible();
});

test('Speculum directory page loads', async ({ page }) => {
    await page.goto('/wiki/');

    await expect(page).not.toHaveURL(/wp-login\.php/);

    await expect(
        page.getByRole('heading', { name: /speculum/i })
    ).toBeVisible();

    await expect(page.locator('.manuscriptum-illuminatum-wiki-group .manuscriptum-illuminatum-speculum-teaser h3 a').first()).toBeVisible();
});

test('Personae directory page loads with Character Type groups', async ({ page }) => {
    await page.goto('/characters/');

    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expect(page.getByRole('heading', { level: 1, name: /^Personae$/i })).toBeVisible();
    await expect(page.getByRole('searchbox', { name: 'Search Personae' })).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-personae-group .manuscriptum-illuminatum-persona-teaser h3 a').first()).toBeVisible();
});

test('single Persona loads with the compact character header', async ({ page }) => {
    await page.goto('/characters/aveline-of-bonisagus/');

    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expect(page.locator('.manuscriptum-illuminatum-persona-header')).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-entry-hero')).toHaveCount(0);
});

test('Speculum Saga Topic collection loads', async ({ page }) => {
    await page.goto('/wiki/topics/covenant-affairs/');

    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expect(page.getByRole('heading', { level: 1, name: 'Covenant Affairs' })).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-saga-topic-directory .manuscriptum-illuminatum-speculum-teaser h3 a').first()).toBeVisible();
});

test('generic Saga Topic archive loads as a campaign archive', async ({ page }) => {
    await page.goto('/saga-topic/covenant-affairs/');

    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expect(page.getByRole('heading', { level: 1, name: /Covenant Affairs/ })).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-card-grid .manuscriptum-illuminatum-card').first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'Back to all Speculum entries' })).toHaveCount(0);
});

test('Persona-specific Commentarii collection loads without consuming the single-Journal namespace', async ({ page }) => {
    const persona = await getJournalPersonaCollection(page);
    await page.goto(persona.path);

    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expect(page.locator('.manuscriptum-illuminatum-commentarii-persona-context')).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-journal-row').first()).toBeVisible();
});

test('Covenant Records searchable directory loads', async ({ page }) => {
    await page.goto('/covenant-records/');

    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expect(page.locator('.manuscriptum-illuminatum-covenant-directory')).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-covenant-record-row').first()).toBeVisible();
});

test('Covenant Record scoped collections and single presentation load', async ({ page }) => {
    await page.goto('/covenant-records/type/artifact/');
    await expect(page.locator('.manuscriptum-illuminatum-directory-context')).toContainText('Artifact');
    await expect(page.locator('.manuscriptum-illuminatum-covenant-record-row').first()).toBeVisible();

    await page.goto('/covenant-records/topics/covenant-affairs/');
    await expect(page.locator('.manuscriptum-illuminatum-directory-context')).toContainText('Covenant Affairs');
    await expect(page.locator('.manuscriptum-illuminatum-covenant-record-row').first()).toBeVisible();

    await page.goto('/covenant-records/charter-chest-three-mismatched-keys/');
    await expect(page.locator('.manuscriptum-illuminatum-covenant-metadata')).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-covenant-content')).toBeVisible();
});
