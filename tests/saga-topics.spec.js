const { test, expect } = require('@playwright/test');

test('Speculum and generic Saga Topic routes preserve their distinct scopes', async ({ page }) => {
    await page.goto('/wiki/covenant-of-the-quiet-bell/');

    const speculumTopic = page.locator('.manuscriptum-illuminatum-meta-list--plain dd a', { hasText: 'Covenant Affairs' });
    await expect(speculumTopic).toHaveAttribute('href', /\/wiki\/topics\/covenant-affairs\/?$/);

    await speculumTopic.click();
    await expect(page).toHaveURL(/\/wiki\/topics\/covenant-affairs\/?$/);
    await expect(page.locator('.manuscriptum-illuminatum-saga-topic-directory .manuscriptum-illuminatum-speculum-teaser').first()).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-saga-topic-directory .manuscriptum-illuminatum-card--diary, .manuscriptum-illuminatum-saga-topic-directory .manuscriptum-illuminatum-card--character, .manuscriptum-illuminatum-saga-topic-directory .manuscriptum-illuminatum-card--covenant')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Back to all Speculum entries' })).toHaveAttribute('href', /\/wiki\/?$/);

    await page.goto('/covenant-records/charter-chest-three-mismatched-keys/');
    const covenantTopic = page.locator('.manuscriptum-illuminatum-covenant-metadata a', { hasText: 'Covenant Affairs' });
    await expect(covenantTopic).toHaveAttribute('href', /\/covenant-records\/topics\/covenant-affairs\/?$/);
    await covenantTopic.click();
    await expect(page).toHaveURL(/\/covenant-records\/topics\/covenant-affairs\/?$/);
    await expect(page.locator('.manuscriptum-illuminatum-covenant-directory .manuscriptum-illuminatum-covenant-record-row').first()).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-covenant-directory .manuscriptum-illuminatum-speculum-teaser, .manuscriptum-illuminatum-covenant-directory .manuscriptum-illuminatum-card--diary')).toHaveCount(0);

    await page.goto('/journals/guarin-begins-courteous-audit/');

    const journalTopic = page.locator('.manuscriptum-illuminatum-journal-footer__topics a', { hasText: 'Covenant Affairs' });
    await expect(journalTopic).toHaveAttribute('href', /\/saga-topic\/covenant-affairs\/?$/);
    expect(new URL(await journalTopic.getAttribute('href')).pathname).toBe('/saga-topic/covenant-affairs/');

    await journalTopic.click();
    await expect(page).toHaveURL(/\/saga-topic\/covenant-affairs\/?$/);
    await expect(page.getByRole('heading', { level: 1, name: /Covenant Affairs/ })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Back to all Speculum entries' })).toHaveCount(0);
    await expect(page.locator('.manuscriptum-illuminatum-saga-topic-directory')).toHaveCount(0);

    const cardTypes = new Set();
    let pageCount = 0;

    while (pageCount < 10) {
        pageCount += 1;

        const classes = await page.locator('.manuscriptum-illuminatum-card-grid > article').evaluateAll((cards) => (
            cards.map((card) => card.className)
        ));

        for (const className of classes) {
            if (className.includes('manuscriptum-illuminatum-card--wiki')) {
                cardTypes.add('wiki');
            }
            if (className.includes('manuscriptum-illuminatum-card--diary')) {
                cardTypes.add('diary');
            }
            if (className.includes('manuscriptum-illuminatum-card--character')) {
                cardTypes.add('character');
            }
            if (className.includes('manuscriptum-illuminatum-card--covenant')) {
                cardTypes.add('covenant');
            }
        }

        const next = page.locator('.nav-links a.next');

        if (!await next.count()) {
            break;
        }

        await page.goto(await next.getAttribute('href'));
    }

    expect([...cardTypes].sort()).toEqual(['character', 'covenant', 'diary', 'wiki']);

    await page.goto('/saga-topic/covenant-affairs/');
    await expect(page.getByRole('heading', { level: 1, name: /Covenant Affairs/ })).toBeVisible();
    await expect(page.locator('.manuscriptum-illuminatum-card-grid > article').first()).toBeVisible();
});
