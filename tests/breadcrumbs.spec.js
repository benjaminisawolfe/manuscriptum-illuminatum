const { test, expect } = require('@playwright/test');
const { getApiSettings, wpRequest } = require('./support/personae-fixture');
const { getJournalPersonaCollection } = require('./helpers/journal-persona');

const breadcrumb = (page) => page.getByRole('navigation', { name: 'Breadcrumb' });

async function expectTrail(page, expected) {
    const trail = breadcrumb(page);
    await expect(trail).toBeVisible();
    await expect(trail.locator('.manuscriptum-illuminatum-breadcrumb__label')).toHaveText(expected);

    const current = trail.locator('[aria-current="page"]');
    await expect(current).toHaveCount(1);
    await expect(current).toHaveText(expected.at(-1));
    expect(await current.evaluate((element) => element.tagName)).toBe('SPAN');
    await expect(current.locator('a')).toHaveCount(0);
}

test('the front page does not repeat Home as a breadcrumb', async ({ page }) => {
    await page.goto('/');

    await expect(page).not.toHaveURL(/wp-login\.php/);
    await expect(breadcrumb(page)).toHaveCount(0);
});

const exactRoutes = [
	['/news/', ['Home', 'Annales']],
	[
		'/news/notes-from-the-salt-barge/',
		['Home', 'Annales', 'Notes from the Salt Barge'],
	],
    ['/wiki/', ['Home', 'Speculum']],
    ['/wiki/artifact/', ['Home', 'Speculum', 'Artifact']],
    ['/wiki/topics/politics/', ['Home', 'Speculum', 'Politics']],
    ['/saga-topic/covenant-affairs/', ['Home', 'Covenant Affairs']],
    ['/characters/', ['Home', 'Personae']],
    ['/characters/aveline-of-bonisagus/', ['Home', 'Personae', 'Aveline of Bonisagus']],
    ['/journals/', ['Home', 'Commentarii']],
    [
        '/journals/guarin-begins-courteous-audit/',
        ['Home', 'Commentarii', 'Guarin of Tremere Begins a Courteous Audit of Every Locked Chest'],
    ],
    ['/covenant-records/', ['Home', 'Covenant Records']],
    ['/covenant-records/type/artifact/', ['Home', 'Covenant Records', 'Artifact']],
    ['/covenant-records/topics/covenant-affairs/', ['Home', 'Covenant Records', 'Covenant Affairs']],
    [
        '/covenant-records/great-hall-lower-ward/',
        ['Home', 'Covenant Records', 'Great Hall of the Lower Ward'],
    ],
    ['/house-rules/', ['Home', 'House Rules']],
    [
		'/news/spring-rain-over-the-lower-bailey/',
        ['Home', 'Annales', 'Spring Rain over the Lower Bailey'],
    ],
];

test('a current Persona Commentarii collection has the expected breadcrumb hierarchy', async ({ page }) => {
    const persona = await getJournalPersonaCollection(page);
    await page.goto(persona.path);

    await expectTrail(page, ['Home', 'Commentarii', persona.name]);
});

for (const [path, expected] of exactRoutes) {
    test(`${path} has the expected breadcrumb hierarchy`, async ({ page }) => {
        await page.goto(path);

        await expect(page).not.toHaveURL(/wp-login\.php/);
        await expectTrail(page, expected);

        if (path.startsWith('/saga-topic/')) {
            await expect(breadcrumb(page)).not.toContainText('Speculum');
        }
    });
}

test('a single Speculum entry may use its one real Entry Type', async ({ page }) => {
    await page.goto('/wiki/covenant-of-the-quiet-bell/');

    const labels = await breadcrumb(page).locator('.manuscriptum-illuminatum-breadcrumb__label').allTextContents();
    expect(labels.slice(0, 2)).toEqual(['Home', 'Speculum']);
    expect(labels.at(-1)).toBe('Covenant of the Quiet Bell');
    expect(labels.length).toBeGreaterThanOrEqual(3);
    expect(labels.length).toBeLessThanOrEqual(4);
});

test('search and not-found requests use concise current-page labels', async ({ page }) => {
    await page.goto('/?s=Hermetic');
    await expectTrail(page, ['Home', 'Search Results']);

    await page.goto(`/fixture-breadcrumb-missing-${Date.now()}/`);
    await expect(page.locator('body')).toHaveClass(/error404/);
    await expectTrail(page, ['Home', 'Page Not Found']);
});

