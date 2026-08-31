const { test, expect } = require('@playwright/test');
const { getApiSettings, wpRequest } = require('../support/personae-fixture');
const { preserveUnrelatedNavigationBaseline } = require('./support');

async function createFrontPageSpeculumFixtures(page) {
	const api = await getApiSettings(page);
	const typeSlugs = ['event', 'mystery', 'artifact', 'place'];
	const titles = [
		'A New Leaf in the Winter Register',
		'The Lantern Below the Archive Stair',
		'Four Seals on the Guest-Room Door',
		'A Letter Carried Against the Current',
	];
	const ids = [];

	for (let index = 0; index < titles.length; index += 1) {
		const terms = await wpRequest(page, api, `wp/v2/ligatura_entry_type?slug=${typeSlugs[index]}`);
		const entry = await wpRequest(page, api, 'wp/v2/ligatura_wiki', {
			method: 'POST',
			body: {
				title: titles[index],
				content: `<p>${titles[index]} is a stable visual fixture for the front-page card rhythm.</p>`,
				excerpt: `A concise archive note concerning ${titles[index].toLowerCase()}.`,
				status: 'publish',
				ligatura_entry_type: [terms[0].id],
			},
		});
		ids.push(entry.id);
	}

	return { api, ids };
}

async function deleteFrontPageSpeculumFixtures(page, fixture) {
	if (!fixture) return;
	for (const id of fixture.ids) {
		await wpRequest(page, fixture.api, `wp/v2/ligatura_wiki/${id}?force=true`, { method: 'DELETE' });
	}
}

test('front page presentation', async ({ page }) => {
	let fixture;

	try {
		fixture = await createFrontPageSpeculumFixtures(page);
		await page.goto('/');
		await expect(page.locator('.manuscriptum-illuminatum-front-wiki .manuscriptum-illuminatum-card--wiki-update')).toHaveCount(4);
		await page.addStyleTag({ content: '.manuscriptum-illuminatum-front-wiki .manuscriptum-illuminatum-card__modified { display: none !important; }' });

		await expect(page).toHaveScreenshot('front-page-speculum.png', {
			fullPage: true,
			animations: 'disabled',
		});
	} finally {
		await deleteFrontPageSpeculumFixtures(page, fixture);
	}
});

test('Speculum directory appearance', async ({ page }) => {
    await page.goto('/wiki/');
	await preserveUnrelatedNavigationBaseline(page);

    await expect(page).toHaveScreenshot('wiki-archive.png', {
        fullPage: true,
        animations: 'disabled',
    });
});

test('Speculum Saga Topic collection appearance', async ({ page }) => {
    await page.goto('/wiki/topics/politics/');
    await expect(page.locator('.manuscriptum-illuminatum-saga-topic-directory .manuscriptum-illuminatum-speculum-teaser').first()).toBeVisible();
	await preserveUnrelatedNavigationBaseline(page);

    await expect(page).toHaveScreenshot('speculum-saga-topic.png', {
        fullPage: true,
        animations: 'disabled',
    });
});

test('generic Saga Topic archive appearance', async ({ page }) => {
    await page.goto('/saga-topic/covenant-affairs/');
    await expect(page.locator('.manuscriptum-illuminatum-card-grid .manuscriptum-illuminatum-card').first()).toBeVisible();

    await expect(page.locator('.manuscriptum-illuminatum-page-header')).toHaveScreenshot('generic-saga-topic-header.png', {
        animations: 'disabled',
    });
});

test('single Speculum entry presentation', async ({ page }) => {
    await page.goto('/wiki/covenant-of-the-quiet-bell/');
    await expect(page.locator('.manuscriptum-illuminatum-entry--wiki')).toBeVisible();
	await preserveUnrelatedNavigationBaseline(page);

    await expect(page).toHaveScreenshot('speculum-entry.png', {
        fullPage: true,
        animations: 'disabled',
    });
});
