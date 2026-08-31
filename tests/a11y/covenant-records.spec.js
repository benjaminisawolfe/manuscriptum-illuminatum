const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

test('Covenant Records directory and expanded search have no detectable accessibility violations', async ({ page }) => {
    await page.goto('/covenant-records/');
    await expect(page).not.toHaveURL(/wp-login\.php/);
    await page.getByRole('button', { name: 'Advanced Search' }).click();

    const results = await new AxeBuilder({ page })
        .include('.manuscriptum-illuminatum-entry--content-directory')
        .analyze();

    expect(results.violations).toEqual([]);
});

test('single Covenant Record has no detectable accessibility violations', async ({ page }) => {
    await page.goto('/covenant-records/charter-chest-three-mismatched-keys/');
    await expect(page.locator('.manuscriptum-illuminatum-entry--covenant')).toBeVisible();

    const results = await new AxeBuilder({ page })
        .include('.manuscriptum-illuminatum-entry--covenant')
        .analyze();

    expect(results.violations).toEqual([]);
});

test('scoped Covenant Records collection has no detectable accessibility violations', async ({ page }) => {
    await page.goto('/covenant-records/type/artifact/');
    await page.getByRole('button', { name: 'Advanced Search' }).click();

    const results = await new AxeBuilder({ page })
        .include('.manuscriptum-illuminatum-entry--content-directory')
        .analyze();

    expect(results.violations).toEqual([]);
});
