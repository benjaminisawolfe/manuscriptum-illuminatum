const { test, expect } = require('./support/speculum-toc-fixture');
const AxeBuilder = require('@axe-core/playwright').default;

const tocSelector = '.manuscriptum-illuminatum-wiki-toc';

test('Speculum TOC includes authored H1 parents in order and excludes headings outside content', async ({ page, tocFixture }) => {
    test.setTimeout(90_000);
    const entry = await tocFixture.create(`
        <h1>1. Money</h1><h2>Units</h2><h3>Pennies</h3>
        <h1>2. Price Variation</h1><h2>Markets</h2><h4>Regional prices</h4>
    `);
    await tocFixture.setOutsideHeadings(entry);
    await tocFixture.visit(entry);
    await expect(page.locator('.manuscriptum-illuminatum-page-header h1')).toHaveText(entry.title.rendered);
    await expect(page.locator('.manuscriptum-illuminatum-summary h2')).toHaveText('Outside article summary');
    await expect(page.locator('.manuscriptum-illuminatum-storyguide-notes')).toContainText('Private fixture heading');
    const toc = page.locator(tocSelector);
    await expect(toc.locator('a')).toHaveText(['1. Money', 'Units', 'Pennies', '2. Price Variation', 'Markets', 'Regional prices']);
    const items = await toc.locator('li').evaluateAll(elements => elements.map(element => ({
        level: Number(element.className.match(/--level-(\d)/)[1]),
        indent: parseFloat(getComputedStyle(element).marginInlineStart),
    })));
    expect(items.map(item => item.level)).toEqual([1, 2, 3, 1, 2, 4]);
    expect(items[0].indent).toBe(0);
    expect(items[1].indent).toBeGreaterThan(items[0].indent);
    expect(items[2].indent).toBeGreaterThan(items[1].indent);
    expect(items[3].indent).toBe(items[0].indent);
    expect(items[5].indent).toBeGreaterThan(items[2].indent);
    await expect(toc.getByRole('link', { name: 'Contents', exact: true })).toHaveCount(0);
});

test('Speculum TOC persists maximum levels 1–6 including legacy 2/3/4', async ({ page, tocFixture }) => {
    test.setTimeout(180_000);
    const entry = await tocFixture.create('<h1>One</h1><h2>Two</h2><h3>Three</h3><h4>Four</h4><h5>Five</h5><h6>Six</h6><h1>Another one</h1>');
    await expect(tocFixture.depth.locator('option')).toHaveText(['H1 only', 'H1–H2', 'H1–H3', 'H1–H4', 'H1–H5', 'H1–H6']);
    for (const [depth, expected] of [
        [1, ['One', 'Another one']],
        [2, ['One', 'Two', 'Another one']],
        [3, ['One', 'Two', 'Three', 'Another one']],
        [4, ['One', 'Two', 'Three', 'Four', 'Another one']],
        [5, ['One', 'Two', 'Three', 'Four', 'Five', 'Another one']],
        [6, ['One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Another one']],
    ]) {
        await tocFixture.setSettings(depth);
        await tocFixture.visit(entry);
        await expect(page.locator(`${tocSelector} a`)).toHaveText(expected);
    }
});

