<?php
/**
 * Custom post type registration.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const REWRITE_RULE_VERSION = '2026-08-29-covenant-collections-v9';
const REWRITE_OPTION       = 'ligatura_manuscripti_illuminati_rewrite_rule_version';

/**
 * Return configured custom post type names.
 *
 * @return string[]
 */
function get_post_type_names(): array {
	return array( 'ligatura_character', 'ligatura_wiki', 'ligatura_diary', 'ligatura_covenant' );
}

/**
 * Return the project-specific %posttype% token values for first-class CPTs.
 *
 * @return array<string,string>
 */
function get_permalink_roots(): array {
	return array(
		'ligatura_wiki'      => 'wiki',
		'ligatura_character' => 'characters',
		'ligatura_diary'     => 'journals',
		'ligatura_covenant'  => 'covenant-records',
	);
}

/**
 * Return the URL segment represented by %posttype% for a campaign CPT.
 */
function get_permalink_root_for_post_type( string $post_type ): string {
	$roots = get_permalink_roots();

	return $roots[ $post_type ] ?? '';
}

/**
 * Return the campaign CPT represented by a URL segment.
 */
function get_post_type_for_permalink_root( string $root ): string {
	$root = sanitize_title( $root );

	foreach ( get_permalink_roots() as $post_type => $segment ) {
		if ( $root === $segment ) {
			return $post_type;
		}
	}

	return '';
}

/**
 * Return the editable directory Page URL for a campaign CPT.
 */
function get_directory_url( string $post_type ): string {
	$root = get_permalink_root_for_post_type( $post_type );

	if ( '' === $root ) {
		return '';
	}

	return home_url( user_trailingslashit( $root ) );
}

/**
 * Build the custom capability map for a campaign post type.
 *
 * @param string $singular Singular capability fragment.
 * @param string $plural   Plural capability fragment.
 * @return array<string,string>
 */
function capability_map( string $singular, string $plural ): array {
	return array(
		'edit_post'              => "edit_{$singular}",
		'read_post'              => "read_{$singular}",
		'delete_post'            => "delete_{$singular}",
		'edit_posts'             => "edit_{$plural}",
		'edit_others_posts'      => "edit_others_{$plural}",
		'delete_posts'           => "delete_{$plural}",
		'publish_posts'          => "publish_{$plural}",
		'read_private_posts'     => "read_private_{$plural}",
		'delete_private_posts'   => "delete_private_{$plural}",
		'delete_published_posts' => "delete_published_{$plural}",
		'delete_others_posts'    => "delete_others_{$plural}",
		'edit_private_posts'     => "edit_private_{$plural}",
		'edit_published_posts'   => "edit_published_{$plural}",
		'create_posts'           => "create_{$plural}",
	);
}

/**
 * Return post type configuration used by registration and role setup.
 *
 * @return array<string,array<string,mixed>>
 */
function get_definitions(): array {
	$roots = get_permalink_roots();

	return array(
		'ligatura_character' => array(
			'singular'    => __( 'Character', 'ligatura-manuscripti-illuminati' ),
			'plural'      => __( 'Characters', 'ligatura-manuscripti-illuminati' ),
			'menu_icon'   => 'dashicons-groups',
			'slug'        => $roots['ligatura_character'],
			'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author', 'custom-fields' ),
			'capability'  => array( 'ligatura_character', 'ligatura_characters' ),
			'description' => __( 'Ars Magica magi, companions, grogs, covenfolk, NPCs, and creatures.', 'ligatura-manuscripti-illuminati' ),
		),
		'ligatura_wiki'      => array(
			'singular'    => __( 'Wiki Entry', 'ligatura-manuscripti-illuminati' ),
			'plural'      => __( 'Wiki Entries', 'ligatura-manuscripti-illuminati' ),
			'menu_icon'   => 'dashicons-book-alt',
			'slug'        => $roots['ligatura_wiki'],
			'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author' ),
			'capability'  => array( 'ligatura_wiki_entry', 'ligatura_wiki_entries' ),
			'description' => __( 'Campaign wiki entries for places, factions, mysteries, politics, and lore.', 'ligatura-manuscripti-illuminati' ),
		),
		'ligatura_diary'     => array(
			'singular'    => __( 'Journal', 'ligatura-manuscripti-illuminati' ),
			'plural'      => __( 'Journals', 'ligatura-manuscripti-illuminati' ),
			'menu_icon'   => 'dashicons-welcome-write-blog',
			'slug'        => $roots['ligatura_diary'],
			'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author', 'comments', 'custom-fields' ),
			'taxonomies'  => array( 'post_tag' ),
			'capability'  => array( 'ligatura_diary_entry', 'ligatura_diary_entries' ),
			'description' => __( 'Player journals, letters, visions, and in-character lab notes.', 'ligatura-manuscripti-illuminati' ),
		),
		'ligatura_covenant'  => array(
			'singular'    => __( 'Covenant Record', 'ligatura-manuscripti-illuminati' ),
			'plural'      => __( 'Covenant Records', 'ligatura-manuscripti-illuminati' ),
			'menu_icon'   => 'dashicons-admin-home',
			'slug'        => $roots['ligatura_covenant'],
			'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ),
			'capability'  => array( 'ligatura_covenant_record', 'ligatura_covenant_records' ),
			'description' => __( 'Covenant resources, library, laboratories, vis stores, buildings, charter, and relationships.', 'ligatura-manuscripti-illuminati' ),
		),
	);
}

