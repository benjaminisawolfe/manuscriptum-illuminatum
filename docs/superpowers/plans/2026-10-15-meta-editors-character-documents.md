# Visual editors and Character documents implementation plan

> **For agentic workers:** Use systematic debugging for the editor failure and test-driven development for implementation. Independent editor and document modules are delegated; integration, staging deployment and whole-branch verification stay with the primary agent.

**Goal:** Complete the user-supplied Pass 2 brief: usable Visual meta-box editors, no generic meta-box Add Media, and validated Character document relationships.

**Architecture:** Keep editor lifecycle and document validation in the plugin; use native WordPress attachment storage, a narrow upload endpoint and a dedicated array of attachment IDs. The theme displays sanitized Full Sheet content and validated document links. Ordinary uploads remain directly accessible: the UI and operator documentation must disclose this, with no claim of private downloads.

**Tech stack:** WordPress/PHP, TinyMCE and WordPress editor APIs, native media selector, browser JavaScript, existing remote Playwright/runtime-probe harness.

**Spec:** User attachment `codex-pass-2-visual-editors-and-character-documents.md`, supplied with this task.

## Constraints and review focus

- Begin from merged Pass 1 on canonical main; fresh branch, no role or save redesign.
- Disposable staging fixtures only; no local server stack, production, release or merge.
- Preserve omitted fields, explicit clearing, assignment, Storyguide privacy and existing image controls.
- Reject invalid IDs, foreign Player media, mismatched extensions/content, unknown MIME types, failed uploads and unauthorized writes without changing the relationship.
- Revalidate files on display; do not show missing files, private notes, raw IDs or empty sections.
- Test editor remounts and both modes, simultaneous editors, request failure, unlink without deletion, native REST tampering and anonymous file URLs.

## 1. Diagnose and fix editor lifecycle

- [x] Reproduce inert Visual editors with disposable Character/Wiki records; record actual iframe and initialization state.
- [x] Add `tests/meta-editors.spec.js` covering real typing/formatting, mode round trips, update/reload, isolation, Player saves and supported classic mode.
- [x] Implement lifecycle correction in `includes/meta-boxes.php` and `assets/admin.js`, sharing configuration with `media_buttons => false`.
- [x] Wire required editor script dependencies in `includes/class-plugin.php`; verify core media remains intact.

## 2. Implement document validation and relationships

- [x] Verify missing-document-UI regression fails before implementation.
- [x] Create `includes/character-documents.php` with `register_meta()`, `register_rest_routes()`, `save_form()`, `render_control()` and `get_documents()`.
- [x] Store `_ligatura_character_documents` as an ordered unique integer array. Form field is JSON plus per-Character nonce; absence preserves, `[]` unlinks all.
- [x] REST `/characters/{id}/documents` reads/validates selections or saves `{ids:[]}`; `/documents/upload` accepts multipart `document` and creates a Media Library attachment without committing a relationship.
- [x] Require edit permission and explicit nonce on writes; upload also requires existing upload permission. Restricted Players may add only their own uploads or retain existing links.
- [x] Validate extension, core allowed MIME/type checks and actual Fileinfo MIME. Reject ambiguous formats with an understandable host-compatibility message rather than force allowing.
- [x] Add fixture-based API/form/ownership/file-format/size/omission/unlink tests in `tests/character-documents.spec.js`.

## 3. Integrate admin and front end

- [x] Add `assets/character-documents.js` for separate multiple-file uploads, select-only media dialog, stable list, unlink and pending-save status. Preserve native error feedback and accessible labels.
- [x] Load module and hooks; render its control below Full Sheet. Keep generic main-editor and Campaign Image media controls.
- [x] Add conditional Full Sheet and Documents sections to `single-ligatura_character.php`, with minimal theme styling and escaping.
- [x] Verify actual saved metadata, authorized/anonymous views, direct file URL behavior and accessibility with generated documents only.

## 4. Package, stage, review and hand off

- [x] Run repository guard, syntax/static inspection, release-hygiene tests and source/ZIP validation; inspect diff.
- [x] Commit/push branch and deploy both canonical ZIPs through the authorized staging replacement flow.
- [x] Run targeted Pass 2 and Pass 1 regressions, relevant existing desktop/mobile visual and accessibility suites. Inspect mismatches, never update baselines merely to pass.
- [x] Independently review authorization, upload validation and lifecycle changes; resolve material findings and rerun affected tests.
- [ ] Commit the report with root cause, format results, exact privacy limits, test matrix and rollback/acceptance instructions; push and open a review-ready PR without merging.
