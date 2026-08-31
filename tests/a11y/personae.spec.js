const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

test('grouped Personae directory has no automatically detectable accessibility violations', async ({ page }) => {
    await page.goto('/characters/');
    await expect(page.locator('.manuscriptum-illuminatum-personae-directory .manuscriptum-illuminatum-persona-teaser').first()).toBeVisible();
    await page.getByRole('button', { name: 'Advanced Search' }).click();
    await expect(page.locator('#personae-advanced-search')).toBeVisible();

    const results = await new AxeBuilder({ page })
        .withTags([
            'wcag2a',
            'wcag2aa',
            'wcag21a',
            'wcag21aa',
        ])
        .analyze();

    expect(results.violations).toEqual([]);
});

test('single Persona has no automatically detectable accessibility violations', async ({ page }) => {
    await page.goto('/characters/aveline-of-bonisagus/');
    await expect(page.locator('.manuscriptum-illuminatum-entry--character')).toBeVisible();

    const results = await new AxeBuilder({ page })
        .withTags([
            'wcag2a',
            'wcag2aa',
            'wcag21a',
            'wcag21aa',
        ])
        .analyze();

    expect(results.violations).toEqual([]);
});