/**
 * Register all campaign post types.
 */
function register_post_types(): void {
	foreach ( get_definitions() as $post_type => $definition ) {
		$singular = $definition['singular'];
		$plural   = $definition['plural'];
		$labels   = array(
			'name'                  => $plural,
			'singular_name'         => $singular,
			'add_new'               => __( 'Add New', 'ligatura-manuscripti-illuminati' ),
			'add_new_item'          => sprintf(
				/* translators: %s: singular post type label. */
				__( 'Add New %s', 'ligatura-manuscripti-illuminati' ),
				$singular
			),
			'edit_item'             => sprintf(
				/* translators: %s: singular post type label. */
				__( 'Edit %s', 'ligatura-manuscripti-illuminati' ),
				$singular
			),
			'new_item'              => sprintf(
				/* translators: %s: singular post type label. */
				__( 'New %s', 'ligatura-manuscripti-illuminati' ),
				$singular
			),
			'view_item'             => sprintf(
				/* translators: %s: singular post type label. */
				__( 'View %s', 'ligatura-manuscripti-illuminati' ),
				$singular
			),
			'search_items'          => sprintf(
				/* translators: %s: plural post type label. */
				__( 'Search %s', 'ligatura-manuscripti-illuminati' ),
				$plural
			),
			'not_found'             => __( 'No entries found.', 'ligatura-manuscripti-illuminati' ),
			'not_found_in_trash'    => __( 'No entries found in Trash.', 'ligatura-manuscripti-illuminati' ),
			'all_items'             => sprintf(
				/* translators: %s: plural post type label. */
				__( 'All %s', 'ligatura-manuscripti-illuminati' ),
				$plural
			),
			'archives'              => sprintf(
				/* translators: %s: plural post type label. */
				__( '%s Archives', 'ligatura-manuscripti-illuminati' ),
				$plural
			),
			'featured_image'        => __( 'Campaign Image', 'ligatura-manuscripti-illuminati' ),
			'set_featured_image'    => __( 'Set campaign image', 'ligatura-manuscripti-illuminati' ),
			'remove_featured_image' => __( 'Remove campaign image', 'ligatura-manuscripti-illuminati' ),
			'use_featured_image'    => __( 'Use as campaign image', 'ligatura-manuscripti-illuminati' ),
		);

		if ( 'ligatura_diary' === $post_type ) {
			$labels['not_found']          = __( 'No journals found.', 'ligatura-manuscripti-illuminati' );
			$labels['not_found_in_trash'] = __( 'No journals found in Trash.', 'ligatura-manuscripti-illuminati' );
		}

		register_post_type(
			$post_type,
			array(
				'labels'             => $labels,
				'description'        => $definition['description'],
				'public'             => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'has_archive'        => false,
				'menu_icon'          => $definition['menu_icon'],
				'supports'           => $definition['supports'],
				'taxonomies'         => $definition['taxonomies'] ?? array(),
				'rewrite'            => false,
				'query_var'          => true,
				'capability_type'    => $definition['capability'],
				'capabilities'       => capability_map( $definition['capability'][0], $definition['capability'][1] ),
				'map_meta_cap'       => true,
				'delete_with_user'   => false,
				'exclude_from_search'=> false,
			)
		);
	}
}

