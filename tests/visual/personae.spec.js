const { test, expect } = require('@playwright/test');
const {
    createPersonaeImageFixture,
    restorePersonaeImageFixture,
} = require('../support/personae-fixture');
const { preserveUnrelatedNavigationBaseline } = require('./support');

test('Personae directory grouped by Character Type', async ({ page }) => {
    test.setTimeout(60_000);
    let fixture;

    try {
        fixture = await createPersonaeImageFixture(page);
        await page.goto('/characters/');
        await expect(page.locator('.manuscriptum-illuminatum-personae-directory .manuscriptum-illuminatum-personae-group').first()).toBeVisible();
		await preserveUnrelatedNavigationBaseline(page);

        await expect(page).toHaveScreenshot('personae-directory.png', {
            fullPage: true,
            animations: 'disabled',
        });
    } finally {
        await restorePersonaeImageFixture(page, fixture);
    }
});

test('single Persona compact entry presentation', async ({ page }) => {
    test.setTimeout(60_000);
    let fixture;

    try {
        fixture = await createPersonaeImageFixture(page, {
            meta: {
                ligatura_apparent_age: 32,
                ligatura_occupation: 'Hermetic researcher',
            },
        });
        await page.goto('/characters/aveline-of-bonisagus/');
        await expect(page.locator('.manuscriptum-illuminatum-persona-header')).toBeVisible();
		await preserveUnrelatedNavigationBaseline(page);

        await expect(page).toHaveScreenshot('persona-entry.png', {
            fullPage: true,
            animations: 'disabled',
        });
    } finally {
        await restorePersonaeImageFixture(page, fixture);
    }
});