test('breadcrumb markup is outside the header, aligned with content, and its links resolve', async ({ page }) => {
    await page.goto('/wiki/covenant-of-the-quiet-bell/');

    const trail = breadcrumb(page);
    const siteHeader = page.locator('.site-header');
    const contentContainer = page.locator('.manuscriptum-illuminatum-layout__content');
    const [headerBox, trailBox, contentBox] = await Promise.all([
        siteHeader.boundingBox(),
        trail.boundingBox(),
        contentContainer.boundingBox(),
    ]);

    expect(headerBox).not.toBeNull();
    expect(trailBox).not.toBeNull();
    expect(contentBox).not.toBeNull();
    expect(trailBox.y).toBeGreaterThanOrEqual(headerBox.y + headerBox.height);
    expect(Math.abs(trailBox.x - contentBox.x)).toBeLessThanOrEqual(2);
    await expect(siteHeader.getByRole('navigation', { name: 'Breadcrumb' })).toHaveCount(0);

    const links = trail.locator('a');
    expect(await links.count()).toBeGreaterThan(0);
    const results = await links.evaluateAll(async (elements) => Promise.all(elements.map(async (element) => {
        const url = new URL(element.href);
        const response = await fetch(url, { credentials: 'same-origin' });
        return {
            sameOrigin: url.origin === window.location.origin,
            ok: response.ok,
            redirectedToLogin: response.url.includes('wp-login.php'),
        };
    })));

    expect(results.every((result) => result.sameOrigin && result.ok && !result.redirectedToLogin)).toBe(true);
});

test('long breadcrumbs wrap without horizontal overflow on mobile', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile', 'Mobile wrapping is verified at the mobile viewport.');
    await page.goto('/questions-before-tribunal-gathering/');

    const trail = breadcrumb(page);
    const measurements = await trail.evaluate((element) => {
        const list = element.querySelector('.manuscriptum-illuminatum-breadcrumb__list');
        const content = element.closest('.manuscriptum-illuminatum-layout__content');
        return {
            flexWrap: getComputedStyle(list).flexWrap,
            trailWidth: element.getBoundingClientRect().width,
            contentWidth: content.getBoundingClientRect().width,
            documentOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        };
    });

    expect(measurements.flexWrap).toBe('wrap');
    expect(measurements.trailWidth).toBeLessThanOrEqual(measurements.contentWidth + 1);
    expect(measurements.documentOverflow).toBeLessThanOrEqual(1);
    await expectTrail(page, [
        'Home',
        'Questions To Settle Before the Next Tribunal Gathering in Normandy',
    ]);
});

test('ordinary child Pages follow their editor-defined parent hierarchy', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The temporary WordPress fixture is needed only once.');
    test.setTimeout(60_000);

    const api = await getApiSettings(page);
    const parents = await wpRequest(page, api, 'wp/v2/pages?slug=house-rules&context=edit');
    const marker = Date.now();
    const title = `Breadcrumb Child ${marker}`;
    const slug = `fixture-breadcrumb-child-${marker}`;
    let child;

    expect(parents).toHaveLength(1);

    try {
        child = await wpRequest(page, api, 'wp/v2/pages', {
            method: 'POST',
            body: {
                title,
                slug,
                content: 'Temporary child Page used to verify editor-defined breadcrumb hierarchy.',
                parent: parents[0].id,
                status: 'publish',
            },
        });

        await page.goto(child.link);
        await expectTrail(page, ['Home', 'House Rules', title]);
        await expect(breadcrumb(page).getByRole('link', { name: 'House Rules' })).toHaveAttribute('href', /\/house-rules\/?$/);
    } finally {
        if (child) {
            await wpRequest(page, api, `wp/v2/pages/${child.id}?force=true`, { method: 'DELETE' });
        }
    }
});

test('a multi-type Speculum entry does not choose an arbitrary Entry Type', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The temporary WordPress mutation is needed only once.');
    test.setTimeout(60_000);

    const api = await getApiSettings(page);
    const entries = await wpRequest(page, api, 'wp/v2/ligatura_wiki?slug=covenant-of-the-quiet-bell&context=edit');
    const artifactTerms = await wpRequest(page, api, 'wp/v2/ligatura_entry_type?slug=artifact');
    const entry = entries[0];
    const originalTypes = [...entry.ligatura_entry_type];
    const multipleTypes = [...new Set([...originalTypes, artifactTerms[0].id])];

    expect(entry).toBeTruthy();
    expect(artifactTerms).toHaveLength(1);
    expect(multipleTypes.length).toBeGreaterThan(1);

    try {
        await wpRequest(page, api, `wp/v2/ligatura_wiki/${entry.id}`, {
            method: 'POST',
            body: { ligatura_entry_type: multipleTypes },
        });

        await page.goto('/wiki/covenant-of-the-quiet-bell/');
        await expectTrail(page, ['Home', 'Speculum', 'Covenant of the Quiet Bell']);
    } finally {
        await wpRequest(page, api, `wp/v2/ligatura_wiki/${entry.id}`, {
            method: 'POST',
            body: { ligatura_entry_type: originalTypes },
        });
    }
});
