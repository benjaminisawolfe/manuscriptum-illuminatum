# Manuscriptum Illuminatum v1.1.0

Proposed release notes. Prepared for review; not yet tagged or published.

This coordinated minor release updates Paginae Manuscripti Illuminati and
Ligatura Manuscripti Illuminati to **1.1.0**, incorporating reviewed PRs #7 and #8.

- Fixes duplicate project-owned legacy Player/Storyguide roles through a versioned
  migration on update, preserving unrelated role assignments and individual grants.
- Lets assigned Players save authorized Character metadata. Partial saves preserve
  omitted fields instead of clearing existing values.
- Restores working Visual rich-text editors in campaign meta boxes and removes
  generic Add Media buttons from those controls.
- Adds Character document upload, attachment and unlinking for **PDF, DOC, DOCX,
  ODT, RTF and TXT**, with Full Sheet and Documents sections on Character pages.
  Unlinking a document preserves the file in the media library.

**Document privacy:** Direct URLs to files in ordinary WordPress uploads are
accessible without authentication, including on a login-only site. These are
**not private or confidential attachments**. Do not upload Storyguide-only or
sensitive documents until a separate protected-storage solution exists.

**Before updating:** Back up the **production database, uploads and existing
theme/plugin packages**. This release includes the versioned legacy-role migration
from Pass 1. Replace the plugin first, then the theme, using WordPress's normal
package replacement flow. Review roles, Character editing and campaign routes
after updating.

Both package headers require **WordPress 6.5 or newer** and **PHP 8.0 or newer**.
These are declared minimums, not a claim that every WordPress/PHP combination
has been tested. Document acceptance also depends on WordPress and the host's
file-type identification.

Planned downloads, available only after separately authorized publication:

- [Paginae Manuscripti Illuminati theme](https://github.com/benjaminisawolfe/manuscriptum-illuminatum/releases/download/v1.1.0/paginae-manuscripti-illuminati.zip)
- [Ligatura Manuscripti Illuminati plugin](https://github.com/benjaminisawolfe/manuscriptum-illuminatum/releases/download/v1.1.0/ligatura-manuscripti-illuminati.zip)

GitHub publication does not deploy either package to the production WordPress site.
