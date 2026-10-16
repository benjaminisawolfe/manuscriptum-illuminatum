# v1.1.0 preparation verification

Preparation date: 2026-10-15 (America/Toronto). This records release preparation,
not publication or production deployment.

## Accepted base and scope

- Canonical origin: `https://github.com/benjaminisawolfe/manuscriptum-illuminatum.git`.
- Accepted main: `8d2e100b92ac5abe1f3743a55a23abe4e577f696`.
- Accepted tree: `d2a34108a9570e4b9675ea80740f29f47539ec0a`.
- PR #7 merge `ffea229d4abb1eb9b2f96487f56d267fad483889` is an ancestor.
- PR #8 was merged by the owner at `2026-10-16T02:28:23Z`. Its accepted merge
  tree exactly equals reviewed Pass 2 head `c56690ad5a11d91d98ae56624e258f274bb751c8`.
- Local main was fast-forwarded to origin/main, then the new
  `codex/release-v1.1.0` branch was created with a clean working tree.
- Latest published release at preflight: v1.0.2, published `2026-10-15T08:58:37Z`.
  No v1.1.0 tag, release, preparation branch or preparation PR existed.

Only three runtime metadata declarations change: the theme's Version header,
the plugin's Version header and its public version constant. All now equal
1.1.0. Internal role, site-structure and rewrite migration versions are unchanged.
No runtime feature, permission, database schema, test or screenshot baseline changed.

README current versions and future asset links, the new changelog section,
proposed release notes, this verification record and the preparation plan comprise
the documentation changes. Historical changelog entries and Pass 1/Pass 2 reports
are preserved. Root documentation is outside both package source directories.

## Package verification

| Package | Version | Files | Bytes | SHA-256 |
| --- | --- | ---: | ---: | --- |
| `paginae-manuscripti-illuminati.zip` | 1.1.0 | 46 | 489125 | `58ede6fa33501491563b31ac473ae43da49c7e09712536c50739f8c447d2b57b` |
| `ligatura-manuscripti-illuminati.zip` | 1.1.0 | 29 | 91656 | `80c43e71c06bcb6dfc49706253c212b3a013c04210b8c733703a96e812c960d8` |

Both canonical ZIPs remain tracked and match their source byte for byte.
Every entry was compared with the ZIPs at accepted main. Inventories are identical:
only `paginae-manuscripti-illuminati/style.css` and
`ligatura-manuscripti-illuminati/ligatura-manuscripti-illuminati.php` differ, and
each difference is exactly a `1.0.2` to `1.1.0` replacement. The other 73 files
are byte-identical. The plugin runtime version constant matches its header.

The full inventories and machine-readable hygiene results were inspected.
Both have zero findings. Packages contain no tests, authentication, probe plugins,
release notes, planning files, QA reports or workstation paths. Licensing and
attribution are unchanged.

## Automated build checks

All completed successfully:

- `npm run check:repository`
- `npm run test:repository-guard` — 6 passed.
- `npm run test:release-hygiene` — 52 passed.
- `git diff --check`
- `npm run build:packages`
- `npm run validate:packages`
- `npm run check:release-hygiene`
- `node scripts/check-release-hygiene.mjs --list`
- `node scripts/check-release-hygiene.mjs --json`

## Staging deployment

The verified ZIPs were committed and pushed at
`e9c9b683b8737356dc4d7a850b1bfad2e43dfeb1` before deployment. The existing
approved helper replaced the plugin first, then the theme, using WordPress's
administrator package upload/replacement flow on authorized remote staging.
WordPress reported both updates successful; the deployment check passed.
No server source was edited directly and no local WordPress stack was installed.

After regressions, two desktop/mobile health checks confirmed that both active
components report 1.1.0, the deployed theme stylesheet exactly matches canonical
source, temporary runtime/listing probe plugins are absent, and the Character
page fits its viewport.

## Critical regressions

All **40 critical regression cases passed**, with no skips, failures or retries:

| Existing suite | Passed | Coverage |
| --- | ---: | --- |
| `character-documents.spec.js` | 10 | Upload, picker, attach/unlink, formats, authorization, invalid inputs, stale links and direct-file privacy. |
| `meta-editors.spec.js` | 5 | Block/classic, Player/Storyguide and Wiki Visual editors; typing, formatting, switching, saving and movement. |
| `meta-editor-links.spec.js` | 1 | Link toolbar, focus and unique editor identities. |
| `meta-save-boundaries.spec.js` | 9 | Character/Journal save authorization, forged requests, publication boundaries and protected notes. |
| `meta-saving.spec.js` | 9 | Omitted-field preservation, explicit clearing and metadata saves by permitted roles. |
| `player-access.spec.js` | 1 | Least privilege, assignments, media, Journal authorship and reassignment. |
| `privacy.spec.js` | 1 | Guest restrictions on front-end pages, feeds and REST data. |
| `role-migration.spec.js` | 2 | Isolated migration scenarios, retained independent roles/grants and canonical role selector. |
| `personae-single.spec.js` (two disposable publication cases) | 2 | Character Type required for publishing/scheduling; missing persisted taxonomy reverses direct publication. |

The first eight suites ran together with one mutation worker: 38/38 passed in
15.1 minutes. The two publication checks then passed in 19.3 seconds. Mutation
coverage used disposable records/accounts/files; role migration used isolated
options and guards preventing changes to real user memberships. Existing
Character content was not rewritten for presentation tests.

All six safe formats (PDF, DOC, DOCX, ODT, RTF, TXT) returned HTTP 201 on this
host, including a genuine binary DOC fixture. Missing nonces, foreign media,
unassigned access, disallowed types, MIME mismatches and oversized uploads were
rejected as expected. Anonymous requests to the disposable TXT and PDF upload
URLs returned HTTP 200 with the exact original bytes while the Character page
redirected to login and document metadata access was denied.

Reproduction uses the existing configured remote Playwright environment, with
authentication kept local and ignored and traces disabled for these runs:

```text
npx playwright test tests/meta-editors.spec.js tests/meta-editor-links.spec.js tests/character-documents.spec.js tests/meta-saving.spec.js tests/meta-save-boundaries.spec.js tests/player-access.spec.js tests/role-migration.spec.js tests/privacy.spec.js --project=desktop --trace=off
npx playwright test tests/personae-single.spec.js --grep "server requires a valid Character Type|persisted taxonomy recheck" --project=desktop --trace=off
npx playwright test tests/a11y/personae.spec.js --trace=off
```

The optional `MANUSCRIPTUM_TEST_DOC_FIXTURE` was configured for genuine DOC coverage.
Local reports, fixtures and operator health checks remain ignored and outside
both packages.

## Visual and accessibility verification

All **4 existing Personae accessibility cases passed** on desktop/mobile, covering
the directory and single Character page, with no detected WCAG 2/2.1 A/AA
violations. The document workflow also recorded zero axe violations in Full Sheet
and Documents at both desktop and mobile viewports.

Captured empty-library, document-editor and desktop/mobile document-list views
were visually inspected: notices and controls are legible, links wrap inside the
available width, and no overlap or clipping was observed. Read-only full Character
captures were also inspected on desktop/mobile, including Full Sheet spacing and
responsive layout. The two health checks passed in 12.3 seconds.

The owner's accepted manual Pass 2 QA remains the manual acceptance record.
No screenshot baseline was regenerated. The full prior 120-case Pass 2 run was
not repeated; this release uses targeted critical coverage and exact source/ZIP
equivalence. Existing visual fixtures that rewrite canonical Character content
were avoided. Automated accessibility scans do not replace manual assistive-
technology testing.

## Independent review

A fresh read-only reviewer checked the release diff, draft verification record,
source requirements, historical preservation and exact package comparison, and
ran package validation and hygiene checks. No Critical, Important or Minor
findings were reported. The reviewer excluded then-pending staging outcomes;
the completed results and captures above were inspected separately before handoff.
Broad unchanged feature behavior remains supported by the accepted PR #7/#8
reviews and the targeted regressions here. Publication and production behavior
remain outside this preparation stage.

## Publication boundary and limitations

This branch prepares a PR for the owner to review and merge. It does not authorize
tagging, release publication or production deployment. Future README asset links
are planned destinations until publication. Previous releases and their assets
remain untouched.

Direct Character document URLs in ordinary WordPress uploads are publicly accessible,
even though Character pages and document metadata require authentication. These
are not confidential attachments; do not upload Storyguide-only or sensitive files
without a separate protected-storage solution.

The package headers declare WordPress 6.5+ and PHP 8.0+. Verification covers the
configured staging host, not every supported WordPress/PHP combination. Production
remains untouched. Before an operator upgrades production, back up its database,
uploads and installed theme/plugin packages.
