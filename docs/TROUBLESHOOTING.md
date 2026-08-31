# Troubleshooting

## A main route returns 404

Go to **Settings → Permalinks** and click **Save Changes** without changing the structure. Then clear any WordPress, host, or CDN cache. Confirm that Ligatura Manuscripti Illuminati is active and Paginae Manuscripti Illuminati is the active theme.

## A default Page is missing

Visit any WordPress administration screen as an Administrator. The admin-side repair check restores an unambiguous matching Page from Trash or creates a genuinely missing Page; it does not overwrite Page body content. If a notice reports a slug conflict, search Pages (including Trash) and Media for the named slug, resolve the conflict, empty Trash if appropriate, and load an admin screen again.

Setup intentionally does not publish an existing draft/private Page or choose a suffixed URL. Review that Page yourself, publish or rename it as intended, and retry by loading an admin screen.

## Home or Annales uses the wrong Page

Review **Settings → Reading**. For the standard layout, select **A static page**, choose Home for the homepage, and choose Annales for the Posts page. Setup makes these choices only on a clearly unconfigured installation and respects later administrator changes.

## The header menu is empty or incorrect

Go to **Appearance → Menus → Manage Locations** and assign the intended menu to **Primary Menu**. Edit its items under **Appearance → Menus**. The theme does not overwrite an assigned menu. If a submenu is missing, confirm that its child item is indented beneath a parent and that the menu was saved.

## Footer text is wrong or missing

Open **Appearance → Customize → Paginae Manuscripti Illuminati → Footer**. Enter plain text and use `{year}` where the current year should appear. An empty setting intentionally hides the line.

## Visitors are sent to login

That is the default privacy mode. See [Content, Privacy, and Roles](CONTENT-PRIVACY-AND-ROLES.md) before choosing public access. Do not make the site public merely to troubleshoot an account or permission problem.

## A Player cannot edit a Persona or select a Character Author

An Administrator or Storyguide must assign the Persona to that Player in the Persona editor. Confirm that the user has the Player role and that the relevant Persona assignment is saved. The permission restrictions are deliberate and should not be bypassed.
