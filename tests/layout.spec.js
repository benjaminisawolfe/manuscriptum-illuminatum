const { test, expect } = require('@playwright/test');

async function expectContentToFillTrack(content, track) {
    await expect(content).toBeVisible();
    await expect(track).toBeVisible();

    const [contentBox, trackBox, contentStyle] = await Promise.all([
        content.boundingBox(),
        track.boundingBox(),
        content.evaluate((element) => {
            const style = getComputedStyle(element);

            return {
                maxWidth: style.maxWidth,
                minWidth: style.minWidth,
                viewportOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            };
        }),
    ]);

    expect(contentBox).not.toBeNull();
    expect(trackBox).not.toBeNull();
    expect(Math.abs(contentBox.x - trackBox.x)).toBeLessThanOrEqual(2);
    expect(Math.abs(contentBox.width - trackBox.width)).toBeLessThanOrEqual(2);
    expect(contentStyle.maxWidth).toBe('none');
    expect(contentStyle.minWidth).toBe('0px');
    expect(contentStyle.viewportOverflow).toBeLessThanOrEqual(0);
}

test('front page title sits close to its rule without touching it', async ({ page }) => {
    await page.goto('/');

    await expect(page).not.toHaveURL(/wp-login\.php/);

    const gap = await page.locator('.manuscriptum-illuminatum-page-header').evaluate((el) => {
        const title = el.querySelector('h1');
        const headerStyle = getComputedStyle(el);

        if (!title) {
            return null;
        }

        return el.getBoundingClientRect().bottom
            - parseFloat(headerStyle.borderBottomWidth)
            - title.getBoundingClientRect().bottom;
    });

    expect(gap).not.toBeNull();
    expect(gap).toBeGreaterThanOrEqual(8);
    expect(gap).toBeLessThanOrEqual(12);
});

test('primary page title rule spacing below the rule stays compact', async ({ page }) => {
    await page.goto('/wiki/');

    await expect(page).not.toHaveURL(/wp-login\.php/);

    const marginBottom = await page.locator('.manuscriptum-illuminatum-page-header').evaluate(
        el => parseFloat(getComputedStyle(el).marginBottom)
    );

    const viewport = page.viewportSize();
    const expectedMaximum = viewport && viewport.width < 760 ? 18.5 : 36.5;

    expect(marginBottom).toBeLessThanOrEqual(expectedMaximum);
});

test('normal editor content fills its assigned track on Home and a single Annal', async ({ page }) => {
    await page.goto('/');

    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expectContentToFillTrack(
        page.locator('.manuscriptum-illuminatum-front-content .manuscriptum-illuminatum-content'),
        page.locator('.manuscriptum-illuminatum-layout__content')
    );

    await page.goto('/news/');
    const annalUrl = await page
        .locator('.manuscriptum-illuminatum-annales-directory .manuscriptum-illuminatum-annal-row h2 a')
        .first()
        .getAttribute('href');

    expect(annalUrl).toBeTruthy();
    await page.goto(annalUrl);
    await expectContentToFillTrack(
        page.locator('.manuscriptum-illuminatum-entry > .manuscriptum-illuminatum-content'),
        page.locator('.manuscriptum-illuminatum-entry')
    );
});

const contentDirectories = [
    { root: 'wiki', listing: '.manuscriptum-illuminatum-content-index' },
    { root: 'characters', listing: '.manuscriptum-illuminatum-content-index' },
    { root: 'journals', listing: '.manuscriptum-illuminatum-journal-directory' },
    { root: 'covenant-records', listing: '.manuscriptum-illuminatum-covenant-directory' },
];

for (const directory of contentDirectories) {
    test(`${directory.root} introduction and listing share the directory content width`, async ({ page }) => {
        await page.goto(`/${directory.root}/`);

        await expect(page).not.toHaveURL(/wp-login\.php/);

        const entry = page.locator('.manuscriptum-illuminatum-entry--content-directory');
        const header = entry.locator('.manuscriptum-illuminatum-page-header');
        const content = entry.locator('.manuscriptum-illuminatum-content');
        const introduction = content.locator(':scope > p').first();
        const listing = content.locator(directory.listing);

        await expect(entry).toBeVisible();
        await expect(introduction).toBeVisible();
        await expect(listing).toBeVisible();

        const [headerBox, contentBox, introBox, listingBox] = await Promise.all([
            header.boundingBox(),
            content.boundingBox(),
            introduction.boundingBox(),
            listing.boundingBox(),
        ]);

        expect(Math.abs(contentBox.x - headerBox.x)).toBeLessThanOrEqual(2);
        expect(Math.abs(contentBox.width - headerBox.width)).toBeLessThanOrEqual(2);
        expect(Math.abs(introBox.width - contentBox.width)).toBeLessThanOrEqual(2);
        expect(Math.abs(listingBox.width - contentBox.width)).toBeLessThanOrEqual(2);

        if (directory.root === 'journals' || directory.root === 'covenant-records') {
            const searchBox = await content.locator('.manuscriptum-illuminatum-journal-search').boundingBox();
            expect(Math.abs(searchBox.width - contentBox.width)).toBeLessThanOrEqual(2);
        }
    });
}

