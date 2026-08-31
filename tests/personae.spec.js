const { test, expect } = require('@playwright/test');
const {
    createPersonaeImageFixture,
    restorePersonaeImageFixture,
} = require('./support/personae-fixture');

function titleSortKey(title) {
    return title.trim().replace(/^the\s+/i, '');
}

function sortedTitles(titles) {
    return [...titles].sort((left, right) => {
        const comparison = titleSortKey(left).localeCompare(titleSortKey(right), undefined, {
            numeric: true,
            sensitivity: 'base',
        });

        return comparison || left.localeCompare(right, undefined, { numeric: true, sensitivity: 'base' });
    });
}

test('Personae directory groups readable characters by canonical Character Type', async ({ page }) => {
    await page.goto('/characters/');

    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expect(page).toHaveURL(/\/characters\/?$/);
    await expect(page.getByRole('heading', { level: 1, name: /^Personae$/i })).toBeVisible();
    await expect(page.getByText(/editable Personae page introduces/i)).toBeVisible();

    const directory = page.locator('.manuscriptum-illuminatum-personae-directory');
    const groups = directory.locator('.manuscriptum-illuminatum-personae-group');

    await expect(directory).toBeVisible();
    expect(await groups.count()).toBeGreaterThanOrEqual(3);
    await expect(directory.getByRole('heading', { level: 2, name: /^Magus$/i })).toBeVisible();
    await expect(directory.getByRole('heading', { level: 2, name: /^Companion$/i })).toBeVisible();
    await expect(directory.getByRole('heading', { level: 2, name: /^(Grog|NPC)$/i }).first()).toBeVisible();

    const groupData = await groups.evaluateAll((items) => items.map((group) => ({
        heading: group.querySelector('h2')?.textContent.trim() || '',
        slug: group.dataset.characterType || '',
        titles: [...group.querySelectorAll('.manuscriptum-illuminatum-persona-teaser h3 a')]
            .map((link) => link.textContent.trim())
            .filter(Boolean),
        cardTypeLabels: [...group.querySelectorAll('.manuscriptum-illuminatum-persona-teaser .manuscriptum-illuminatum-card__meta')]
            .map((meta) => meta.textContent.trim())
            .filter(Boolean),
    })));

    for (const group of groupData) {
        expect(group.heading).not.toBe('');
        expect(group.slug).not.toBe('');
        expect(group.titles.length).toBeGreaterThan(0);
        expect(group.titles).toEqual(sortedTitles(group.titles));
        expect(group.cardTypeLabels.every((label) => label.toLocaleLowerCase() !== group.heading.toLocaleLowerCase())).toBe(true);
    }

    const terms = await page.evaluate(async () => {
        const response = await fetch('/wp-json/wp/v2/ligatura_character_type?per_page=100&hide_empty=false');

        if (!response.ok) {
            throw new Error(`Character Type request failed with ${response.status}`);
        }

        return response.json();
    });
    const renderedSlugs = groupData.map((group) => group.slug);

    for (const term of terms.filter((item) => item.count === 0)) {
        expect(renderedSlugs).not.toContain(term.slug);
    }
});

test('Personae directory uses responsive two-column teasers with optional campaign images', async ({ page }, testInfo) => {
    test.setTimeout(60_000);
    let fixture;

    try {
        fixture = await createPersonaeImageFixture(page);
        await page.goto('/characters/');

        await expect(page).not.toHaveURL(/wp-login\.php/);

        const directory = page.locator('.manuscriptum-illuminatum-personae-directory');
        const withImage = directory.locator('.manuscriptum-illuminatum-persona-teaser--with-image');
        const withoutImage = directory.locator('.manuscriptum-illuminatum-persona-teaser--without-image');

        await expect(withImage.first()).toBeVisible();
        await expect(withoutImage.first()).toBeVisible();

        const image = withImage.first().locator('.manuscriptum-illuminatum-persona-teaser__image');
        await expect(image).toBeVisible();
        await expect(image).toHaveAttribute('alt', 'Gold key on a blue heraldic shield');
        await expect(image).toHaveAttribute('sizes', /112px/);
        expect(await image.getAttribute('src')).not.toContain('default-character');
        expect(await image.evaluate((element) => element.complete)).toBe(true);
        expect(await image.evaluate((element) => element.naturalWidth)).toBeGreaterThan(0);
        expect(await image.evaluate((element) => element.naturalHeight)).toBeGreaterThan(0);
        await expect(withoutImage.first().locator('img')).toHaveCount(0);

        const layout = await directory.evaluate((element) => {
            const grids = [...element.querySelectorAll('.manuscriptum-illuminatum-personae-teaser-grid')];
            const grid = grids.find((candidate) => candidate.querySelectorAll('.manuscriptum-illuminatum-persona-teaser').length > 1);

            if (!grid) {
                return null;
            }

            const cards = [...grid.querySelectorAll('.manuscriptum-illuminatum-persona-teaser')].slice(0, 2);
            const boxes = cards.map((card) => card.getBoundingClientRect());

            return {
                columnCount: getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length,
                firstX: boxes[0].x,
                firstY: boxes[0].y,
                secondX: boxes[1].x,
                secondY: boxes[1].y,
                viewportOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            };
        });

        expect(layout).not.toBeNull();
        expect(layout.viewportOverflow).toBeLessThanOrEqual(0);

        if (testInfo.project.name === 'mobile') {
            expect(layout.columnCount).toBe(1);
            expect(layout.secondY).toBeGreaterThan(layout.firstY);
        } else {
            expect(layout.columnCount).toBe(2);
            expect(Math.abs(layout.secondY - layout.firstY)).toBeLessThanOrEqual(2);
            expect(layout.secondX).toBeGreaterThan(layout.firstX);
        }
    } finally {
        await restorePersonaeImageFixture(page, fixture);
    }
});
