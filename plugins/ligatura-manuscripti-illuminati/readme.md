# Ligatura Manuscripti Illuminati

`ligatura-manuscripti-illuminati` is the data and behaviour plugin for a Manuscriptum Illuminatum Ars Magica campaign site. Install it before activating the companion Paginae Manuscripti Illuminati theme.

## What it provides

- Custom post types for Personae, Speculum entries, Commentarii, and Covenant Records.
- Taxonomies for entry type, character type, Hermetic house, and saga topic.
- Storyguide and least-privilege Player roles with custom capabilities.
- Administrator-controlled Persona assignment and Persona-authored Commentarii.
- Login-only front-end gate and guest feed blocking.
- Storyguide-only wiki notes stored as protected post meta.
- Persona, Speculum, Commentarii, and Covenant Record meta boxes with nonce and capability checks.
- Authenticated dynamic Commentarii, Persona-scoped Commentarii, and Covenant Record directories.
- Registered public-safe meta with REST rules.
- Shortcodes for character, wiki, diary, and related-entry lists.
- Responsive image sizes for campaign artwork.

## Installation

Upload the installable `ligatura-manuscripti-illuminati.zip` package through **Plugins → Add New Plugin → Upload Plugin**, activate it, and then install the companion theme. Assign users either the `Storyguide` or `Player` role as appropriate. Assign one or more Personae to each Player through the Persona editor before they publish Character-authored Commentarii.

The plugin intentionally does not delete campaign content, roles, or custom fields on deactivation.
