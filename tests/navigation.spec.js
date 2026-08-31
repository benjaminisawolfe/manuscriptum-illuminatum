const { test, expect } = require('@playwright/test');

test('primary navigation uses public Latin section names', async ({ page }) => {
    await page.goto('/');

    await expect(page).not.toHaveURL(/wp-login\.php/);

    const nav = page.locator('.site-nav');

	await expect(nav.getByRole('link', { name: /^Annales$/ })).toBeVisible();
    await expect(nav.getByRole('link', { name: /^Speculum$/ })).toBeVisible();
    await expect(nav.getByRole('link', { name: /^Personae$/ })).toBeVisible();
    await expect(nav.getByRole('link', { name: /^Commentarii$/ })).toBeVisible();

	await expect(nav.getByRole('link', { name: /^Annales$/ }).first()).toHaveAttribute('href', /\/news\/?$/);
    await expect(nav.getByRole('link', { name: /^Speculum$/ }).first()).toHaveAttribute('href', /\/wiki\/?$/);
    await expect(nav.getByRole('link', { name: /^Personae$/ }).first()).toHaveAttribute('href', /\/characters\/?$/);
    await expect(nav.getByRole('link', { name: /^Commentarii$/ }).first()).toHaveAttribute('href', /\/journals\/?$/);

    await expect(nav.getByRole('link', { name: /^Wiki$/ })).toHaveCount(0);
    await expect(nav.getByRole('link', { name: /^Characters$/ })).toHaveCount(0);
    await expect(nav.getByRole('link', { name: /^Diaries$/ })).toHaveCount(0);
});

test('primary navigation dropdowns expose child items accessibly when present', async ({ page }) => {
    await page.goto('/');

    await expect(page).not.toHaveURL(/wp-login\.php/);

    const parent = page.locator('.site-nav__menu .menu-item-has-children').first();

    if (await parent.count() === 0) {
        test.skip(true, 'The test installation primary menu currently has no child menu items.');
    }

    const link = parent.locator(':scope > a');
    const toggle = parent.locator(':scope > .site-nav__submenu-toggle');
    const submenu = parent.locator(':scope > .sub-menu');

    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

	await parent.hover();
	await expect(submenu).toBeVisible();

    await link.focus();
    await expect(submenu).toBeVisible();

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(submenu).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
});

test('a WordPress menu edit creates a usable submenu and can be restored', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The reversible WordPress menu mutation is exercised once.');
    test.setTimeout(90_000);

    await page.goto('/wp-admin/');
    await expect(page).not.toHaveURL(/wp-login\.php/);

    const api = await page.evaluate(() => ({
        root: window.wpApiSettings?.root || document.querySelector('link[rel="https://api.w.org/"]')?.href || '',
        nonce: window.wpApiSettings?.nonce || '',
    }));
    const status = await page.evaluate(async ({ root, nonce }) => {
        const response = await fetch(new URL('paginae-manuscripti-illuminati/v1/site-setup/status', root), {
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': nonce },
        });
        return response.json();
    }, api);

    expect(status.primaryMenuId).toBeGreaterThan(0);
    const menuUrl = `/wp-admin/nav-menus.php?action=edit&menu=${status.primaryMenuId}`;
    let parentId = '';
    let childId = '';
    let originalParent = '';
    let originalDepth = 0;
    let originalTitle = '';
    const probeTitle = `Annales submenu probe ${Date.now()}`;

    async function loadMenuEditor() {
        await page.goto(menuUrl);
        await expect(page.locator('#menu-to-edit .menu-item').first()).toBeVisible();
    }

    async function saveMenuItem(itemId, title, parent, depth) {
        const item = page.locator(`#menu-item-${itemId}`);
        await item.locator('.item-edit').click();
        await expect(item.locator('.edit-menu-item-title')).toBeVisible();
        await item.locator('.edit-menu-item-title').fill(title);
        await item.locator('.menu-item-data-parent-id').evaluate((input, value) => {
            input.value = value;
        }, parent);
        await item.evaluate((element, menuDepth) => {
            for (const className of [...element.classList]) {
                if (/^menu-item-depth-\d+$/.test(className)) {
                    element.classList.remove(className);
                }
            }
            element.classList.add(`menu-item-depth-${menuDepth}`);
        }, depth);
        await page.locator('#save_menu_footer').click();
        await page.waitForLoadState('domcontentloaded');
        await expect(page.locator('#message.updated, .notice-success').first()).toBeVisible();
    }

    try {
        await loadMenuEditor();
        const items = page.locator('#menu-to-edit > .menu-item');
        expect(await items.count()).toBeGreaterThanOrEqual(2);
        parentId = (await items.nth(0).getAttribute('id')).replace('menu-item-', '');
        childId = (await items.nth(1).getAttribute('id')).replace('menu-item-', '');
        const child = page.locator(`#menu-item-${childId}`);
        originalParent = await child.locator('.menu-item-data-parent-id').inputValue();
        originalDepth = Number((await child.getAttribute('class')).match(/menu-item-depth-(\d+)/)?.[1] || 0);
        originalTitle = await child.locator('.edit-menu-item-title').inputValue();

        await saveMenuItem(childId, probeTitle, parentId, 1);
        await page.goto('/');

        let parent = page.locator('.site-nav__menu .menu-item-has-children').filter({ has: page.getByRole('link', { name: /^Home$/ }) });
        let submenu = parent.locator(':scope > .sub-menu');
        let toggle = parent.locator(':scope > .site-nav__submenu-toggle');
        await parent.hover();
        await expect(submenu.getByRole('link', { name: probeTitle })).toBeVisible();

        await parent.locator(':scope > a').focus();
        await expect(submenu).toBeVisible();
        await toggle.focus();
        await page.keyboard.press('Enter');
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await page.keyboard.press('Escape');
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');

        await page.setViewportSize({ width: 393, height: 851 });
        await page.reload();
        parent = page.locator('.site-nav__menu .menu-item-has-children').filter({ has: page.getByRole('link', { name: /^Home$/ }) });
        submenu = parent.locator(':scope > .sub-menu');
        toggle = parent.locator(':scope > .site-nav__submenu-toggle');
        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(submenu.getByRole('link', { name: probeTitle })).toBeVisible();
    } finally {
        if (childId) {
            await loadMenuEditor();
            await saveMenuItem(childId, originalTitle, originalParent, originalDepth);
            await page.goto('/');
            await expect(page.locator('.site-nav').getByRole('link', { name: originalTitle, exact: true })).toBeVisible();
            await expect(page.locator('.site-nav').getByRole('link', { name: probeTitle, exact: true })).toHaveCount(0);
        }
    }
});
