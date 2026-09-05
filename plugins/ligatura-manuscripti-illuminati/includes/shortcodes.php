<?php
/**
 * Campaign shortcodes.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\Shortcodes;

use LigaturaManuscriptiIlluminati\JournalDirectory;
use LigaturaManuscriptiIlluminati\CovenantDirectory;
use LigaturaManuscriptiIlluminati\PersonaeDirectory;
use LigaturaManuscriptiIlluminati\PostTypes;
use LigaturaManuscriptiIlluminati\SpeculumDirectory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register public shortcodes.
 */
function register_shortcodes(): void {
	add_shortcode( 'ligatura_character', __NAMESPACE__ . '\\character_shortcode' );
	add_shortcode( 'ligatura_character_list', __NAMESPACE__ . '\\character_list_shortcode' );
	add_shortcode( 'ligatura_wiki_list', __NAMESPACE__ . '\\wiki_list_shortcode' );
	add_shortcode( 'ligatura_diary_list', __NAMESPACE__ . '\\diary_list_shortcode' );
	add_shortcode( 'ligatura_covenant_list', __NAMESPACE__ . '\\covenant_list_shortcode' );
	add_shortcode( 'ligatura_content_index', __NAMESPACE__ . '\\content_index_shortcode' );
	add_shortcode( 'ligatura_related_entries', __NAMESPACE__ . '\\related_entries_shortcode' );
}

/**
 * Render a single character card.
 *
 * @param array<string,string> $atts Shortcode attributes.
 */
function character_shortcode( array $atts ): string {
	$atts = shortcode_atts(
		array( 'id' => 0 ),
		$atts,
		'ligatura_character'
	);

	$post_id = absint( $atts['id'] );

	if ( ! $post_id || 'ligatura_character' !== get_post_type( $post_id ) || ! \LigaturaManuscriptiIlluminati\Privacy\can_read_post( $post_id ) ) {
		return '';
	}

	return function_exists( 'ligatura_render_character_card' ) ? ligatura_render_character_card( $post_id ) : '';
}

/**
 * Render character list.
 *
 * @param array<string,string> $atts Shortcode attributes.
 */
function character_list_shortcode( array $atts ): string {
	$atts = shortcode_atts(
		array( 'type' => '' ),
		$atts,
		'ligatura_character_list'
	);

	$args = array(
		'post_type'      => 'ligatura_character',
		'post_status'    => 'publish',
		'posts_per_page' => 24,
	);

	if ( '' !== $atts['type'] ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'ligatura_character_type',
				'field'    => 'name',
				'terms'    => sanitize_text_field( $atts['type'] ),
			),
		);
	}

	return render_card_query( $args, 'ligatura_render_character_card' );
}

/**
 * Render wiki list.
 *
 * @param array<string,string> $atts Shortcode attributes.
 */
function wiki_list_shortcode( array $atts ): string {
	$atts = shortcode_atts(
		array( 'type' => '' ),
		$atts,
		'ligatura_wiki_list'
	);

	$args = array(
		'post_type'      => 'ligatura_wiki',
		'post_status'    => 'publish',
		'posts_per_page' => 24,
	);

	if ( '' !== $atts['type'] ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'ligatura_entry_type',
				'field'    => 'name',
				'terms'    => sanitize_text_field( $atts['type'] ),
			),
		);
	}

	return render_card_query( $args, 'ligatura_render_wiki_card' );
}

/**
 * Render diary list.
 *
 * @param array<string,string> $atts Shortcode attributes.
 */
function diary_list_shortcode( array $atts ): string {
	$atts = shortcode_atts(
		array( 'user' => '' ),
		$atts,
		'ligatura_diary_list'
	);

	$args = array(
		'post_type'                   => 'ligatura_diary',
		'post_status'                 => 'publish',
		'posts_per_page'              => 12,
		'ligatura_journal_saga_date_order' => 'DESC',
		'ligatura_journal_secondary_order' => 'DESC',
		'orderby'                     => 'none',
	);

	if ( 'current' === $atts['user'] && is_user_logged_in() ) {
		$args['author'] = get_current_user_id();
	}

	return render_card_query( $args, 'ligatura_render_diary_card' );
}

/**
 * Render covenant record list.
 *
 * @param array<string,string> $atts Shortcode attributes.
 */
function covenant_list_shortcode( array $atts ): string {
	unset( $atts );

	return CovenantDirectory\render_directory();
}

/**
 * Render a first-class campaign content index.
 *
 * @param array<string,string> $atts Shortcode attributes.
 */
function content_index_shortcode( array $atts ): string {
	$atts = shortcode_atts(
		array( 'type' => '' ),
		$atts,
		'ligatura_content_index'
	);
	$post_type = normalize_content_index_type( (string) $atts['type'] );

	if ( '' === $post_type ) {
		return '';
	}

	return render_content_index_for_post_type( $post_type );
}

