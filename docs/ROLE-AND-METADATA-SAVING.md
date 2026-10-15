# Role migration and metadata saving

## Findings

The affected staging installation contained both `ars_player` and
`ligatura_player` named Player, and both `ars_storyguide` and
`ligatura_storyguide` named Storyguide. Four accounts held `ars_player`; the
other three project roles were unoccupied. No existing account held multiple
roles. The old project history establishes both `ars_*` slugs as project-owned
roles (original registration `fd5e06f`, later Player changes `c8ae9ec` and
`a4bd289`, rename `121704a`). These are persisted definitions left behind by the
rename, not two current registration callbacks.

`includes/roles-capabilities.php` is the current product's only role-definition
writer. Activation calls its role setup; `init` priority 1 runs its versioned
upgrade. Deactivation flushes rewrite rules and does not remove roles. The theme
does not register these roles. The migration does not infer ownership from labels:
an unrelated third-party role called Player remains untouched.

An isolated legacy-account fixture had no effective assigned-Persona or own-Journal
editing permission before migration and both permissions afterward. Normal
administrator profile updates were verified on a disposable canonical account;
no separate profile-update error was reproduced. The canonical Player save
failure below was independent of the duplicate-role condition.

The Character save failure was reproduced separately with a canonical Player.
The actual editor's REST update returned HTTP 200, followed by its metabox POST
returning HTTP 500; the stored occupation remained unchanged. Core's
`_wp_translate_postdata()` checks `edit_others_posts` when the submitted author
is another user, even when that author is unchanged. The Player can edit the
assigned post but does not hold that broad primitive capability. Omitting the
author from a classic form instead transferred technical ownership to the Player.
The observed REST payload contained no metadata, so an assignment metadata echo
was not the cause of this failure.

There was also a shared data-loss defect: valid partial metabox forms converted
every omitted field to an empty value on Characters, Wiki entries, Journals and
Covenant Records. Private Wiki notes had the same problem. An assignment nonce
without its assignment field reset a Character to the default administrator.
All were reproduced on disposable records before deployment. Invalid Saga Dates
were retained silently; the form now reports an error.

