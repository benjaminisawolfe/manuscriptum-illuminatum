# Changelog

## 1.0.2 — 2026-10-15

Maintenance release.

- Includes article-authored H1–H6 headings in Speculum tables of contents, in document order and up to the configured maximum level.
- Adds maximum-level choices from H1 only through H1–H6, retaining the default of H1–H4 and saved numeric values 2/3/4, now inclusive of H1.
- Preserves valid, unique authored heading anchors and generates deterministic IDs that avoid collisions with other article IDs.
- Improves desktop and mobile hierarchy with progressive H1–H6 indentation and wrapping for long headings, preserving keyboard navigation and visible focus.
- Adds regression coverage for heading levels, settings compatibility, fragment navigation, content scoping, privacy boundaries, responsive layout, and automated accessibility checks.

## 1.0.1 — 2026-09-05

Maintenance release.

- Fixes published entries disappearing from front-end directories because of incorrect capability checks.
- Includes Speculum entries without an Entry Type in the default directory.
- Prevents automatic excerpts from recursively rendering directory content.
- Fixes Annales pagination collisions with single-post routes.
- Strengthens regression coverage and package validation.

## 1.0 — 2026-08-31

Initial public release.

- Provides the coordinated Paginae Manuscripti Illuminati theme and Ligatura Manuscripti Illuminati plugin.
- Provides Annales, Speculum, Personae, Commentarii, Covenant Records, and Saga Topic routes and presentations.
- Provides responsive manuscript styling, breadcrumbs, configurable typography, colours, layout, compact header and footer, and Home-page collections.
- Provides safe, idempotent setup for the six default Pages, Reading settings, an editable Primary Menu, and submenu support.
- Provides campaign post types, taxonomies, structured Persona data, Storyguide-only notes, Player assignments, authorship validation, and protected media workflows.
- Provides authenticated searchable directories, filters, pagination, and accessible dynamic status controls.
- Enables login-only privacy by default with an explicit public-mode configuration switch.
