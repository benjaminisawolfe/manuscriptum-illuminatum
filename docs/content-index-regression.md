# Canonical content-index regression recovery

## Repository and source

- Canonical repository: https://github.com/benjaminisawolfe/manuscriptum-illuminatum
- Local checkout used: `E:/Personal/RPG/Gaillard - Ars Magica/WP Theme/manuscriptum-illuminatum`.
- Feature branch: `codex/port-content-index-query-regression`.
- Accepted canonical main: `89ad7657ce291607e8a204aca78e98d4fed897ff`.
- Read-only source: `benjaminisawolfe/ars-magica-theme`, branch `codex/content-index-query-regression`.
- Source patch range: `96d54b49ba3c6648c00aee4c5e739ce8bd15c16a..40b42afe6424d5377fba094e09cbb9afcbd0c4e8`.

The first preflight stopped in the old checkout because origin was wrong. After the
canonical local path was supplied, origin was verified, the working tree was clean,
origin was fetched, and local main was fast-forwarded from de0a927 to the accepted
89ad765 before creating this feature branch. No old history was merged or cherry-picked.

## Per-file port decisions

The nine affected application files in canonical main exactly matched the old base
after line-ending normalization. None contained the four accepted fixes or conflicting
newer implementations. The reviewed source diff therefore applied cleanly.

| Source changes | Canonical comparison and decision |
| --- | --- |
| Plugin privacy.php and helpers.php | No equivalent fix; port public-mode-aware listing visibility and its public helper. |
| Plugin Speculum, Personae, Journal and Covenant directory files | No equivalent fix; port readable-entry checks and the unclassified Speculum group. |
| Plugin shortcodes.php | No equivalent fix; port visibility checks and the automatic-excerpt recursion guard. |
| Theme functions.php and includes/breadcrumbs.php | No equivalent fix; port visibility delegation, related-entry handling and Annales pagination/rule refresh. |
| tests/content-index-regression.spec.js | Absent; port fixture-based browser/query regression coverage. Adapt fixture search tokens to avoid an ordinal also matching the shared timestamp. |
| tests/public-listing-queries.spec.js and support/mi-listing-regression-probe/probe.php | Absent; port administrator-only request-scoped visibility simulation and cleanup. |
| tests/privacy.spec.js | Matched old base; port four directory endpoint privacy assertions. |
| tests/personae.spec.js | Already corrected in canonical main: its selector targets the fixture portrait by alt text. Preserve this equivalent fix; do not replace it with the old branch's link-scoped selector. |
| docs/content-index-regression.md | Absent; write this canonical recovery record instead of copying an old deployment/test report as current evidence. |
| Both package ZIPs | Rebuild from canonical source; do not copy the old archives. |

Canonical CSS improvements, release metadata, theme screenshot, documentation screenshots,
README changes, visual test helpers and accepted baselines are preserved. There are no
application-code conflicts. The only overlapping newer test change is the equivalent
portrait assertion, which remains unchanged.

## Accepted behavior

1. Front-end visibility now distinguishes public visibility from WordPress capabilities.
   Authenticated users retain read_post checks. Guests require explicitly public site mode,
   a publicly viewable entry, and satisfaction of post-password requirements. Directory,
   shortcode, related-entry and shared teaser/row rendering use that policy. Login gates,
   private notes, REST authorization and editor capabilities are not relaxed.
2. Default Speculum grouping includes readable entries without the optional Entry Type.
   It does not assign terms or require existing entries to be resaved.
3. Automatic excerpt generation cannot re-enter the root directory's the_content filter.
   Manually entered excerpts and ordinary page content rendering retain their existing behavior.
4. Annales page-two-and-later URLs use an explicit pagination rule ahead of single-post
   routes. The existing versioned rule refresh installs it without changing single URLs.

## Query and test coverage

Annales uses the native Posts Page main query with published posts and date/ID ordering.
Speculum and Personae use sanitized optional filters and readable entries; grouped defaults
do not impose an absent taxonomy criterion. Commentarii and Covenant Records retain their
10-entry incremental pagination and explicit topic, type, relationship and date filters.
Homepage queries remain published-only, using publication date for Annales, Saga Dates
with undated fallback for Commentarii, and modified date for Covenant News, Personae
and Specula. The query normalization and registration behavior did not need to change.

The ported tests cover unfiltered, empty and whitespace filters; real search and reset;
taxonomy/type filters; unclassified entries; automatic and manual excerpts; pagination;
single URLs; all five latest sections; readable private versus draft content; and
private-site REST boundaries. The query probe temporarily installs through WordPress,
requires an administrator and nonce, changes public/private guest mode only inside that
authorized request, returns assertion results, and removes its own posts, terms and plugin.
It does not change staging's login setting or expose a guest endpoint.