const dropCapPages = [
    { name: 'Speculum directory', path: '/wiki/' },
    { name: 'single Speculum entry', path: '/wiki/broken-bell-saint-remigius/' },
];

for (const target of dropCapPages) {
    test(`${target.name} keeps its drop cap without wrapping later lines around it`, async ({ page }) => {
        await page.goto(target.path);

        await expect(page).not.toHaveURL(/wp-login\.php/);

        const paragraph = page.locator('.manuscriptum-illuminatum-content > p').first();
        await expect(paragraph).toBeVisible();

        const layout = await paragraph.evaluate((element) => {
            const paragraphStyle = getComputedStyle(element);
            const initialStyle = getComputedStyle(element, '::first-letter');
            const paragraphBox = element.getBoundingClientRect();
            const textCharacters = [];
            const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
            let skippedInitial = false;
            let node = walker.nextNode();

            while (node) {
                for (let offset = 0; offset < node.textContent.length; offset += 1) {
                    const character = node.textContent[offset];

                    if (!/\S/.test(character)) {
                        continue;
                    }

                    if (!skippedInitial) {
                        skippedInitial = true;
                        continue;
                    }

                    const range = document.createRange();
                    range.setStart(node, offset);
                    range.setEnd(node, offset + 1);
                    const box = range.getBoundingClientRect();

                    textCharacters.push({ left: box.left, top: box.top });
                }

                node = walker.nextNode();
            }

            const lines = [];

            for (const character of textCharacters) {
                let line = lines.find((candidate) => Math.abs(candidate.top - character.top) <= 2);

                if (!line) {
                    line = { left: character.left, top: character.top };
                    lines.push(line);
                } else {
                    line.left = Math.min(line.left, character.left);
                }
            }

            lines.sort((left, right) => left.top - right.top);

            const selection = window.getSelection();
            const selectionRange = document.createRange();
            selectionRange.selectNodeContents(element);
            selection.removeAllRanges();
            selection.addRange(selectionRange);
            const selectedText = selection.toString().replace(/\s+/g, ' ').trim();
            selection.removeAllRanges();

            const colourProbe = document.createElement('span');
            colourProbe.style.color = getComputedStyle(document.documentElement).getPropertyValue('--manuscriptum-illuminatum-red');
            document.body.appendChild(colourProbe);
            const rubricColour = getComputedStyle(colourProbe).color;
            colourProbe.remove();

            return {
                firstLetter: {
                    color: initialStyle.color,
                    content: initialStyle.content,
                    float: initialStyle.cssFloat,
                    fontFamily: initialStyle.fontFamily,
                    fontSize: parseFloat(initialStyle.fontSize),
                    lineHeight: parseFloat(initialStyle.lineHeight),
                },
                lineStarts: lines.map((line) => line.left),
                paragraphFontSize: parseFloat(paragraphStyle.fontSize),
                paragraphLeft: paragraphBox.left,
                rubricColour,
                selectedText,
                text: element.innerText.replace(/\s+/g, ' ').trim(),
                viewportOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            };
        });

        expect(layout.firstLetter.color).toBe(layout.rubricColour);
        expect(layout.firstLetter.float).toBe('none');
        expect(layout.firstLetter.fontFamily).toContain('Uncial Antiqua');
        expect(layout.firstLetter.fontSize).toBeGreaterThan(layout.paragraphFontSize * 2.5);
        expect(layout.firstLetter.lineHeight).toBe(0);
        expect(layout.firstLetter.content).toBe('normal');
        expect(layout.lineStarts.length).toBeGreaterThan(0);
        expect(layout.lineStarts[0]).toBeGreaterThan(layout.paragraphLeft + 10);

        for (const laterLineStart of layout.lineStarts.slice(1)) {
            expect(Math.abs(laterLineStart - layout.paragraphLeft)).toBeLessThanOrEqual(2);
        }

        expect(layout.viewportOverflow).toBeLessThanOrEqual(0);
        expect(layout.selectedText).toBe(layout.text);
    });
}
