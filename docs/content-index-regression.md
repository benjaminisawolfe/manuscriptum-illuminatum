# Content-index regression coverage

## Expected behavior

The 1.0.1 maintenance release protects four related behaviors:

1. Front-end listing visibility distinguishes publicly viewable content from
   WordPress capabilities. Authenticated users retain read_post checks. Guests
   require explicitly public site mode, a publicly viewable entry, and satisfaction
   of post-password requirements. Directory, shortcode, related-entry, and shared
   teaser rendering use the same policy. Login gates, protected notes, REST
   authorization, and editor capabilities remain enforced.
2. Default Speculum grouping includes readable entries without the optional
   Entry Type. No taxonomy assignment or resaving existing entries is required.
3. Automatic excerpt generation cannot re-enter the root directory's the_content
   filter. Manual excerpts and ordinary Page content retain their normal behavior.
4. Annales pagination rules take precedence over single-post routes. The versioned
   rewrite-rule refresh installs them while preserving canonical single-entry URLs.

## Query invariants

Annales uses the native Posts Page query with published posts and date/ID ordering.
Speculum and Personae apply sanitized optional filters; an absent taxonomy filter
must not exclude unclassified entries. Commentarii and Covenant Records retain
10-entry incremental pagination and their topic, type, relationship, and date filters.

Home collections remain published-only. Annales uses publication date; Commentarii
uses Saga Date with an undated fallback; Covenant News, Personae, and Specula use
modification date.

## Automated coverage

Run the existing tests against an authorized remote WordPress installation with
MANUSCRIPTUM_TEST_BASE_URL and a local, ignored MANUSCRIPTUM_TEST_STORAGE_STATE:

    npx playwright test tests/content-index-regression.spec.js tests/public-listing-queries.spec.js tests/privacy.spec.js

Coverage includes unfiltered, empty, and whitespace-only filters; search and reset;
taxonomy filters; unclassified entries; automatic and manual excerpts; pagination;
single-entry URLs; all five latest sections; private versus draft visibility; and
private-site REST boundaries.

Fixture search tokens surround numeric ordinals with letters so an ordinal cannot
accidentally match a timestamp shared by every fixture. Preserve exact-result assertions.

The listing query fixture temporarily installs an administrator- and nonce-protected
test plugin. Visitor-mode simulation applies only within the authorized request and
does not change the site's login requirement. Fixtures, terms, and the temporary plugin
are removed afterward. Test support stays under tests/ and is excluded from both ZIPs.

Persona publication and Player-access diagnostics also live in temporary test support.
The affected tests check that their routes are absent before installation and after
cleanup. The public plugin retains its publication invariants and permission enforcement
without carrying these diagnostic routes.

## Maintenance considerations

Grouped directories query and retain all readable entries. The Personae request-local
cache assumes a stable user within an ordinary request.

Fixtures that edit and restore content may still change modification timestamps,
affecting Home ordering and screenshot comparisons. Inspect differences before changing
visual baselines; a passing assertion must not depend on rewriting existing content.

See [Releasing](RELEASING.md) for source/package hygiene and artifact verification.