test('Speculum TOC preserves authored fragments and creates deterministic collision-free heading targets', async ({ page, tocFixture }) => {
    const entry = await tocFixture.create(`
        <div id="repeat">Existing article target</div>
        <h1>Repeat</h1><h2>Repeat</h2>
        <h2>Later</h2><h3 id="later">Explicit later</h3>
        <h2 id="Coins:Mixed.Case%20">Coins <em>&amp; silver</em> &lt;gold&gt;</h2>
        <h2 id="authored-duplicate">First authored duplicate</h2>
        <h2 id="authored-duplicate">Second authored duplicate</h2>
        <h2 id="repeat">Heading colliding with nonheading</h2>
        <p><a class="existing-fragment" href="#later">Existing article link</a></p>
    `);
    await tocFixture.visit(entry);
    const links = page.locator(`${tocSelector} a`);
    await expect(links).toHaveCount(8);
    await expect(links.nth(4)).toHaveText('Coins & silver <gold>');
    await expect(links.locator('em, strong, a, script')).toHaveCount(0);
    const headings = page.locator('.manuscriptum-illuminatum-wiki-content > :is(h1,h2,h3)');
    const ids = await headings.evaluateAll(elements => elements.map(element => element.id));
    expect(ids).toEqual(['repeat-2', 'repeat-3', 'later-2', 'later', 'Coins:Mixed.Case%20', 'authored-duplicate', 'authored-duplicate-2', 'repeat-4']);
    const allIds = await page.locator('.manuscriptum-illuminatum-wiki-content [id]').evaluateAll(elements => elements.map(element => element.id));
    expect(new Set(allIds).size).toBe(allIds.length);
    for (let index = 0; index < ids.length; index++) {
        await links.nth(index).click();
        expect(await page.evaluate(() => decodeURIComponent(location.hash.slice(1)))).toBe(ids[index]);
        expect(await page.evaluate(() => document.querySelector(':target')?.id)).toBe(ids[index]);
    }
    await page.locator('.existing-fragment').click();
    expect(await page.evaluate(() => document.querySelector(':target')?.id)).toBe('later');
    await page.reload();
    expect(await headings.evaluateAll(elements => elements.map(element => element.id))).toEqual(ids);
});

test('Speculum TOC keeps the two-heading threshold, disabled behavior and non-Speculum content', async ({ page, tocFixture }) => {
    const single = await tocFixture.create('<h1>Only eligible heading</h1><h5>Outside maximum</h5>');
    await tocFixture.visit(single);
    await expect(page.locator(tocSelector)).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Only eligible heading' })).not.toHaveAttribute('id');
    const wiki = await tocFixture.create('<h1>First</h1><h2>Second</h2>');
    await tocFixture.visit(wiki);
    await expect(page.locator(`${tocSelector} a`)).toHaveCount(2);
    await tocFixture.setSettings(4, false);
    await tocFixture.visit(wiki);
    await expect(page.locator(tocSelector)).toHaveCount(0);
    await tocFixture.setSettings(4);
    const ordinary = await tocFixture.create('<h1>First</h1><h2>Second</h2>', 'posts');
    await tocFixture.visit(ordinary);
    await expect(page.locator(tocSelector)).toHaveCount(0);
});

test('Speculum TOC has six readable levels, wrapping and keyboard links without overflow', async ({ page, tocFixture }, testInfo) => {
    test.setTimeout(120_000);
    await tocFixture.setSettings(6);
    // Authored word-break opportunities keep the body readable; the TOC's plain
    // text must also wrap after those inline elements have been stripped.
    const longText = 'Deep heading with a long description and ' + 'unbroken<wbr>'.repeat(18);
    const entry = await tocFixture.create(`<h1>Money</h1><h2>Coins</h2><h3>Silver</h3><h4>Weights</h4><h5>Measures</h5><h6>${longText}</h6>`);
    await tocFixture.visit(entry);
    const toc = page.locator(tocSelector);
    await expect(toc.locator('a')).toHaveCount(6);
    const indents = await toc.locator('li').evaluateAll(elements => elements.map(element => parseFloat(getComputedStyle(element).marginInlineStart)));
    expect(indents[0]).toBe(0);
    for (let index = 1; index < indents.length; index++) expect(indents[index]).toBeGreaterThan(indents[index - 1]);
    expect(await toc.evaluate(element => element.scrollWidth <= element.clientWidth + 1)).toBe(true);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    const last = toc.locator('a').last();
    await toc.locator('a').first().focus();
    for (let index = 1; index < 6; index++) await page.keyboard.press('Tab');
    await expect(last).toBeFocused();
    expect(await last.evaluate(element => getComputedStyle(element).outlineStyle)).not.toBe('none');
    await page.keyboard.press('Enter');
    expect(await page.evaluate(() => document.querySelector(':target')?.tagName)).toBe('H6');
    const results = await new AxeBuilder({ page }).include(tocSelector).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    expect(results.violations).toEqual([]);
    await toc.scrollIntoViewIfNeeded();
    await toc.screenshot({ path: testInfo.outputPath('toc-six-levels.png') });
});
