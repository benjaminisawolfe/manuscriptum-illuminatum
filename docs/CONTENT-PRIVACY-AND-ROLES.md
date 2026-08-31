# Content, Privacy, and Roles

## Main sections

**Annales** uses ordinary WordPress Posts. The editable Annales Page appears at `/news/`, and individual Posts appear under `/news/{post-slug}/`.

**Speculum** is the campaign reference collection. Use it for places, factions, mysteries, politics, history, and lore. Entry Type and Saga Topic terms organize the collection.

**Personae** stores characters and creatures. Structured fields cover identity, Hermetic details, traits, abilities, Arts, spells, equipment, notes, and full character-sheet material. A published Persona requires a Character Type.

**Commentarii** stores journals, letters, visions, lab notes, and other in-character records. Entries can use a Saga Date and an assigned Persona as their visible Character Author.

**Covenant Records** stores resources, laboratories, vis, library holdings, buildings, charter material, and relationships.

**Saga Topics** connect related records across the campaign. The mixed public route is `/saga-topic/{topic-slug}/`; Speculum and Covenant Records also provide section-specific topic collections.

## Roles

- **Administrator:** manages the WordPress installation and all campaign content and settings.
- **Storyguide:** manages campaign entries, terms, and Storyguide-only notes.
- **Player:** creates and publishes their own Commentarii, selects an assigned Persona as Character Author, edits assigned Personae within the plugin's limits, and uses permitted media.

Assign one or more Personae to each Player from the Persona editor before the Player creates Character-authored Commentarii. Players cannot grant themselves assignments, edit another user's Journal, publish new Personae, manage Pages/settings/plugins, or read Storyguide-only material.

## Login-only mode

Login is required by default for front-end pages, feeds, and WordPress REST content. To deliberately make published front-end content public, add this before the “stop editing” line in `wp-config.php`:

```php
define( 'LIGATURA_MANUSCRIPTI_ILLUMINATI_REQUIRE_LOGIN', false );
```

Use `true` or omit the constant for a private site. Ask your web host for help editing `wp-config.php` if necessary.

Public mode does not expose Storyguide-only notes or private posts; their capability checks remain active. Direct media URLs under `wp-content/uploads` do not pass through the plugin's PHP login gate. Use host, CDN, or storage-level access control for confidential files.
