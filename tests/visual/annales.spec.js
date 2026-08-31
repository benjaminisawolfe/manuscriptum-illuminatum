const { test, expect } = require('@playwright/test');

test('Annales index presentation', async ({ page }) => {
    await page.goto('/news/');
    await expect(page.locator('.manuscriptum-illuminatum-annales-directory .manuscriptum-illuminatum-annal-row').first()).toBeVisible();

    await expect(page).toHaveScreenshot('annales-index.png', {
        fullPage: true,
        animations: 'disabled',
    });
});
