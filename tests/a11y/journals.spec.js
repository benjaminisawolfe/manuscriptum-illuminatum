const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { getJournalPersonaCollection } = require('../helpers/journal-persona');

test('Commentarii directory and expanded search have no detectable accessibility violations', async ({ page }) => {
    await page.goto('/journals/');
    await expect(page).not.toHaveURL(/wp-login\.php/);

    await page.getByRole('button', { name: 'Advanced Search' }).click();

    const results = await new AxeBuilder({ page })
        .include('.manuscriptum-illuminatum-entry--content-directory')
        .analyze();

    expect(results.violations).toEqual([]);
});

test('single Commentarium has no detectable accessibility violations', async ({ page }) => {
    await page.goto('/journals/guarin-begins-courteous-audit/');
    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expect(page.locator('.manuscriptum-illuminatum-entry--diary')).toBeVisible();

    const results = await new AxeBuilder({ page })
        .include('.manuscriptum-illuminatum-entry--diary')
        .analyze();

    expect(results.violations).toEqual([]);
});

test('Persona-specific Commentarii collection has no detectable accessibility violations', async ({ page }) => {
    const persona = await getJournalPersonaCollection(page);
    await page.goto(persona.path);
    await expect(page.locator('.manuscriptum-illuminatum-commentarii-persona-context')).toBeVisible();

    const results = await new AxeBuilder({ page })
        .include('.manuscriptum-illuminatum-entry--content-directory')
        .analyze();

    expect(results.violations).toEqual([]);
});