/**
 * Append the matching content index to editable root Pages.
 */
function append_directory_listing_to_root_page( string $content ): string {
	// wp_trim_excerpt() applies the_content while the root Page is still global.
	// An entry without a manual excerpt must not recursively render its directory.
	if ( doing_filter( 'get_the_excerpt' ) || is_admin() || is_feed() || ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $content;
	}

	if ( has_shortcode( $content, 'ligatura_content_index' ) || has_shortcode( $content, 'ligatura_covenant_list' ) ) {
		return $content;
	}

	$page = get_post();

	if ( ! $page instanceof \WP_Post ) {
		return $content;
	}

	$post_type = PostTypes\get_post_type_for_permalink_root( $page->post_name );

	if ( '' === $post_type ) {
		return $content;
	}

	if ( 'ligatura_diary' === $post_type ) {
		return $content . JournalDirectory\render_directory();
	}

	if ( 'ligatura_wiki' === $post_type ) {
		return $content . SpeculumDirectory\render_directory();
	}

	if ( 'ligatura_covenant' === $post_type ) {
		return $content . CovenantDirectory\render_directory();
	}

	return $content . render_content_index_for_post_type( $post_type );
}

/**
 * Render related entries.
 *
 * @param array<string,string> $atts Shortcode attributes.
 */
function related_entries_shortcode( array $atts ): string {
	$atts = shortcode_atts(
		array( 'id' => get_the_ID() ),
		$atts,
		'ligatura_related_entries'
	);

	$post_id = absint( $atts['id'] );

	if ( ! $post_id || ! function_exists( 'ligatura_get_related_entries' ) ) {
		return '';
	}

	$entries = ligatura_get_related_entries( $post_id );

	if ( empty( $entries ) ) {
		return '';
	}

	$output = '<ul class="manuscriptum-illuminatum-related-entries">';

	foreach ( $entries as $entry_id ) {
		if ( ! \LigaturaManuscriptiIlluminati\Privacy\can_read_post( $entry_id ) ) {
			continue;
		}

		$output .= '<li><a href="' . esc_url( get_permalink( $entry_id ) ) . '">' . esc_html( get_the_title( $entry_id ) ) . '</a></li>';
	}

	$output .= '</ul>';

	return $output;
}

/**
 * Normalize shortcode type aliases to campaign post types.
 */
function normalize_content_index_type( string $type ): string {
	$type  = sanitize_title( $type );
	$roots = PostTypes\get_permalink_roots();

	if ( isset( $roots[ $type ] ) ) {
		return $type;
	}

	$aliases = array(
		'wiki'             => 'ligatura_wiki',
		'speculum'         => 'ligatura_wiki',
		'characters'       => 'ligatura_character',
		'personae'         => 'ligatura_character',
		'journals'         => 'ligatura_diary',
		'commentarii'      => 'ligatura_diary',
		'covenant-records' => 'ligatura_covenant',
		'covenant'         => 'ligatura_covenant',
	);

	return $aliases[ $type ] ?? '';
}

/**
 * Render an alphabetical index for a campaign post type.
 */
function render_content_index_for_post_type( string $post_type ): string {
	if ( 'ligatura_character' === $post_type ) {
		return PersonaeDirectory\render_directory();
	}

	if ( 'ligatura_covenant' === $post_type ) {
		return CovenantDirectory\render_directory();
	}

	$renderers = array(
		'ligatura_wiki'      => 'ligatura_render_wiki_card',
		'ligatura_diary'     => 'ligatura_render_diary_card',
	);

	if ( ! isset( $renderers[ $post_type ] ) ) {
		return '';
	}

	$cards = render_card_query(
		array(
			'post_type'           => $post_type,
			'post_status'         => array( 'publish', 'private' ),
			'perm'                => 'readable',
			'posts_per_page'      => -1,
			'orderby'             => 'title',
			'order'               => 'ASC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		),
		$renderers[ $post_type ]
	);

	if ( '' === $cards ) {
		return '';
	}

	return '<section class="manuscriptum-illuminatum-content-index" aria-label="' . esc_attr__( 'Campaign content index', 'ligatura-manuscripti-illuminati' ) . '">' . $cards . '</section>';
}

/**
 * Render cards from a query.
 *
 * @param array<string,mixed> $args            WP_Query args.
 * @param string              $render_function Global render function.
 */
function render_card_query( array $args, string $render_function ): string {
	if ( ! function_exists( $render_function ) ) {
		return '';
	}

	$query = new \WP_Query( $args );

	if ( ! $query->have_posts() ) {
		return '';
	}

	$cards = '';

	while ( $query->have_posts() ) {
		$query->the_post();
		$cards .= call_user_func( $render_function, get_the_ID() );
	}

	wp_reset_postdata();

	if ( '' === $cards ) {
		return '';
	}

	return '<div class="manuscriptum-illuminatum-card-grid">' . $cards . '</div>';
}
