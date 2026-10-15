# Visual editors and Character documents

This implements Pass 2 on top of accepted Pass 1 (`ffea229`), on
`codex/fix-meta-editors-add-character-documents`. Package versions remain 1.0.2;
these ZIPs are staging builds, not a published release.

## Editor failure and correction

The staging block editor initialized TinyMCE before Gutenberg relocated its
meta-box DOM. TinyMCE reported `initialized=true` and its cached body reported
`contenteditable=true`, but the connected iframe had a new, empty document.
`editor.getBody()` no longer equalled `editor.iframeElement.contentDocument.body`.
The same failure was reproduced by moving a Wiki meta box with its keyboard
ordering button. Code mode used the surviving textarea and therefore worked.

WordPress explicitly documents that an initialized TinyMCE editor cannot safely
be moved in the DOM: [wp_editor reference](https://developer.wordpress.org/reference/functions/wp_editor/).
The correction uses its [dynamic editor API](https://make.wordpress.org/core/2017/05/20/editor-api-changes-in-4-8/):

- One shared renderer configures every plugin meta-box editor with
  `media_buttons => false` and defers automatic TinyMCE initialization with
  `wp_skip_init`.
- Admin code initializes the existing WordPress editor configuration once the
  textarea reaches its visible container. It leaves core wrappers, tabs and
  Quicktags in place.
- Visual changes synchronize the textarea used by Gutenberg's separate
  meta-box form. Moving a box removes its editor while preserving this value;
  initialization stays suspended through the full ordering click or drag event,
  then resumes after placement. Browser tracing found that a MutationObserver
  can run between capture and bubble listeners, before WordPress's move handler;
  removing an editor without that suspension allowed it to initialize too early
  again. A stale iframe is never serialized over the last synchronized content.
- The same API supports the core classic screen. No Classic Editor plugin was
  installed on staging; tests select core classic editing only for disposable
  administrator-owned fixture requests through an excluded test-support filter.

The main body editor and Campaign Image controls remain available. No role,
assignment, publication or Pass 1 save-path changes are part of this pass.

## Character Documents

The Full Sheet meta box now has a separate, labelled document area. Users choose
one or more files and select **Upload Document**, or choose their existing files
with **Attach Document**. Each row shows a document title, format and unlink
action. Save the Character to persist the list. Unlinking never deletes the
underlying attachment; replacing a document means unlinking the old entry and
uploading or selecting its replacement.

The relationship is `_ligatura_character_documents`, a single ordered array of
unique Media Library IDs, limited to 50 entries. An omitted form/API field keeps
the current list; an explicit empty list unlinks everything. No file bytes are
stored in post meta. Uploads create native WordPress attachments; a cancelled
Character edit may therefore leave an unlinked upload in its owner's library.

Dedicated REST routes under `ligatura-manuscripti-illuminati/v1`:

| Route | Behavior |
|---|---|
| `characters/{id}/documents` GET | Read linked documents; optional `ids` validates a proposed library selection |
| `characters/{id}/documents` POST | Validate and persist the complete supplied `ids` array |
| `characters/{id}/documents/upload` POST | Validate multipart `document` and create a native attachment; does not save the relationship |

Writes require an explicit WordPress REST nonce or a per-Character form nonce,
plus `edit_post` on that Character. Uploads also require `upload_files`. Restricted
Players may newly select only their own uploads; they may retain or unlink files
already linked to their assigned Character. Administrator/Storyguide access uses
the existing capabilities. The entire selection is validated before the single
relationship write.

Generic metadata writes are denied, including XML-RPC custom-field capabilities.
The protected key is absent from the core REST metadata schema. Only the dedicated
validators write it; hiding a field from REST alone would not secure other
WordPress custom-field APIs.

The Character template previously omitted `ligatura_full_sheet`. It now renders
that field once in a conditional **Full Sheet** section using `wpautop` and
`wp_kses_post`, followed by a conditional **Documents** list. The main body is
unchanged. Document output rechecks post/password access, attachment existence,
local upload storage, MIME and file type. Unavailable entries are omitted from
the front end and labelled for unlinking in the editor.

## File validation and privacy

The extension allowlist is PDF, DOC, DOCX, ODT, RTF and TXT. It narrows the current
WordPress MIME policy; it does not add global MIME permissions. Native upload APIs
and `wp_check_filetype_and_ext` validate each upload, with an additional strict
Fileinfo comparison against the actual content. This matters because
[core's validator](https://developer.wordpress.org/reference/functions/wp_check_filetype_and_ext/)
intentionally permits some generic ZIP/binary MIME results for application files.
Renaming an ordinary archive to a document extension must not bypass the list.

Ambiguous formats are rejected with an explanation and a suggestion to export PDF
or plain text. File inspection does not extract, execute or render file contents;
it is type validation, not a malware scanner. Host upload limits are respected.
Missing files, failed authorization, invalid selections, storage failures and
size/type failures produce errors rather than silently changing relationships.

**Files in ordinary WordPress upload storage are not confidential downloads.**
The page login gate does not protect their direct URLs. There is no private
storage or protected-download facility in the current architecture; adding a PHP
download endpoint would still leave the original URL accessible. The document UI
therefore warns that anyone with the file link can access it and prohibits using
this feature for Storyguide-only or confidential material. Truly private downloads
need a separately agreed storage/host/CDN access-control design and bypass tests.

Staging on 15 October 2026 accepted safe generated examples of every allowed
format with HTTP 201: PDF (`application/pdf`), DOC (`application/msword`), DOCX
(`application/vnd.openxmlformats-officedocument.wordprocessingml.document`), ODT
(`application/vnd.oasis.opendocument.text`), RTF and TXT (`text/plain`). The DOC
fixture was converted from the generated DOCX with the workstation's existing
LibreOffice installation; it was not a renamed archive. Fileinfo can differ on
other hosts, so an ambiguous file still fails closed there. No fixture binaries
are committed or packaged.

Anonymous requests to the generated TXT and PDF URLs returned HTTP 200, the
exact original bytes and `text/plain` / `application/pdf` respectively. The same
visitor was redirected from the Character page to login and denied the document
metadata API. A password-protected Character also omitted Full Sheet and Documents
until authorized. These are verified page/API protections, not file protection.

## Changed-file inventory

All paths below are relative to the repository root.

| Files | Purpose |
|---|---|
| `plugins/ligatura-manuscripti-illuminati/includes/meta-boxes.php`, `assets/admin.js` | Shared editor configuration and TinyMCE lifecycle/synchronization |
| Plugin `includes/character-documents.php`, `assets/character-documents.js`, `assets/admin.css` | Document validation, routes, persistence, upload/select/unlink UI and styling |
| Plugin `ligatura-manuscripti-illuminati.php`, `includes/class-plugin.php` | Module hooks and scoped script dependencies/cache versions |
| `themes/paginae-manuscripti-illuminati/single-ligatura_character.php`, `assets/css/main.css`, `functions.php` | Full Sheet/Documents rendering, styling and stylesheet cache version |
| `tests/meta-editors.spec.js`, `tests/meta-editor-links.spec.js`, `tests/character-documents.spec.js` | Editor interaction, persistence, document UI, API and security regressions |
| `tests/personae-single.spec.js`, two `tests/visual/personae.spec.js-snapshots/persona-entry-*-win32.png` images | Verify the newly visible Full Sheet content/spacing and its intentional desktop/mobile presentation |
| `tests/support/document-files.js`, `tests/support/document-fixture.js` | Generated harmless files and disposable role/Character/media fixtures |
| `tests/support/mi-runtime-regression-probes/meta-saving.php`, `probe.php` | Excluded raw-meta/capability inspection and fixture-only classic/size-limit probes |
| `packages/ligatura-manuscripti-illuminati.zip`, `packages/paginae-manuscripti-illuminati.zip` | Canonical distributable artifacts rebuilt from source |
| `docs/CONTENT-PRIVACY-AND-ROLES.md`, this report, `docs/superpowers/plans/2026-10-15-meta-editors-character-documents.md` | Privacy guidance, findings, acceptance/rollback and implementation checklist |

## Staging packages and scope of verification

Both canonical ZIPs from commit `512e595` were replaced successfully through the
authorized staging WordPress administrator package flow. Runtime source was never
edited on the server. The theme and plugin remain version 1.0.2; changed assets use
content-based cache versions. No production deployment, merge or release was made.

| Package | Bytes / files | SHA-256 |
|---|---|---|
| `packages/paginae-manuscripti-illuminati.zip` | 489125 / 46 | `194f66c6d4ce372e154afd2536766b04592951fb869626fb95274b5591b37850` |
| `packages/ligatura-manuscripti-illuminati.zip` | 91657 / 29 | `65df689d246104a45fdfcb7408d9f5c2e22206165e874d3714dd5ca2e0cb8ede` |

Package validation confirmed exact source/ZIP identity. Full archive inventories
were inspected; test probes, generated files, auth state, documentation and caches
are excluded. Repository-guard tests passed 6/6; release-hygiene tests passed 52/52.
JavaScript syntax and diff-whitespace checks passed. No local PHP CLI was available;
PHP routes, form handling and templates were exercised on the existing remote host.

Verification is browser automation plus inspection of the resulting screenshots,
not a claim of completed human operator acceptance. The new document sections
received scoped WCAG A/AA axe checks at 1280px and 393px with no violations and no
horizontal overflow. Broader existing axe coverage passed across desktop/mobile.
This does not replace assistive-technology testing or the operator steps below.

| Automated coverage | Result |
|---|---|
| Character document UI, role APIs, six formats, rejection/size/ownership, POST/REST omission/tampering, unlink, missing files and anonymous URLs | 10 passed |
| Block/classic/assigned-Player/Storyguide Visual editors, toolbar links, independent fields, mode round trips, repeat saves, Wiki move/restore, retained core media controls | 6 passed |
| Existing Pass 1 partial saves, nonces, status/author/assignment boundaries, private notes, media ownership and Player access | 19 passed |
| Existing Character publication/presentation/editor regressions, including updated Full Sheet content/spacing on desktop and mobile | 7 passed |
| Existing desktop/mobile smoke and privacy checks | 22 passed |
| Existing desktop/mobile WCAG A/AA axe checks | 26 passed |
| Existing desktop/mobile visual comparisons | 30 passed after reviewed Character baseline updates and an unchanged home-page retry |

The 16 new tests ran against the final plugin. The later theme-only correction
adds spacing below the new Full Sheet heading; affected presentation/document
checks were rerun after deploying that ZIP. The 48 read-only front-end checks ran
before the final admin-only move/empty-library correction; the only later
front-end change is that scoped Full Sheet spacing rule. Earlier failed runs exposed
the real native-picker default and reorder-event race; both were corrected and
the affected tests passed without changing campaign content to make tests pass.

The initial combined regression run finished 40 passed / 1 failed: the legacy
Character test explicitly expected Full Sheet to be hidden. It now verifies the
saved paragraphs appear once and clear their heading, and passes on both viewport
sizes. Initial visual comparison was 27 passed / 3 failed. Desktop/mobile
Character differences were inspected, a real heading-spacing defect was corrected,
and only those two intentional baselines were refreshed and verified again without
update mode. The unrelated mobile home-page difference was 117 pixels at text
edges; its unchanged baseline passed on retry. No tolerance was relaxed.

Across the final applicable results, 120 distinct browser cases passed. Repeated
runs are not counted again. The mobile duplicate of document mutation fixtures is
intentionally skipped; their rendered sections are checked at both viewport sizes
inside the desktop fixture. Human operator acceptance is still separate.

Verification commands (with the authorized staging URL and ignored authenticated
storage-state file supplied through the existing Playwright environment variables):

```powershell
npm run check:repository
npm run test:repository-guard
npm run test:release-hygiene
git diff --check
npm run build:packages
npm run validate:packages
node scripts/check-release-hygiene.mjs --list

npx playwright test tests/character-documents.spec.js tests/meta-editors.spec.js tests/meta-editor-links.spec.js tests/meta-saving.spec.js tests/meta-save-boundaries.spec.js tests/player-access.spec.js tests/personae-single.spec.js --project=desktop
npx playwright test tests/smoke.spec.js tests/privacy.spec.js tests/a11y
npx playwright test tests/visual
npx playwright test tests/personae-single.spec.js --grep 'compact header, six-field'
npx playwright test tests/visual/personae.spec.js --grep 'single Persona compact entry'
```

`MANUSCRIPTUM_TEST_DOC_FIXTURE` additionally pointed to the ignored generated
legacy DOC file for the format test. Without it, that particular fixture is
reported as uncovered rather than silently represented by renamed ZIP bytes.
The scoped classic-editor and server-size-limit probes are fixture-only test
support, never distributable runtime code. Tests remove their temporary users,
posts and media; the existing visual fixture helper restores seeded data.
The final staging health check confirmed both components active and the temporary
runtime-probe plugin absent.

## Operator acceptance and rollback

1. Review the PR and staging results. On a disposable assigned Character, type and
   format in both Visual editors, switch modes, save twice with a reload, upload
   two harmless documents, unlink one, and check the front-end list.
2. Confirm that the direct-URL limitation is suitable for the intended files.
   Do not upload real private campaign material during acceptance.
3. Before an independently authorized production rollout, back up the database,
   upload storage and currently installed theme/plugin packages together. This
   task does not authorize a production deployment or release.
4. Build and validate both ZIPs from the accepted canonical checkout. Replace
   the plugin and theme through the normal WordPress administrator flow, then
   repeat the focused editor/document acceptance steps.
5. To roll back application code, reinstall the prior matching theme/plugin ZIPs.
   Existing attachment records, files and document-list metadata remain; old
   versions simply do not display the new document list. Do not delete Media
   Library items as a rollback shortcut, since another record may reference them.
6. A database/uploads restore reverses content changes only to the backup point
   and can discard later edits. Use it only as an explicitly chosen coordinated
   recovery, not automatically after a code rollback.