The initial canonical run exposed a fixture ambiguity: searching for the first entry's
bare ordinal `01` also matched every entry when `01` occurred in their shared timestamp.
Four tests failed for that reason. Entry-specific search tokens now include letters
around the ordinal, preserving the exact-one-result assertions without changing query behavior.

## Repository identity guard

`npm run check:repository` prints expected and detected origin destinations and rejects
any fetch or push URL outside the canonical HTTPS/SSH forms. It checks the active working
directory, including when invoked by absolute path. AGENTS.md requires this preflight
before future changes. `npm run test:repository-guard` exercises canonical HTTPS/SSH,
the old repository, an unrelated push URL, missing origin and a non-repository directory.

## Validation and staging

All checks run from the canonical checkout. Browser runs set
`MANUSCRIPTUM_TEST_BASE_URL=https://devgaillard.wolfshafenpress.com` and use the existing
local authenticated storage-state file through `MANUSCRIPTUM_TEST_STORAGE_STATE`.
The state is read locally; it is not copied into canonical source, printed or committed.

| Command | Result |
| --- | --- |
| `npm run check:repository` | Passed; canonical fetch and push origin verified. |
| `npm run test:repository-guard` | 6 passed; canonical HTTPS/SSH accepted, wrong/missing destinations and non-repository working directory rejected. |
| `node --check tests/content-index-regression.spec.js` | Passed, including after fixture-token adaptation. |
| `node --check tests/public-listing-queries.spec.js` | Passed. |
| `node --check tests/privacy.spec.js` | Passed. |
| `node --check tests/personae.spec.js` | Passed; newer canonical test retained unchanged. |
| `git diff --check` | Passed. |
| `npm run build:packages` | Passed; both ZIPs rebuilt from canonical source. |
| `npm run validate:packages` | Passed; structure, exclusions, exact source contents and Git tracking verified. |
| `npx playwright test tests/content-index-regression.spec.js tests/public-listing-queries.spec.js tests/privacy.spec.js tests/personae.spec.js --output=.cache/targeted-test-results --reporter=list` | Initial port run: 15 passed, 4 failed, 1 skipped. The four failures were the ambiguous numeric fixture-search token described above. |
| `npx playwright test tests/content-index-regression.spec.js tests/public-listing-queries.spec.js tests/privacy.spec.js tests/personae.spec.js --output=.cache/targeted-corrected-results --reporter=list` | Corrected run: **19 passed, 1 skipped**. The duplicate mobile server-side visibility matrix is intentionally skipped. All accepted regression, privacy and portrait cases passed. |
| `npm run test:e2e` | **277 passed, 3 failed, 30 skipped** (310 total; 16.6 minutes). All 12 ported browser regressions and the server-side visibility matrix passed. All 26 accessibility checks passed. |
| `npx playwright test tests/visual/personae.spec.js --project=desktop -g "directory grouped" --output=.cache/portrait-visual-recheck --reporter=list` | The same four-pixel portrait mismatch repeated. No baseline or tolerance was changed. |

The full suite includes the existing visual, accessibility, smoke and typography suites;
the separate npm aliases were not rerun. There were no application, authorization,
layout, typography or accessibility failures after the fixture-token correction.

The remaining three failures are unrelated screenshot comparisons in unchanged canonical
visual tests: Home on desktop/mobile differs by 210 pixels at the first Covenant Record's
Last updated date, because existing fixtures edit/restore it without restoring modification
timestamps; the desktop Personae directory differs by four pixels inside one campaign
portrait. Expected/actual/diff images were inspected. The portrait mismatch repeated in
an isolated rerun; its deeper rendering cause is not established. The other 27 visual
tests passed, including the mobile Personae directory. No baselines were updated, no
tolerances were relaxed, and no existing content was rewritten to make comparisons pass.
The full suite is explicitly not claimed to pass.

PHP CLI linting is unavailable on this workstation. The relevant PHP code is executed
by real WordPress on staging through the regression and browser checks; no local server
stack was installed.

### Canonical package provenance and deployment

The following packages were built and committed in canonical source commit
`d12179ed1e32b55b7a5ac761a799dd5cebdaf81a`. Before upload, each ZIP was byte-compared
with its committed Git blob. Later fixture/documentation changes do not alter deployed
application source or packages.

