const { test, expect } = require('@playwright/test');

test('Paginae Manuscripti Illuminati typography is actually applied', async ({ page }) => {
    await page.goto('/wiki/');

    const bodyFont = await page.locator('body').evaluate(
        el => getComputedStyle(el).fontFamily
    );

    expect(bodyFont).toContain('EB Garamond');

    const title = page.getByRole('heading', {
        name: /speculum/i
    });

    const titleFont = await title.evaluate(
        el => getComputedStyle(el).fontFamily
    );

    expect(titleFont).toContain('Cinzel');

    const titleSize = await title.evaluate(
        el => parseFloat(getComputedStyle(el).fontSize)
    );

    expect(titleSize).toBeLessThanOrEqual(32);
});
