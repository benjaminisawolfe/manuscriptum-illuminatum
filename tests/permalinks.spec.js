const { test, expect } = require('@playwright/test');
const { getJournalPersonaCollection } = require('./helpers/journal-persona');

const directories = [
    {
        root: 'wiki',
        title: /^Speculum$/i,
        authoredText: /editable Speculum page introduces/i,
        singleSlug: 'covenant-of-the-quiet-bell',
        singleTitle: /Covenant of the Quiet Bell/i,
		wikiDirectory: true,
    },
    {
        root: 'characters',
        title: /^Personae$/i,
        authoredText: /editable Personae page introduces/i,
        singleSlug: 'aveline-of-bonisagus',
        singleTitle: /Aveline of Bonisagus/i,
		personaeDirectory: true,
    },
    {
        root: 'journals',
        title: /^Commentarii$/i,
        authoredText: /editable Commentarii page/i,
        singleSlug: 'guarin-begins-courteous-audit',
        singleTitle: /Guarin of Tremere Begins/i,
		journalDirectory: true,
    },
    {
        root: 'covenant-records',
        title: /^Covenant Records$/i,
        authoredText: /editable covenant-records page/i,
        singleSlug: 'library-damp-calfskin-ash',
        singleTitle: /Library of Damp Calfskin and Ash/i,
		covenantDirectory: true,
    },
];

function alpha(values) {
    return [...values].sort((a, b) => a.localeCompare(b));
}

for (const directory of directories) {
    test(`${directory.root} root is an editable Page with the expected content index`, async ({ page }) => {
        await page.goto(`/${directory.root}/`);

        await expect(page).not.toHaveURL(/wp-login\.php/);
        await expect(page).toHaveURL(new RegExp(`/${directory.root}/?$`));
        await expect(page).not.toHaveURL(/index\.php/);
        await expect(page.getByRole('heading', { level: 1, name: directory.title })).toBeVisible();
        await expect(page.getByText(directory.authoredText)).toBeVisible();

        const bodyClass = await page.locator('body').getAttribute('class');
        expect(bodyClass).toContain('page');
        expect(bodyClass).not.toContain('post-type-archive');

        const links = directory.journalDirectory
            ? page.locator('.manuscriptum-illuminatum-journal-directory .manuscriptum-illuminatum-journal-row h3 a')
            : directory.wikiDirectory
                ? page.locator('.manuscriptum-illuminatum-wiki-directory .manuscriptum-illuminatum-wiki-teaser-grid h3 a')
                : directory.personaeDirectory
                    ? page.locator('.manuscriptum-illuminatum-personae-directory .manuscriptum-illuminatum-persona-teaser h3 a')
                    : directory.covenantDirectory
                        ? page.locator('.manuscriptum-illuminatum-covenant-directory .manuscriptum-illuminatum-covenant-record-row h3 a')
                        : page.locator('.manuscriptum-illuminatum-content-index .manuscriptum-illuminatum-card h3 a');
        await expect(links.first()).toBeVisible();

        const titles = await links.evaluateAll((items) => items.map((item) => item.textContent.trim()).filter(Boolean));
        expect(titles.length).toBeGreaterThan(1);

        if (directory.journalDirectory) {
            const dates = await page.locator('.manuscriptum-illuminatum-journal-directory .manuscriptum-illuminatum-journal-row').evaluateAll(
                (items) => items.map((item) => item.dataset.sagaDate || '')
            );
            const dated = dates.filter(Boolean);
            const firstUndated = dates.indexOf('');

            expect(dated).toEqual([...dated].sort((a, b) => b.localeCompare(a)));

            if (firstUndated >= 0) {
                expect(dates.slice(firstUndated).every((date) => !date)).toBe(true);
            }
        } else if (directory.personaeDirectory) {
            const groupedTitles = await page.locator('.manuscriptum-illuminatum-personae-group').evaluateAll((groups) => groups.map(
                (group) => [...group.querySelectorAll('.manuscriptum-illuminatum-persona-teaser h3 a')].map((link) => link.textContent.trim())
            ));

            for (const groupTitles of groupedTitles) {
                expect(groupTitles).toEqual([...groupTitles].sort((left, right) => {
                    const leftKey = left.replace(/^the\s+/i, '');
                    const rightKey = right.replace(/^the\s+/i, '');
                    return leftKey.localeCompare(rightKey) || left.localeCompare(right);
                }));
            }
        } else if (directory.covenantDirectory) {
            const dates = await page.locator('.manuscriptum-illuminatum-covenant-directory .manuscriptum-illuminatum-covenant-record-row').evaluateAll(
                (items) => items.map((item) => item.dataset.sagaDate || '')
            );
            const dated = dates.filter(Boolean);
            const firstUndated = dates.indexOf('');
            expect(dated).toEqual([...dated].sort((left, right) => right.localeCompare(left)));
            if (firstUndated >= 0) {
                expect(dates.slice(firstUndated).every((date) => !date)).toBe(true);
            }
        } else if (!directory.wikiDirectory) {
            expect(titles).toEqual(alpha(titles));
        }

        const firstHref = await links.first().getAttribute('href');
        expect(firstHref).toContain(`/${directory.root}/`);
        expect(firstHref).not.toContain('/index.php/');
    });

    test(`${directory.root} single entries resolve beneath the editable root Page`, async ({ page }) => {
        await page.goto(`/${directory.root}/${directory.singleSlug}/`);

        await expect(page).not.toHaveURL(/wp-login\.php/);
        await expect(page).not.toHaveURL(/index\.php/);
        await expect(page.getByRole('heading', { name: directory.singleTitle })).toBeVisible();
        await expect(page.getByText(directory.authoredText)).toHaveCount(0);
    });
}

test('ordinary Pages remain top-level and do not receive a content index', async ({ page }) => {
    await page.goto('/house-rules/');

    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expect(page).toHaveURL(/\/house-rules\/?$/);
    await expect(page).not.toHaveURL(/index\.php|\/pages\//);
    await expect(page.getByRole('heading', { name: /^House Rules$/i })).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-content-index')).toHaveCount(0);
});

test('Annales singles and Persona Commentarii collections keep distinct canonical namespaces', async ({ page }) => {
    await page.goto('/news/notes-from-the-salt-barge/');
    await expect(page).toHaveURL(/\/news\/notes-from-the-salt-barge\/?$/);

    const persona = await getJournalPersonaCollection(page);
    await page.goto(persona.path);
    await expect(page.locator('.manuscriptum-illuminatum-commentarii-persona-context')).toBeVisible();
    await expect(page.locator('article.manuscriptum-illuminatum-entry--diary')).toHaveCount(0);

    await page.goto('/journals/guarin-begins-courteous-audit/');
    await expect(page.locator('article.manuscriptum-illuminatum-entry--diary')).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-commentarii-persona-context')).toHaveCount(0);
});