| Package | Bytes / files | SHA-256 |
| --- | --- | --- |
| `packages/ligatura-manuscripti-illuminati.zip` | 80,435 / 27 | `04f7fb40c3c3faea9380737b649e1ec624f95082df7da657cc179be736e6dd6e` |
| `packages/paginae-manuscripti-illuminati.zip` | 488,305 / 46 | `e1e243bfa0dffe4fdc8c4904a9b4224d0c7aaedaefc4c22898d51aed77211924` |

Staging deployment succeeded on 2026-09-05 through the normal authenticated WordPress
administrator upload-and-replace flow for both active packages. The old repository's
SSH deployment helper was not used. No package archive from the old repository was
uploaded. Both packages retain canonical version 1.0 and its newer release metadata/assets.

### Final browser QA

`node .cache/manual-canonical.cjs` completed successfully against canonical staging.
An additional read-only screenshot pass waited for campaign images to finish decoding
before visual inspection. The following routes returned HTTP 200 with their expected
headings and no visible PHP fatal/parse errors:

- `/`
- `/news/`
- `/wiki/`
- `/wiki/artifact/`
- `/wiki/topics/covenant-affairs/`
- `/saga-topic/covenant-affairs/`
- `/characters/`
- `/journals/`
- `/covenant-records/`
- `/covenant-records/type/artifact/`
- `/covenant-records/topics/covenant-affairs/`
- `/news/notes-from-the-salt-barge/`
- `/wiki/covenant-of-the-quiet-bell/`
- `/characters/aveline-of-bonisagus/`
- `/journals/guarin-begins-courteous-audit/`
- `/covenant-records/charter-chest-three-mismatched-keys/`

Searches on Speculum, Personae, Commentarii and Covenant Records each returned the
selected entry, then reset successfully. Their actual advanced controls were also
used to apply and clear Artifact, Companion, Covenant Affairs and Artifact filters,
respectively. Temporary posts enabled the generated Annales pagination link
`/index.php/news/page/2/`, which rendered two entries, and an unclassified Speculum
entry with no manual excerpt. Both were inspected visually and their temporary
fixtures were removed.

Screenshots of all five root indexes, Latest Personae, Latest Specula on desktop/mobile,
the unclassified group and Annales page two were inspected. The accepted grouping,
card/row presentation, images, excerpts and pagination are intact. Home and all five
root indexes have zero horizontal overflow at the Pixel 5 viewport. No visible empty
Home heading links or JavaScript page errors were found. Automated accessibility
results supplement this inspection; complete accessibility compliance is not claimed.

After cleanup, homepage counts are 3 Latest Annales, 3 Latest Commentarii, 3 Latest
Covenant News, 4 Latest Personae and 4 Latest Specula. Published counts remain
8 Annales / 15 Specula / 10 Personae / 8 Commentarii / 8 Covenant Records, matching
the initial canonical-task audit. No task fixtures or query-probe plugin remain.
The canonical theme/plugin are active and a fresh unauthenticated browser still
redirects to WordPress login. Existing content needs no resaving for these fixes.

Deployment/test/browser evidence is retained in ignored `.cache/`, `test-results/`
and `playwright-report/`. No production deployment, merge into main, force-push,
history rewrite, old-branch deletion or old-repository modification occurred.
The final commit and tree SHA are recorded in the completion report.

## Changed files

- `AGENTS.md`
- `package.json`
- `scripts/check-repository.mjs`
- `scripts/check-repository.test.mjs`
- `docs/content-index-regression.md`
- `plugins/ligatura-manuscripti-illuminati/includes/privacy.php`
- `plugins/ligatura-manuscripti-illuminati/includes/helpers.php`
- `plugins/ligatura-manuscripti-illuminati/includes/speculum-directory.php`
- `plugins/ligatura-manuscripti-illuminati/includes/personae-directory.php`
- `plugins/ligatura-manuscripti-illuminati/includes/journal-directory.php`
- `plugins/ligatura-manuscripti-illuminati/includes/covenant-directory.php`
- `plugins/ligatura-manuscripti-illuminati/includes/shortcodes.php`
- `themes/paginae-manuscripti-illuminati/functions.php`
- `themes/paginae-manuscripti-illuminati/includes/breadcrumbs.php`
- `tests/content-index-regression.spec.js`
- `tests/public-listing-queries.spec.js`
- `tests/privacy.spec.js`
- `tests/support/mi-listing-regression-probe/probe.php`
- `packages/ligatura-manuscripti-illuminati.zip`
- `packages/paginae-manuscripti-illuminati.zip`

## Follow-up considerations

Grouped directories still repeat queries and hold all readable entries in memory, and
the Personae request-local cache assumes a stable user within an ordinary request.
Older fixtures can change modification timestamps while restoring content, affecting
homepage order and screenshot determinism. These are separate maintenance candidates.
