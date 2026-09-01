async function preserveUnrelatedNavigationBaseline(page) {
	await page.locator('.site-brand__text small').evaluate((element) => {
		element.textContent = 'An Ars Magica Saga';
	});

	await page.addStyleTag({
		content: `
			.manuscriptum-illuminatum-front-content
			.manuscriptum-illuminatum-content > :nth-child(n + 3) {
				display: none !important;
			}
		`,
	});

	await page.locator('img[loading="lazy"]').evaluateAll((images) => {
		for (const image of images) {
			image.loading = 'eager';
		}
	});

	await page.waitForFunction(
		() => [...document.images].every((image) => image.complete && image.naturalWidth > 0),
		null,
		{ timeout: 10_000 }
	);
}

module.exports = { preserveUnrelatedNavigationBaseline };
