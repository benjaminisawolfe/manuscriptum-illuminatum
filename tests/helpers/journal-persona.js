const { expect } = require('@playwright/test');

/**
 * Resolve a real Persona collection from the current test content.
 *
 * Persona content is editorial data, so release tests must not depend on one
 * demo Persona remaining published forever.
 */
async function getJournalPersonaCollection(page, preferredName = null) {
    await page.goto('/journals/');

    const links = page.locator('.manuscriptum-illuminatum-commentarii-personae a');
    const link = preferredName ? links.filter({ hasText: preferredName }).first() : links.first();
    await expect(link).toBeVisible();

    const [href, name] = await Promise.all([
        link.getAttribute('href'),
        link.textContent(),
    ]);
    const url = new URL(href);
    const match = url.pathname.match(/^\/journals\/persona\/([a-z0-9-]+)\/?$/);

    expect(match).not.toBeNull();
    expect(name.trim()).toBeTruthy();

    return {
        href: url.href,
        name: name.trim(),
        path: url.pathname,
        slug: match[1],
    };
}

module.exports = { getJournalPersonaCollection };
