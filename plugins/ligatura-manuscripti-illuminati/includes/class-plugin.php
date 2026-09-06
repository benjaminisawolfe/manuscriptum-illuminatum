<?php
/**
 * Plugin coordinator.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires plugin modules into WordPress hooks.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get the plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function boot(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', __NAMESPACE__ . '\\RolesCapabilities\\maybe_upgrade_roles', 1 );
		add_action( 'init', __NAMESPACE__ . '\\PostTypes\\register_post_types' );
		add_action( 'init', __NAMESPACE__ . '\\PostTypes\\register_rewrite_rules', 11 );
		add_filter( 'query_vars', __NAMESPACE__ . '\\PostTypes\\register_query_vars' );
		add_action( 'init', __NAMESPACE__ . '\\PostTypes\\maybe_flush_rewrite_rules', 20 );
		add_action( 'init', __NAMESPACE__ . '\\Taxonomies\\register_taxonomies' );
		add_action( 'init', __NAMESPACE__ . '\\MetaBoxes\\register_meta_fields' );
		add_action( 'init', __NAMESPACE__ . '\\PlayerAccess\\register_assignment_meta' );
		add_action( 'init', __NAMESPACE__ . '\\DemoContent\\register_demo_meta' );
		add_action( 'init', __NAMESPACE__ . '\\PlayerAccess\\remove_player_author_support', 30 );
		add_action( 'after_setup_theme', __NAMESPACE__ . '\\Media\\register_image_sizes' );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'add_meta_boxes', __NAMESPACE__ . '\\MetaBoxes\\register_meta_boxes' );
		add_action( 'add_meta_boxes', __NAMESPACE__ . '\\PlayerAccess\\register_assignment_meta_box' );
		add_action( 'save_post', __NAMESPACE__ . '\\MetaBoxes\\save_meta_boxes', 10, 2 );
		add_action( 'save_post', __NAMESPACE__ . '\\JournalAuthorship\\validate_persisted_author_after_save', 30, 2 );
		add_action( 'save_post_ligatura_character', __NAMESPACE__ . '\\PlayerAccess\\save_assignment', 20, 2 );
		add_action( 'wp_after_insert_post', __NAMESPACE__ . '\\PlayerAccess\\ensure_character_assignment', 20, 2 );
		add_filter( 'wp_insert_post_data', __NAMESPACE__ . '\\CharacterPublication\\enforce_insert_post_data', 10, 3 );
		add_filter( 'wp_insert_post_data', __NAMESPACE__ . '\\JournalAuthorship\\enforce_insert_post_data', 20, 3 );
		add_filter( 'rest_pre_insert_ligatura_character', __NAMESPACE__ . '\\CharacterPublication\\validate_rest_publication', 10, 2 );
		add_filter( 'rest_pre_insert_ligatura_character', __NAMESPACE__ . '\\PlayerAccess\\reject_player_assignment_rest_write', 15, 2 );
		add_filter( 'rest_pre_insert_ligatura_character', __NAMESPACE__ . '\\PlayerAccess\\validate_player_featured_media', 20, 2 );
		add_filter( 'rest_request_before_callbacks', __NAMESPACE__ . '\\PlayerAccess\\preserve_player_published_character_status', 10, 3 );
		add_filter( 'rest_prepare_ligatura_character', __NAMESPACE__ . '\\PlayerAccess\\expose_player_character_update_action', 20, 3 );
		add_filter( 'rest_pre_insert_ligatura_diary', __NAMESPACE__ . '\\JournalAuthorship\\validate_rest_save', 10, 2 );
		add_action( 'rest_after_insert_ligatura_diary', __NAMESPACE__ . '\\JournalAuthorship\\validate_persisted_author_after_rest_insert', 20, 1 );
		add_filter( 'rest_pre_insert_ligatura_diary', __NAMESPACE__ . '\\PlayerAccess\\validate_player_featured_media', 20, 2 );
		add_filter( 'update_post_metadata', __NAMESPACE__ . '\\PlayerAccess\\protect_player_post_meta_update', 10, 4 );
		add_filter( 'delete_post_metadata', __NAMESPACE__ . '\\PlayerAccess\\protect_player_post_meta_delete', 10, 3 );
		add_filter( 'post_class', __NAMESPACE__ . '\\JournalAuthorship\\remove_technical_author_post_class', 10, 3 );
		add_filter( 'map_meta_cap', __NAMESPACE__ . '\\PlayerAccess\\map_player_post_meta_caps', 20, 4 );
		add_filter( 'rest_authentication_errors', __NAMESPACE__ . '\\Privacy\\require_rest_login' );
		add_action( 'transition_post_status', __NAMESPACE__ . '\\JournalAuthorship\\validate_scheduled_publication', 20, 3 );
		add_action( 'wp_after_insert_post', __NAMESPACE__ . '\\CharacterPublication\\queue_publication_transition_check', 10, 4 );
		add_action( 'set_object_terms', __NAMESPACE__ . '\\CharacterPublication\\queue_type_relationship_check', 10, 4 );
		add_action( 'deleted_term_relationships', __NAMESPACE__ . '\\CharacterPublication\\queue_deleted_type_relationship_check', 10, 3 );
		add_action( 'shutdown', __NAMESPACE__ . '\\CharacterPublication\\enforce_queued_relationship_changes' );
		add_action( 'admin_notices', __NAMESPACE__ . '\\CharacterPublication\\render_admin_notices' );
		add_action( 'admin_notices', __NAMESPACE__ . '\\JournalAuthorship\\render_admin_notice' );
		add_action( 'pre_get_posts', __NAMESPACE__ . '\\JournalDates\\order_main_journal_queries' );
		add_action( 'pre_get_posts', __NAMESPACE__ . '\\PlayerAccess\\scope_player_admin_queries', 20 );
		add_filter( 'rest_ligatura_diary_query', __NAMESPACE__ . '\\PlayerAccess\\scope_player_journal_rest_query', 10, 2 );
		add_filter( 'rest_ligatura_character_query', __NAMESPACE__ . '\\PlayerAccess\\scope_player_character_rest_query', 10, 2 );
		add_filter( 'ajax_query_attachments_args', __NAMESPACE__ . '\\PlayerAccess\\scope_player_attachment_ajax_query' );
		add_filter( 'rest_attachment_query', __NAMESPACE__ . '\\PlayerAccess\\scope_player_attachment_rest_query', 10, 2 );
		add_action( 'admin_menu', __NAMESPACE__ . '\\PlayerAccess\\allow_player_character_collection_menu_gate', 998 );
		add_action( 'admin_menu', __NAMESPACE__ . '\\PlayerAccess\\restrict_player_admin_menu', 999 );
		add_action( 'admin_init', __NAMESPACE__ . '\\PlayerAccess\\enforce_player_admin_screen_access', 1 );
		add_action( 'delete_user', __NAMESPACE__ . '\\PlayerAccess\\reassign_deleted_user_personae' );
		add_filter( 'posts_clauses', __NAMESPACE__ . '\\JournalDates\\order_journal_query_clauses', 10, 2 );
		add_filter( 'manage_ligatura_diary_posts_columns', __NAMESPACE__ . '\\JournalDates\\add_admin_saga_date_column' );
		add_action( 'manage_ligatura_diary_posts_custom_column', __NAMESPACE__ . '\\JournalDates\\render_admin_saga_date_column', 10, 2 );
		add_filter( 'manage_edit-ligatura_diary_sortable_columns', __NAMESPACE__ . '\\JournalDates\\add_sortable_admin_column' );
		add_action( 'template_redirect', __NAMESPACE__ . '\\Privacy\\enforce_login_only', 0 );
		add_action( 'template_redirect', __NAMESPACE__ . '\\JournalDirectory\\maybe_404_invalid_persona_collection', 2 );
		add_action( 'template_redirect', __NAMESPACE__ . '\\CovenantDirectory\\maybe_404_invalid_collection', 2 );
		add_action( 'do_feed', __NAMESPACE__ . '\\Privacy\\block_guest_feeds', 0 );
		add_action( 'do_feed_rdf', __NAMESPACE__ . '\\Privacy\\block_guest_feeds', 0 );
		add_action( 'do_feed_rss', __NAMESPACE__ . '\\Privacy\\block_guest_feeds', 0 );
		add_action( 'do_feed_rss2', __NAMESPACE__ . '\\Privacy\\block_guest_feeds', 0 );
		add_action( 'do_feed_atom', __NAMESPACE__ . '\\Privacy\\block_guest_feeds', 0 );
		add_action( 'rest_api_init', __NAMESPACE__ . '\\Rest\\register_routes' );
		add_action( 'rest_api_init', __NAMESPACE__ . '\\JournalDirectory\\register_rest_route' );
		add_action( 'rest_api_init', __NAMESPACE__ . '\\CovenantDirectory\\register_rest_route' );
		add_action( 'rest_api_init', __NAMESPACE__ . '\\SpeculumDirectory\\register_rest_route' );
		add_action( 'rest_api_init', __NAMESPACE__ . '\\PersonaeDirectory\\register_rest_route' );
		add_action( 'rest_api_init', __NAMESPACE__ . '\\DemoContent\\register_rest_routes' );
		add_action( 'init', __NAMESPACE__ . '\\Shortcodes\\register_shortcodes' );
		add_action( 'created_ligatura_entry_type', __NAMESPACE__ . '\\PostTypes\\flush_term_dependent_rewrite_rules' );
		add_action( 'edited_ligatura_entry_type', __NAMESPACE__ . '\\PostTypes\\flush_term_dependent_rewrite_rules' );
		add_action( 'delete_ligatura_entry_type', __NAMESPACE__ . '\\PostTypes\\flush_term_dependent_rewrite_rules' );
		add_action( 'created_ligatura_saga_topic', __NAMESPACE__ . '\\PostTypes\\flush_term_dependent_rewrite_rules' );
		add_action( 'edited_ligatura_saga_topic', __NAMESPACE__ . '\\PostTypes\\flush_term_dependent_rewrite_rules' );
		add_action( 'delete_ligatura_saga_topic', __NAMESPACE__ . '\\PostTypes\\flush_term_dependent_rewrite_rules' );
		add_filter( 'the_content', __NAMESPACE__ . '\\Shortcodes\\append_directory_listing_to_root_page', 20 );
		add_filter( 'page_link', __NAMESPACE__ . '\\PostTypes\\campaign_page_link', 10, 2 );
		add_filter( 'post_type_link', __NAMESPACE__ . '\\PostTypes\\campaign_post_type_link', 10, 4 );
		add_filter( 'term_link', __NAMESPACE__ . '\\PostTypes\\entry_type_term_link', 10, 3 );
		add_filter( 'get_the_excerpt', __NAMESPACE__ . '\\PrivateNotes\\strip_private_notes_from_excerpt', 20, 2 );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'ligatura-manuscripti-illuminati',
			false,
			dirname( plugin_basename( LIGATURA_MANUSCRIPTI_ILLUMINATI_FILE ) ) . '/languages'
		);
	}

	/**
	 * Load admin CSS/JS on campaign edit screens.
	 *
	 * @param string $hook_suffix Admin hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, PostTypes\get_post_type_names(), true ) ) {
			return;
		}

		wp_enqueue_style(
			'ligatura-manuscripti-illuminati-admin',
			LIGATURA_MANUSCRIPTI_ILLUMINATI_URL . 'assets/admin.css',
			array(),
			LIGATURA_MANUSCRIPTI_ILLUMINATI_VERSION
		);

		wp_enqueue_script(
			'ligatura-manuscripti-illuminati-admin',
			LIGATURA_MANUSCRIPTI_ILLUMINATI_URL . 'assets/admin.js',
			array( 'wp-data', 'wp-dom-ready' ),
			LIGATURA_MANUSCRIPTI_ILLUMINATI_VERSION,
			true
		);
	}
}