/**
 * Register clean Page-owned root routes and nested CPT single routes.
 */
function register_rewrite_rules(): void {
	add_rewrite_rule( '^saga-topic/([^/]+)/?$', 'index.php?ligatura_saga_topic=$matches[1]', 'top' );

	foreach ( get_permalink_roots() as $post_type => $root ) {
		add_rewrite_rule( '^' . preg_quote( $root, '/' ) . '/?$', 'index.php?pagename=' . $root, 'top' );

		if ( 'ligatura_wiki' === $post_type ) {
			$saga_topic_slugs = get_terms(
				array(
					'taxonomy'   => 'ligatura_saga_topic',
					'hide_empty' => false,
					'fields'     => 'slugs',
				)
			);

			if ( ! is_wp_error( $saga_topic_slugs ) ) {
				foreach ( $saga_topic_slugs as $saga_topic_slug ) {
					$saga_topic_slug = sanitize_title( (string) $saga_topic_slug );

					if ( '' !== $saga_topic_slug ) {
						add_rewrite_rule( '^wiki/topics/' . preg_quote( $saga_topic_slug, '/' ) . '/?$', 'index.php?ligatura_saga_topic=' . $saga_topic_slug . '&post_type=ligatura_wiki', 'top' );
					}
				}
			}

			$entry_type_slugs = get_terms(
				array(
					'taxonomy'   => 'ligatura_entry_type',
					'hide_empty' => false,
					'fields'     => 'slugs',
				)
			);

			if ( ! is_wp_error( $entry_type_slugs ) ) {
				foreach ( $entry_type_slugs as $entry_type_slug ) {
					$entry_type_slug = sanitize_title( (string) $entry_type_slug );

					if ( '' !== $entry_type_slug ) {
						add_rewrite_rule( '^wiki/' . preg_quote( $entry_type_slug, '/' ) . '/?$', 'index.php?ligatura_entry_type=' . $entry_type_slug, 'top' );
					}
				}
			}

			add_rewrite_rule( '^wiki/([^/]+)/?$', 'index.php?ligatura_wiki=$matches[1]', 'top' );
		} else {
			add_rewrite_rule( '^' . preg_quote( $root, '/' ) . '/([^/]+)/?$', 'index.php?' . $post_type . '=$matches[1]', 'top' );
		}
	}

	// Added last at the top so it precedes every generic /journals/{slug}/ rule.
	add_rewrite_rule( '^journals/persona/([^/]+)/?$', 'index.php?pagename=journals&ligatura_journal_persona=$matches[1]', 'top' );

	// Reserved collection namespaces must precede /covenant-records/{record-slug}/.
	add_rewrite_rule( '^covenant-records/type/([^/]+)/?$', 'index.php?pagename=covenant-records&ligatura_covenant_entry_type=$matches[1]', 'top' );
	add_rewrite_rule( '^covenant-records/topics/([^/]+)/?$', 'index.php?pagename=covenant-records&ligatura_covenant_topic=$matches[1]', 'top' );

	add_rewrite_rule( '^(.+?)/?$', 'index.php?pagename=$matches[1]', 'bottom' );
}

/**
 * Register public query variables used by first-class collection routes.
 *
 * @param string[] $query_vars Existing public query variables.
 * @return string[]
 */
function register_query_vars( array $query_vars ): array {
	$query_vars[] = 'ligatura_journal_persona';
	$query_vars[] = 'ligatura_covenant_entry_type';
	$query_vars[] = 'ligatura_covenant_topic';

	return array_unique( $query_vars );
}

/**
 * Remove this plugin's generated rules and register them from current state.
 */
