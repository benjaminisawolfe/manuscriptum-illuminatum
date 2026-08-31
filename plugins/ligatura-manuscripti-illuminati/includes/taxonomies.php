<?php
/**
 * Taxonomy registration.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\Taxonomies;

use LigaturaManuscriptiIlluminati\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taxonomy capability map.
 *
 * @return array<string,string>
 */
function taxonomy_capabilities(): array {
	return array(
		'manage_terms' => 'ligatura_manage_campaign_terms',
		'edit_terms'   => 'ligatura_manage_campaign_terms',
		'delete_terms' => 'ligatura_manage_campaign_terms',
		'assign_terms' => 'ligatura_assign_campaign_terms',
	);
}

/**
 * Taxonomy definitions.
 *
 * @return array<string,array<string,mixed>>
 */
function get_definitions(): array {
	return array(
		'ligatura_entry_type'     => array(
			'singular'   => __( 'Entry Type', 'ligatura-manuscripti-illuminati' ),
			'plural'     => __( 'Entry Types', 'ligatura-manuscripti-illuminati' ),
			'post_types' => array( 'ligatura_wiki', 'ligatura_covenant' ),
			'slug'       => 'entry-type',
			'terms'      => array(
				'Person',
				'Place',
				'Covenant',
				'Hermetic House',
				'Noble House',
				'Vis Source',
				'Regio',
				'Artifact',
				'Event',
				'Faction',
				'Mystery',
				'Session Note',
				'Rule Note',
			),
		),
		'ligatura_character_type' => array(
			'singular'   => __( 'Character Type', 'ligatura-manuscripti-illuminati' ),
			'plural'     => __( 'Character Types', 'ligatura-manuscripti-illuminati' ),
			'post_types' => array( 'ligatura_character' ),
			'slug'       => 'character-type',
			'terms'      => array(
				'Magus',
				'Companion',
				'Grog',
				'Covenfolk',
				'NPC',
				'Creature',
				'Spirit',
				'Faerie',
				'Demon',
				'Angel',
			),
		),
		'ligatura_hermetic_house' => array(
			'singular'   => __( 'Hermetic House', 'ligatura-manuscripti-illuminati' ),
			'plural'     => __( 'Hermetic Houses', 'ligatura-manuscripti-illuminati' ),
			'post_types' => array( 'ligatura_character', 'ligatura_wiki' ),
			'slug'       => 'hermetic-house',
			'terms'      => array(
				'Bjornaer',
				'Bonisagus',
				'Criamon',
				'Ex Miscellanea',
				'Flambeau',
				'Guernicus',
				'Jerbiton',
				'Mercere',
				'Merinita',
				'Tremere',
				'Tytalus',
				'Verditius',
			),
		),
		'ligatura_saga_topic'     => array(
			'singular'   => __( 'Saga Topic', 'ligatura-manuscripti-illuminati' ),
			'plural'     => __( 'Saga Topics', 'ligatura-manuscripti-illuminati' ),
			'post_types' => PostTypes\get_post_type_names(),
			'slug'       => 'saga-topic',
			'terms'      => array(
				'Quiet Bell',
				'Andely',
				'Normandy',
				'Order of Hermes',
				'Tribunal',
				'Richard the Lionheart',
				'Covenant Affairs',
				'Vis',
				'Politics',
				'Faerie',
				'Church',
				'Mundanes',
				'Secrets',
			),
		),
	);
}

/**
 * Register taxonomies for campaign post types.
 */
function register_taxonomies(): void {
	foreach ( get_definitions() as $taxonomy => $definition ) {
		register_taxonomy(
			$taxonomy,
			$definition['post_types'],
			array(
				'labels'            => array(
					'name'              => $definition['plural'],
					'singular_name'     => $definition['singular'],
					'search_items'      => sprintf(
						/* translators: %s: taxonomy plural label. */
						__( 'Search %s', 'ligatura-manuscripti-illuminati' ),
						$definition['plural']
					),
					'all_items'         => sprintf(
						/* translators: %s: taxonomy plural label. */
						__( 'All %s', 'ligatura-manuscripti-illuminati' ),
						$definition['plural']
					),
					'edit_item'         => sprintf(
						/* translators: %s: taxonomy singular label. */
						__( 'Edit %s', 'ligatura-manuscripti-illuminati' ),
						$definition['singular']
					),
					'update_item'       => sprintf(
						/* translators: %s: taxonomy singular label. */
						__( 'Update %s', 'ligatura-manuscripti-illuminati' ),
						$definition['singular']
					),
					'add_new_item'      => sprintf(
						/* translators: %s: taxonomy singular label. */
						__( 'Add New %s', 'ligatura-manuscripti-illuminati' ),
						$definition['singular']
					),
					'new_item_name'     => sprintf(
						/* translators: %s: taxonomy singular label. */
						__( 'New %s Name', 'ligatura-manuscripti-illuminati' ),
						$definition['singular']
					),
					'not_found'         => __( 'No terms found.', 'ligatura-manuscripti-illuminati' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => $definition['slug'] ),
				'capabilities'      => taxonomy_capabilities(),
			)
		);
	}
}

/**
 * Insert default taxonomy terms on activation.
 */
function seed_default_terms(): void {
	foreach ( get_definitions() as $taxonomy => $definition ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		foreach ( $definition['terms'] as $term_name ) {
			if ( ! term_exists( $term_name, $taxonomy ) ) {
				wp_insert_term( $term_name, $taxonomy );
			}
		}
	}
}
