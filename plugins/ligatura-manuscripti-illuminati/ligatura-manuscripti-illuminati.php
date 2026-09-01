<?php
/**
 * Plugin Name: Ligatura Manuscripti Illuminati
 * Description: Campaign data, roles, privacy, relationships, and application logic for Manuscriptum Illuminatum.
 * Version: 1.0
 * Author: Ben Wolfe (https://github.com/benjaminisawolfe)
 * Text Domain: ligatura-manuscripti-illuminati
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * License: GPL-3.0-only
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIGATURA_MANUSCRIPTI_ILLUMINATI_VERSION', '1.0' );
define( 'LIGATURA_MANUSCRIPTI_ILLUMINATI_FILE', __FILE__ );
define( 'LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIGATURA_MANUSCRIPTI_ILLUMINATI_URL', plugin_dir_url( __FILE__ ) );

require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/sanitization.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/journal-dates.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/journal-directory.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/covenant-directory.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/speculum-directory.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/personae-directory.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/character-publication.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/character-fields.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/post-types.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/taxonomies.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/roles-capabilities.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/player-access.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/journal-authorship.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/private-notes.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/privacy.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/media.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/meta-boxes.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/shortcodes.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/rest.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/helpers.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/demo-content.php';
require_once LIGATURA_MANUSCRIPTI_ILLUMINATI_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

/**
 * Prepare rewrite rules and role capabilities on activation.
 */
function activate(): void {
	if ( ! defined( 'LIGATURA_MANUSCRIPTI_ILLUMINATI_ACTIVATING' ) ) {
		define( 'LIGATURA_MANUSCRIPTI_ILLUMINATI_ACTIVATING', true );
	}

	PostTypes\register_post_types();
	Taxonomies\register_taxonomies();
	Taxonomies\seed_default_terms();
	RolesCapabilities\add_roles_and_capabilities();
	PlayerAccess\register_assignment_meta();

	PostTypes\rebuild_campaign_rewrite_rules();
	flush_rewrite_rules( false );
	PostTypes\mark_rewrite_rules_flushed();
}

/**
 * Flush rewrite rules, keeping campaign content and roles intact.
 */
function deactivate(): void {
	flush_rewrite_rules();
}

Plugin::instance()->boot();
DemoContent\register_cli_command();
