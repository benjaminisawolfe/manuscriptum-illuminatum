const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

for (const [label, path] of [['Home', '/'], ['Annales', '/news/']]) {
    test(`${label} has no automatically detectable accessibility violations`, async ({ page }) => {
        await page.goto(path);
        await expect(page).not.toHaveURL(/wp-login\.php/);

        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
            .analyze();

        expect(results.violations).toEqual([]);
    });
}