function rebuild_campaign_rewrite_rules(): void {
	global $wp_rewrite;

	if ( ! $wp_rewrite instanceof \WP_Rewrite ) {
		return;
	}

	if ( is_array( $wp_rewrite->extra_rules_top ) ) {
		foreach ( array_keys( $wp_rewrite->extra_rules_top ) as $regex ) {
			foreach ( get_permalink_roots() as $root ) {
				$root_regex = '^' . preg_quote( $root, '/' );

				if ( $root_regex . '/?$' === $regex || str_starts_with( $regex, $root_regex . '/' ) ) {
					unset( $wp_rewrite->extra_rules_top[ $regex ] );
					break;
				}
			}
		}
	}

	if ( isset( $wp_rewrite->extra_rules['^(.+?)/?$'] ) && 'index.php?pagename=$matches[1]' === $wp_rewrite->extra_rules['^(.+?)/?$'] ) {
		unset( $wp_rewrite->extra_rules['^(.+?)/?$'] );
	}

	register_rewrite_rules();
}

/**
 * Rebuild and flush routes when a term-dependent campaign route changes.
 */
function flush_term_dependent_rewrite_rules(): void {
	if ( defined( 'LIGATURA_MANUSCRIPTI_ILLUMINATI_ACTIVATING' ) && LIGATURA_MANUSCRIPTI_ILLUMINATI_ACTIVATING ) {
		return;
	}

	rebuild_campaign_rewrite_rules();
	flush_rewrite_rules( false );
	mark_rewrite_rules_flushed();
}

/**
 * Generate canonical Speculum collection links for campaign taxonomies.
 *
 * @param string   $url      Existing term URL.
 * @param \WP_Term $term     Term object.
 * @param string   $taxonomy Taxonomy name.
 */
function entry_type_term_link( string $url, \WP_Term $term, string $taxonomy ): string {
	if ( 'ligatura_entry_type' === $taxonomy ) {
		return home_url( user_trailingslashit( 'wiki/' . $term->slug ) );
	}

	if ( 'ligatura_saga_topic' === $taxonomy ) {
		$query_post_type = get_query_var( 'post_type' );
		$is_wiki_query   = 'ligatura_wiki' === $query_post_type || ( is_array( $query_post_type ) && in_array( 'ligatura_wiki', $query_post_type, true ) );

		if ( is_singular( 'ligatura_wiki' ) || ( is_tax( 'ligatura_saga_topic' ) && $is_wiki_query ) ) {
			return home_url( user_trailingslashit( 'wiki/topics/' . $term->slug ) );
		}

		return home_url( user_trailingslashit( 'saga-topic/' . $term->slug ) );
	}

	return $url;
}

/**
 * Generate canonical pretty links for campaign CPTs.
 *
 * @param string   $post_link Existing permalink.
 * @param \WP_Post $post      Post object.
 * @param bool     $leavename Whether to leave the post-name token in place.
 * @param bool     $sample    Whether this is a sample permalink.
 */
function campaign_post_type_link( string $post_link, \WP_Post $post, bool $leavename, bool $sample ): string {
	unset( $sample );

	$root = get_permalink_root_for_post_type( $post->post_type );

	if ( '' === $root ) {
		return $post_link;
	}

	$slug = $leavename ? '%' . $post->post_type . '%' : $post->post_name;

	if ( '' === $slug ) {
		$slug = sanitize_title( $post->post_title );
	}

	if ( '' === $slug ) {
		$slug = (string) $post->ID;
	}

	return home_url( user_trailingslashit( $root . '/' . $slug ) );
}

/**
 * Generate clean canonical links for editable Pages while preserving hierarchy.
 */
function campaign_page_link( string $link, int $post_id ): string {
	$post = get_post( $post_id );

	if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type ) {
		return $link;
	}

	if ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post_id ) {
		return home_url( '/' );
	}

	$uri = get_page_uri( $post );

	if ( '' === $uri ) {
		return home_url( '/' );
	}

	return home_url( user_trailingslashit( $uri ) );
}

/**
 * Flush rewrite rules once when the campaign route map changes.
 */
function maybe_flush_rewrite_rules(): void {
	if ( get_option( REWRITE_OPTION ) === REWRITE_RULE_VERSION ) {
		return;
	}

	rebuild_campaign_rewrite_rules();
	flush_rewrite_rules( false );
	mark_rewrite_rules_flushed();
}

/**
 * Mark the current rewrite rule version as flushed.
 */
function mark_rewrite_rules_flushed(): void {
	update_option( REWRITE_OPTION, REWRITE_RULE_VERSION, false );
}
