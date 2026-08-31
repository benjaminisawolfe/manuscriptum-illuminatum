# Site Configuration

## Reading settings and default Pages

Manuscriptum Illuminatum expects these routes:

| Page | Route |
| --- | --- |
| Home | `/` |
| Annales | `/news/` |
| Speculum | `/wiki/` |
| Personae | `/characters/` |
| Commentarii | `/journals/` |
| Covenant Records | `/covenant-records/` |

The initial setup configures Home and Annales under **Settings → Reading** only when WordPress is clearly unconfigured. It does not replace a later administrator selection. Page body content is editable and is never overwritten by repair checks. The section Pages can therefore contain introductions above their generated directories.

If a required Page is deleted, the theme notices during a later administrator request. It restores an unambiguous matching Page from Trash so its content is retained, or safely recreates the Page when no matching Page remains. Setup does not write to the database on ordinary visitor requests. A draft, private, ambiguous, or conflicting Page produces an administrator notice so its content is not unexpectedly published or replaced.

## Menus and submenus

The header renders the standard WordPress **Primary Menu** location.

1. Go to **Appearance → Menus**.
2. Create or select a menu.
3. Add Pages, campaign entries, taxonomy links, or custom links.
4. Drag items into the desired order.
5. To create a submenu, drag a child item slightly to the right below its parent.
6. Select **Primary Menu** under display location and save.

Setup assigns its default menu only if the location has never been assigned. It does not replace a selected menu, reassign a deliberately cleared location, or add items to an administrator's existing menu. Native nested items render as accessible `.sub-menu` lists. Pointer hover/focus works on desktop; a labelled toggle exposes the same children for keyboard and touch users.

## Customizer

Open **Appearance → Customize → Paginae Manuscripti Illuminati**. Depending on the WordPress version, Customize may also appear from the active theme's details screen.

- **Layout:** sidebar arrangement, maximum content width, and sticky header.
- **Header Identity:** short seal text used when no custom logo is selected.
- **Typography:** body, heading, navigation, and accent fonts.
- **Colours and Texture:** the ink, parchment, rubric, gold, blue, and green palette plus parchment texture.
- **Speculum Entries:** automatic table of contents and heading depth.
- **Front Page Sections:** number of full-width latest Commentarii rows.
- **Footer:** plain-text copyright line. `{year}` inserts the current year. Saving an empty value removes the line without leaving empty punctuation.

The footer accepts plain text, not arbitrary HTML. This prevents unsafe markup from being stored or displayed.

## Sidebars and logo

The theme registers left and right sidebars. Their visibility follows the selected layout and whether the sidebar has active widgets. Set a logo through WordPress's Site Identity controls; otherwise the configured seal text, site title, and tagline are shown.
