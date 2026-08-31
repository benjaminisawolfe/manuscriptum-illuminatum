# Installation and Updates

Manuscriptum Illuminatum is distributed as two ordinary WordPress ZIP packages: the Ligatura Manuscripti Illuminati plugin and the Paginae Manuscripti Illuminati theme. Install both on the same site.

## Before installing

Confirm that the site runs WordPress 6.5 or newer and PHP 8.0 or newer. Back up an existing site's database and `wp-content` before replacing an earlier version. Keep the site private while it contains campaign material unless you have intentionally chosen public access.

## New installation

1. Download `ligatura-manuscripti-illuminati.zip` and `paginae-manuscripti-illuminati.zip` from the main README.
2. Open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload `ligatura-manuscripti-illuminati.zip`, install it, and activate **Ligatura Manuscripti Illuminati**.
4. Open **Appearance → Themes → Add New Theme → Upload Theme**.
5. Upload `paginae-manuscripti-illuminati.zip`, install it, and activate **Paginae Manuscripti Illuminati**.
6. Open **Settings → Permalinks** and click **Save Changes**.
7. Review **Settings → Reading**, **Appearance → Menus**, and **Appearance → Customize**.

Plugin-first activation ensures the campaign post types and taxonomies exist before the theme presents them.

## Automatic first-run setup

The theme checks for six Pages: Home, Annales, Speculum, Personae, Commentarii, and Covenant Records. It reuses safe matches and creates only missing Pages. Existing body content and Page configuration are retained.

On a clearly unconfigured WordPress installation, setup selects Home as the static front page and Annales as the Posts page. It also creates and assigns a six-item Primary Menu when that menu location has never been configured. If an expected slug is occupied, setup stops that part safely and shows an administrator notice; it does not silently add a numeric suffix.

## Updating

1. Take a database backup and retain copies of the installed theme and plugin.
2. Upload the newer plugin ZIP through **Plugins → Add New Plugin → Upload Plugin** and approve replacing the current version.
3. Upload the newer theme ZIP through **Appearance → Themes → Add New Theme → Upload Theme** and approve replacing the current version.
4. Confirm that Ligatura Manuscripti Illuminati remains active and Paginae Manuscripti Illuminati remains the active theme.
5. Save **Settings → Permalinks** once.
6. Check Home and each main section on desktop and mobile.

Updating code does not erase campaign entries, user roles, assignments, Pages, menus, or Customizer settings. Backups are still important because setup and upgrade routines can update WordPress options and capability definitions.

## Removing or deactivating

Deactivating Ligatura Manuscripti Illuminati does not delete campaign content, custom fields, or project roles. The campaign content types will not be editable or displayed normally until the plugin is active again. Switching themes retains data and menus, although menu locations may need to be assigned for the newly active theme.
