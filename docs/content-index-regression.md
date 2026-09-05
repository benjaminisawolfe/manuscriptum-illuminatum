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

Validation, canonical package provenance, deployment and final browser QA will be recorded
here after execution. No old-repository test results are treated as canonical-run evidence.

## Follow-up considerations

Grouped directories still repeat queries and hold all readable entries in memory, and
the Personae request-local cache assumes a stable user within an ordinary request.
Older fixtures can change modification timestamps while restoring content, affecting
homepage order and screenshot determinism. These are separate maintenance candidates.
