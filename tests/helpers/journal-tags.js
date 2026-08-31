async function getApiSettings(page) {
    await page.goto('/wp-admin/', { waitUntil: 'domcontentloaded' });

    return page.evaluate(() => {
        const settings = window.wpApiSettings || {};
        const apiLink = document.querySelector('link[rel="https://api.w.org/"]');

        return {
            nonce: settings.nonce || '',
            root: settings.root || (apiLink ? apiLink.href : ''),
        };
    });
}

async function attachJournalTag(page, journalSlug, tagName, tagSlug) {
    const api = await getApiSettings(page);
    const fixture = await page.evaluate(async ({ root, nonce, journalSlug: slug, tagName: name, tagSlug: termSlug }) => {
        const headers = { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce };
        const request = async (path, options = {}) => {
            const response = await fetch(new URL(path, root), {
                credentials: 'same-origin',
                headers,
                ...options,
            });

            return { ok: response.ok, status: response.status, body: await response.json() };
        };
        const journals = await request(`wp/v2/ligatura_diary?slug=${encodeURIComponent(slug)}&context=edit`);

        if (!journals.ok || !journals.body[0]) {
            throw new Error(`Journal fixture lookup failed with ${journals.status}.`);
        }

        const journal = journals.body[0];
        const originalTags = Array.isArray(journal.tags) ? journal.tags : [];
        const existingTags = await request(`wp/v2/tags?slug=${encodeURIComponent(termSlug)}&context=edit`);
        let tag = existingTags.body[0];
        let createdTag = false;

        if (!tag) {
            const created = await request('wp/v2/tags', {
                method: 'POST',
                body: JSON.stringify({ name, slug: termSlug }),
            });

            if (!created.ok) {
                throw new Error(`Tag fixture creation failed with ${created.status}.`);
            }

            tag = created.body;
            createdTag = true;
        }

        const updated = await request(`wp/v2/ligatura_diary/${journal.id}`, {
            method: 'POST',
            body: JSON.stringify({ tags: [...new Set([...originalTags, tag.id])] }),
        });

        if (!updated.ok) {
            throw new Error(`Journal tag assignment failed with ${updated.status}.`);
        }

        return {
            api: { root, nonce },
            createdTag,
            journalId: journal.id,
            originalTags,
            tagId: tag.id,
        };
    }, { ...api, journalSlug, tagName, tagSlug });

    return fixture;
}

async function restoreJournalTags(page, fixture) {
    if (!fixture) {
        return;
    }

    await page.evaluate(async ({ api, createdTag, journalId, originalTags, tagId }) => {
        const headers = { 'Content-Type': 'application/json', 'X-WP-Nonce': api.nonce };

        await fetch(new URL(`wp/v2/ligatura_diary/${journalId}`, api.root), {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: JSON.stringify({ tags: originalTags }),
        });

        if (createdTag) {
            await fetch(new URL(`wp/v2/tags/${tagId}?force=true`, api.root), {
                method: 'DELETE',
                credentials: 'same-origin',
                headers,
            });
        }
    }, fixture);
}

module.exports = { attachJournalTag, restoreJournalTags };
