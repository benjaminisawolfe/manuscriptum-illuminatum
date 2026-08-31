const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

test('Speculum directory has no automatically detectable accessibility violations', async ({ page }) => {
    await page.goto('/wiki/');
    await page.getByRole('button', { name: 'Advanced Search' }).click();

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

test('single Speculum entry and Saga Topic links have no automatically detectable accessibility violations', async ({ page }) => {
    await page.goto('/wiki/covenant-of-the-quiet-bell/');

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

test('generic Saga Topic archive has no automatically detectable accessibility violations', async ({ page }) => {
    await page.goto('/saga-topic/covenant-affairs/');

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