Core reference: [_wp_translate_postdata](https://developer.wordpress.org/reference/functions/_wp_translate_postdata/).

## Implementation and upgrade behavior

- `roles-capabilities.php`: version `2026-10-15-legacy-role-migration-v2` migrates
  only `ars_player` to `ligatura_player` and `ars_storyguide` to
  `ligatura_storyguide` on the current site. It adds canonical membership before
  removing legacy membership, preserves unrelated roles and individual grants
  or denials, and retains effective legacy capabilities on affected accounts
  only. WordPress's capability merge order is evaluated on a cloned user,
  including existing canonical memberships and conflicting other roles.
- Role definitions are retired only after persisted membership checks succeed.
  A legacy default role is updated too. Failed writes leave the legacy definition
  and incomplete version for a retry. Completed upgrades do no repeated writes.
  The current user's cached capabilities are refreshed. No post content,
  assignment values, or Journal authorship is rewritten by the migration.
- `player-access.php` and `class-plugin.php`: a nonce-verified, assigned Character
  form permits core's author-preserving check for that request. No broad
  `edit_others_ligatura_characters` capability is added to the Player role.
  Forged author, post type, status, date, visibility and password changes are
  rejected; missing author/date fields retain the stored values. Classic date
  reconstruction triggers are removed after validating submitted components.
- `meta-boxes.php`: omitted fields are preserved; present empty fields clear.
  Relationship multiselects submit an explicit empty marker. Submitted values
  retain existing sanitizers and metadata authorization. Invalid/missing form
  nonces, forbidden fields, and invalid Saga Dates produce error responses.
  Only actual non-REST POST submissions enter the form saver. GET auto-draft
  bootstrap and REST inserts can contain server-derived Journal defaults, which
  must not be mistaken for unsigned form submissions. REST controllers retain
  their own authorization, validation and persistence.
- Tests and temporary diagnostic plugins live under `tests/`. Both canonical
  ZIPs exclude all probes, fixtures, browser authentication and reports. Theme
  runtime source and package bytes are unchanged.

## Verification

Verification uses the authorized remote staging installation and generated
temporary accounts/posts. No local WordPress, PHP or database stack was installed.
The original role option, default/version options and affected users' exact
capability/user-level metadata were backed up to ignored local storage before
the upgrade. Authentication, account identifiers and backup contents are not
included in this report or the repository.

Local verification passed: repository guard 6/6, release-hygiene unit tests
52/52, JavaScript syntax checks, `git diff --check`, `npm run build:packages`,
`npm run validate:packages`, and `npm run check:release-hygiene`. WordPress loaded
the PHP implementation and executed the integration fixtures; there is no local
PHP lint runtime.

Both canonical packages were replaced through the authorized staging WordPress
administrator UI. The original four accounts were compared with the private
backup: canonical memberships are present, legacy memberships are absent, and
their other direct grants match. Both components are active. The role selector
has exactly one Player and Storyguide, and a disposable account's profile updates
and role changes persist.

The final deployed revision (`9d5f084`) passed **21/21 tests in one run**:
9 save-boundary tests, 9 metadata tests, 2 role tests, and the existing
comprehensive Player-access regression. This includes the new-Journal editor
and REST creation paths with a sole assigned Character, normal form saves,
media ownership, Journal authorship and reassignment. Initial helper failures
were corrected for normal metabox redirects, draft Save button text and isolated
login. Browser diagnostics remain recorded separately from save assertions;
they are described below.

| Identity / path | Automated stored-value and UI checks |
| --- | --- |
| Administrator, Storyguide, assigned Player | Published Character occupation, age and Code-mode sheet; unchanged rich text; intentional clear; repeated save without reload; title/body/excerpt; reloaded fields |
| Assigned Player | Draft, pending, private and scheduled Character saves via REST and partial classic forms; author/status/date/assignment preserved |
| Player | New Journal UI/REST creation and default Character Author; own Journal session/Saga dates persist; unassigned Character UI and REST writes rejected |
| Forged Player requests | Assignment, private Wiki notes, author/type/status/date/visibility/password and invalid nonce changes rejected and stored values preserved |
| Administrator, all four CPTs | Omitted ordinary/rich/date fields preserved; explicit clear allowed; invalid and absent metadata nonces rejected |
| Wiki and assignment forms | Private notes and relationships preserved; deselect-all and private-note clear work; omitted assignment does not reset ownership |
| Role fixtures | Clean and canonical installations; occupied legacy roles; multiple roles; direct grants/denials; conflicting role order; failed writes; repeated upgrades with zero option writes; assignment and Journal ownership preservation |

Reproduction commands (set the staging base URL and ignored storage-state path):

```text
npx playwright test tests/meta-saving.spec.js tests/meta-save-boundaries.spec.js tests/role-migration.spec.js tests/player-access.spec.js --project=desktop --reporter=line
```

The existing desktop/mobile suite scheduled 152 tests: **133 passed, 14 skipped,
5 failed** on the first run. The 14 skips are duplicate mobile mutation/editor
cases deliberately exercised once on desktop. The Player-access failure exposed the Journal
bootstrap regression; it was corrected and passed in the final 21-test run above.
The other four failures were strict screenshot differences (4, 18, 31 and 66
pixels); all four diff images were inspected and showed isolated text/image-edge
differences without a visible layout shift. Baselines and screenshot tolerances
were not changed. All **four unchanged screenshot retries passed** (two desktop,
two mobile), so each of the 30 visual comparisons has a passing latest result.
The initial rendering differences are retained in the ignored test output rather
than hidden by rebaselining. Each initially failing functional/visual test has
now passed; the complete 152-case suite was not repeated after the final fix.

Final staging cleanup verification passed: both components are active, the
temporary runtime-probe plugin is absent, and all four original migrated
accounts still match their preserved non-legacy direct grants. The unfinished
test package upload was removed. The migration backup remains local and ignored.

All **26 accessibility tests passed**, using automated axe WCAG 2 A/AA and
WCAG 2.1 A/AA checks on desktop and mobile. This is not a manual screen-reader
audit. The captured reloaded Player editor was also visually inspected; stored
values and the occupation/full-sheet controls were verified independently.

```text
npx playwright test tests/player-access.spec.js tests/player-login-memory.spec.js tests/personae-single.spec.js tests/privacy.spec.js tests/speculum.spec.js tests/journals.spec.js tests/covenant-records.spec.js tests/smoke.spec.js tests/visual tests/a11y --reporter=line
```

| Package | Bytes | SHA-256 |
| --- | ---: | --- |
| paginae-manuscripti-illuminati.zip | 488736 | `2f1ba4966f37c2aacbd92b1ba76fa10c5a2b1bf8b7a0a3cecdb95abcd140ab75` |
| ligatura-manuscripti-illuminati.zip | 81291 | `8b8f3a6df9b838a772d09b9deaf691493671cddeafe9c30cd885d80ddaed7de6` |

## Deferred editor work and limits

Rich-text persistence is exercised through Code/Text mode. This change does not
repair TinyMCE initialization, change media buttons or introduce attachments.
WordPress's Visual editor can normalize an untouched single-paragraph value by
removing its outer paragraph wrapper; the tests distinguish that normalization
from content loss. Visual-mode editability remains a separate Pass 2 check.

Administrator editor diagnostics were empty. Follow-up observations for the
separate editor pass are:

- In both limited-role flows, `Cannot read properties of undefined (reading
  'serialize')` was recorded in WordPress `wp-admin/js/user-profile.min.js`
  during navigation from login. This is not evidence of a TinyMCE cause.
- Background reads returned 401 for `wpforms/v1/themes/` and 403 for
  `wp/v2/global-styles/{id}` and `wp/v2/blocks`. Actual Character REST/metabox
  saves succeeded and stored/reloaded assertions passed. No broader capabilities
  were granted to silence these responses.
- Visual-mode editability and initialization remain for Pass 2; Code/Text
  persistence is verified here. Media-button changes and attachments remain out
  of scope.

WordPress saves the main post and metaboxes in separate requests. An error from
the metabox request is now explicit, but the earlier title/body REST update is
not a cross-request transaction and may already have succeeded.

## Deployment and rollback checklist

1. Before production deployment, obtain a full database backup and retain the
   currently installed plugin/theme ZIPs. Export the current site's role option,
   role-version/default-role options and affected users' exact capability and
   user-level metadata. The staging snapshot is not a production backup.
2. Review and merge the feature PR. Build and validate both canonical packages
   from accepted canonical source; compare their hashes with the reviewed
   artifacts. Install through WordPress package replacement or an approved
   deployment helper. Production deployment requires separate authorization.
3. The next initialization performs the versioned role upgrade. Confirm one
   canonical Player and Storyguide in the selector, expected migrated accounts,
   unchanged other roles/direct grants, assignments and Journal ownership.
4. Save ordinary and Code-mode Character metadata as an assigned Player, reload
   and verify stored values. Check unassigned edits, assignment/private-note
   writes and status changes are rejected; verify partial forms preserve fields.
5. If rollback is necessary, first restore the prior plugin package so the new
   upgrade cannot immediately rerun. Restore the role option, each affected
   user's exact capability/user-level metadata, and default/version options
   from the same-site backup (delete options/metadata originally absent). Clear
   relevant caches and renew sessions. Restore the full database only under a
   controlled maintenance window to avoid overwriting intervening content.
6. Recheck login, assignments, Journal ownership and role counts after rollback.
   Remove temporary diagnostics and confirm their REST routes are absent.

The pre-change canonical ZIPs remain recoverable from repository commit
`09a61cf3a565422677cb09dd38665d77c14c9235`. They are a source rollback reference,
not a replacement for an operator's backup of the actual installed release and
database.
