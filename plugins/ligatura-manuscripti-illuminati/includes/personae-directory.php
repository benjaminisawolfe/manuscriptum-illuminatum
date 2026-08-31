<?php
/**
 * Searchable Personae directory grouped by Character Type by default.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\PersonaeDirectory;

use LigaturaManuscriptiIlluminati\PostTypes;
use LigaturaManuscriptiIlluminati\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the authenticated Personae-directory REST route.
 */
function register_rest_route(): void {
	\register_rest_route(
		'ligatura-manuscripti-illuminati/v1',
		'/personae',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_personae',
			'permission_callback' => static function (): bool {
				return ! Privacy\login_required() || is_user_logged_in();
			},
		)
	);
}

/**
 * Return filtered Personae results or the default grouped directory.
 */
function get_personae( \WP_REST_Request $request ): \WP_REST_Response {
	$filters = sanitize_filters( $request->get_params() );
	$active  = has_active_filters( $filters );
	$posts   = $active ? query_characters( $filters ) : array();
	$html    = $active ? render_search_results( $posts ) : render_grouped_directory();

	return new \WP_REST_Response(
		array(
			'html'    => $html,
			'total'   => $active ? count( $posts ) : count( readable_characters() ),
			'grouped' => ! $active,
		)
	);
}

/**
 * Return all readable Personae in deterministic title order.
 *
 * @return \WP_Post[]
 */
function readable_characters(): array {
	static $characters = null;

	if ( is_array( $characters ) ) {
		return $characters;
	}

	$posts = array_values(
		array_filter(
			( new \WP_Query(
				array(
					'post_type'           => 'ligatura_character',
					'post_status'         => array( 'publish', 'private' ),
					'perm'                => 'readable',
					'posts_per_page'      => -1,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				)
			) )->posts,
			static function ( $post ): bool {
				return $post instanceof \WP_Post && current_user_can( 'read_post', $post->ID );
			}
		)
	);

	sort_characters( $posts );
	$characters = $posts;

	return $characters;
}

/**
 * Sort Personae alphabetically while ignoring a leading standalone “The”.
 *
 * @param \WP_Post[] $posts Posts to sort in place.
 */
function sort_characters( array &$posts ): void {
	usort(
		$posts,
		static function ( \WP_Post $left, \WP_Post $right ): int {
			$left_title  = get_the_title( $left );
			$right_title = get_the_title( $right );
			$comparison  = strnatcasecmp( title_sort_key( $left_title ), title_sort_key( $right_title ) );

			if ( 0 === $comparison ) {
				$comparison = strnatcasecmp( $left_title, $right_title );
			}

			return 0 !== $comparison ? $comparison : $left->ID <=> $right->ID;
		}
	);
}

/**
 * Normalize a visible title for alphabetical sorting only.
 */
function title_sort_key( string $title ): string {
	$title = trim( wp_strip_all_tags( $title ) );

	return (string) preg_replace( '/^the\s+/iu', '', $title );
}

/**
 * Group readable Personae by the canonical Character Type taxonomy.
 *
 * @return array<int,array{term:\WP_Term,posts:\WP_Post[]}>
 */
function grouped_characters(): array {
	$groups = array();

	foreach ( readable_characters() as $post ) {
		$terms = get_the_terms( $post, 'ligatura_character_type' );

		if ( ! $terms || is_wp_error( $terms ) ) {
			continue;
		}

		foreach ( $terms as $term ) {
			if ( ! isset( $groups[ $term->term_id ] ) ) {
				$groups[ $term->term_id ] = array(
					'term'  => $term,
					'posts' => array(),
				);
			}

			$groups[ $term->term_id ]['posts'][] = $post;
		}
	}

	uasort(
		$groups,
		static function ( array $left, array $right ): int {
			$comparison = strnatcasecmp( $left['term']->name, $right['term']->name );

			return 0 !== $comparison ? $comparison : $left['term']->term_id <=> $right['term']->term_id;
		}
	);

	return $groups;
}

/**
 * Render the searchable Personae directory for the editable root Page.
 */
function render_directory(): string {
	$filters     = sanitize_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only bookmarkable filters.
	$active      = has_active_filters( $filters );
	$posts       = $active ? query_characters( $filters ) : array();
	$listing     = $active ? render_search_results( $posts ) : render_grouped_directory();
	$action_url  = PostTypes\get_directory_url( 'ligatura_character' );
	$types       = taxonomy_choices( 'ligatura_character_type' );
	$houses      = taxonomy_choices( 'ligatura_hermetic_house' );
	$topics      = taxonomy_choices( 'ligatura_saga_topic' );
	$traditions  = meta_choices( 'ligatura_tradition' );
	$players     = meta_choices( 'ligatura_player' );
	$origins     = meta_choices( 'ligatura_origin' );
	$occupations = meta_choices( 'ligatura_occupation' );

	ob_start();
	?>
	<section
		id="personae-directory"
		class="manuscriptum-illuminatum-content-index manuscriptum-illuminatum-personae-directory"
		aria-label="<?php esc_attr_e( 'Personae directory', 'ligatura-manuscripti-illuminati' ); ?>"
		data-endpoint="<?php echo esc_url( rest_url( 'ligatura-manuscripti-illuminati/v1/personae' ) ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
		data-grouped="<?php echo $active ? 'false' : 'true'; ?>"
	>
		<form class="manuscriptum-illuminatum-journal-search manuscriptum-illuminatum-personae-search" method="get" action="<?php echo esc_url( $action_url ); ?>" data-personae-search-form>
			<div class="manuscriptum-illuminatum-journal-search__primary">
				<label for="personae-search-query"><?php esc_html_e( 'Search Personae', 'ligatura-manuscripti-illuminati' ); ?></label>
				<div class="manuscriptum-illuminatum-journal-search__query">
					<input
						type="search"
						id="personae-search-query"
						name="personae_q"
						value="<?php echo esc_attr( $filters['personae_q'] ); ?>"
						placeholder="<?php esc_attr_e( 'Search Persona name or text…', 'ligatura-manuscripti-illuminati' ); ?>"
					/>
					<button type="submit"><?php esc_html_e( 'Search', 'ligatura-manuscripti-illuminati' ); ?></button>
				</div>
				<button
					type="button"
					class="manuscriptum-illuminatum-journal-search__advanced-toggle"
					aria-expanded="false"
					aria-controls="personae-advanced-search"
					data-personae-advanced-toggle
				>
					<?php esc_html_e( 'Advanced Search', 'ligatura-manuscripti-illuminati' ); ?>
				</button>
			</div>

			<div id="personae-advanced-search" class="manuscriptum-illuminatum-journal-search__advanced" data-personae-advanced hidden>
				<?php
				echo render_select_field( 'personae-character-type', 'personae_type', __( 'Character Type', 'ligatura-manuscripti-illuminati' ), __( 'All character types', 'ligatura-manuscripti-illuminati' ), $types, $filters['personae_type'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes all output.
				echo render_select_field( 'personae-house', 'personae_house', __( 'House', 'ligatura-manuscripti-illuminati' ), __( 'All houses', 'ligatura-manuscripti-illuminati' ), $houses, $filters['personae_house'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes all output.
				echo render_select_field( 'personae-tradition', 'personae_tradition', __( 'Tradition', 'ligatura-manuscripti-illuminati' ), __( 'All traditions', 'ligatura-manuscripti-illuminati' ), $traditions, $filters['personae_tradition'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes all output.
				echo render_select_field( 'personae-player', 'personae_player', __( 'Player', 'ligatura-manuscripti-illuminati' ), __( 'All players', 'ligatura-manuscripti-illuminati' ), $players, $filters['personae_player'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes all output.
				echo render_select_field( 'personae-origin', 'personae_origin', __( 'Origin', 'ligatura-manuscripti-illuminati' ), __( 'All origins', 'ligatura-manuscripti-illuminati' ), $origins, $filters['personae_origin'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes all output.
				echo render_select_field( 'personae-occupation', 'personae_occupation', __( 'Occupation', 'ligatura-manuscripti-illuminati' ), __( 'All occupations', 'ligatura-manuscripti-illuminati' ), $occupations, $filters['personae_occupation'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes all output.
				echo render_select_field( 'personae-topic', 'personae_topic', __( 'Saga Topic', 'ligatura-manuscripti-illuminati' ), __( 'All Saga Topics', 'ligatura-manuscripti-illuminati' ), $topics, $filters['personae_topic'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes all output.
				?>

				<div class="manuscriptum-illuminatum-journal-search__actions">
					<button type="submit"><?php esc_html_e( 'Apply Filters', 'ligatura-manuscripti-illuminati' ); ?></button>
					<a href="<?php echo esc_url( $action_url ); ?>" data-personae-clear><?php esc_html_e( 'Clear filters', 'ligatura-manuscripti-illuminati' ); ?></a>
				</div>
			</div>
		</form>

		<p class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true" data-personae-status></p>
		<p class="manuscriptum-illuminatum-personae-directory__error" role="alert" data-personae-error hidden></p>

		<div class="manuscriptum-illuminatum-personae-directory__results" data-personae-results aria-busy="false">
			<?php echo $listing; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Render helpers escape all dynamic output. ?>
		</div>

		<p class="manuscriptum-illuminatum-personae-directory__empty" data-personae-empty<?php echo $listing ? ' hidden' : ''; ?>><?php esc_html_e( 'No Personae match these search criteria.', 'ligatura-manuscripti-illuminati' ); ?></p>
	</section>
	<?php

	return (string) ob_get_clean();
}

/**
 * Render one advanced-search select from validated stored choices.
 *
 * @param array<string,string> $choices Value => label choices.
 */
function render_select_field( string $id, string $name, string $label, string $empty_label, array $choices, string $selected_value ): string {
	$html  = '<div class="manuscriptum-illuminatum-journal-search__field">';
	$html .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
	$html .= '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
	$html .= '<option value="">' . esc_html( $empty_label ) . '</option>';

	foreach ( $choices as $value => $choice_label ) {
		$html .= '<option value="' . esc_attr( $value ) . '"' . selected( $selected_value, $value, false ) . '>' . esc_html( $choice_label ) . '</option>';
	}

	return $html . '</select></div>';
}

/**
 * Sanitize and validate supported Personae filters.
 *
 * @param array<string,mixed> $source Raw request values.
 * @return array<string,string>
 */
function sanitize_filters( array $source ): array {
	$filters = array(
		'personae_q'          => sanitize_scalar_text( $source['personae_q'] ?? '' ),
		'personae_type'       => sanitize_title( sanitize_scalar_text( $source['personae_type'] ?? '' ) ),
		'personae_house'      => sanitize_title( sanitize_scalar_text( $source['personae_house'] ?? '' ) ),
		'personae_tradition'  => sanitize_scalar_text( $source['personae_tradition'] ?? '' ),
		'personae_player'     => sanitize_scalar_text( $source['personae_player'] ?? '' ),
		'personae_origin'     => sanitize_scalar_text( $source['personae_origin'] ?? '' ),
		'personae_occupation' => sanitize_scalar_text( $source['personae_occupation'] ?? '' ),
		'personae_topic'      => sanitize_title( sanitize_scalar_text( $source['personae_topic'] ?? '' ) ),
	);

	$taxonomy_filters = array(
		'personae_type'  => taxonomy_choices( 'ligatura_character_type' ),
		'personae_house' => taxonomy_choices( 'ligatura_hermetic_house' ),
		'personae_topic' => taxonomy_choices( 'ligatura_saga_topic' ),
	);

	foreach ( $taxonomy_filters as $key => $choices ) {
		if ( $filters[ $key ] && ! isset( $choices[ $filters[ $key ] ] ) ) {
			$filters[ $key ] = '';
		}
	}

	$meta_filters = array(
		'personae_tradition'  => 'ligatura_tradition',
		'personae_player'     => 'ligatura_player',
		'personae_origin'     => 'ligatura_origin',
		'personae_occupation' => 'ligatura_occupation',
	);

	foreach ( $meta_filters as $key => $meta_key ) {
		if ( $filters[ $key ] && ! isset( meta_choices( $meta_key )[ $filters[ $key ] ] ) ) {
			$filters[ $key ] = '';
		}
	}

	return $filters;
}

/**
 * Sanitize a scalar text value.
 */
function sanitize_scalar_text( mixed $value ): string {
	return is_scalar( $value ) ? sanitize_text_field( wp_unslash( (string) $value ) ) : '';
}

/**
 * Determine whether any Personae search/filter is active.
 *
 * @param array<string,string> $filters Sanitized filters.
 */
function has_active_filters( array $filters ): bool {
	return count( array_filter( $filters, static fn( string $value ): bool => '' !== $value ) ) > 0;
}

/**
 * Query readable Personae using only fixed, validated fields and taxonomies.
 *
 * @param array<string,string> $filters Sanitized filters.
 * @return \WP_Post[]
 */
function query_characters( array $filters ): array {
	$args      = array(
		'post_type'           => 'ligatura_character',
		'post_status'         => array( 'publish', 'private' ),
		'perm'                => 'readable',
		'posts_per_page'      => -1,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	$tax_query = array( 'relation' => 'AND' );

	foreach (
		array(
			'personae_type'  => 'ligatura_character_type',
			'personae_house' => 'ligatura_hermetic_house',
			'personae_topic' => 'ligatura_saga_topic',
		) as $filter_key => $taxonomy
	) {
		if ( $filters[ $filter_key ] ) {
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $filters[ $filter_key ],
			);
		}
	}

	if ( 1 < count( $tax_query ) ) {
		$args['tax_query'] = $tax_query;
	}

	$meta_query = array( 'relation' => 'AND' );

	foreach (
		array(
			'personae_tradition'  => 'ligatura_tradition',
			'personae_player'     => 'ligatura_player',
			'personae_origin'     => 'ligatura_origin',
			'personae_occupation' => 'ligatura_occupation',
		) as $filter_key => $meta_key
	) {
		if ( $filters[ $filter_key ] ) {
			$meta_query[] = array(
				'key'     => $meta_key,
				'value'   => $filters[ $filter_key ],
				'compare' => '=',
			);
		}
	}

	if ( 1 < count( $meta_query ) ) {
		$args['meta_query'] = $meta_query;
	}

	$posts = array_values(
		array_filter(
			( new \WP_Query( $args ) )->posts,
			static function ( $post ) use ( $filters ): bool {
				return $post instanceof \WP_Post
					&& current_user_can( 'read_post', $post->ID )
					&& personae_matches_search( $post, $filters['personae_q'] );
			}
		)
	);

	sort_characters( $posts );

	return $posts;
}

/**
 * Match simple search terms against public Persona text only.
 */
function personae_matches_search( \WP_Post $post, string $query ): bool {
	if ( '' === $query ) {
		return true;
	}

	$haystack = implode(
		' ',
		array(
			get_the_title( $post ),
			wp_strip_all_tags( $post->post_content ),
			$post->post_excerpt,
			(string) get_post_meta( $post->ID, 'ligatura_brief_description', true ),
		)
	);
	$terms    = preg_split( '/\s+/u', trim( $query ) );

	if ( ! is_array( $terms ) ) {
		return false;
	}

	foreach ( array_filter( $terms ) as $term ) {
		$matched = function_exists( 'mb_stripos' )
			? false !== mb_stripos( $haystack, $term )
			: false !== stripos( $haystack, $term );

		if ( ! $matched ) {
			return false;
		}
	}

	return true;
}

/**
 * Return non-empty taxonomy choices used by readable Personae.
 *
 * @return array<string,string>
 */
function taxonomy_choices( string $taxonomy ): array {
	if ( ! in_array( $taxonomy, array( 'ligatura_character_type', 'ligatura_hermetic_house', 'ligatura_saga_topic' ), true ) ) {
		return array();
	}

	$object_ids = wp_list_pluck( readable_characters(), 'ID' );

	if ( empty( $object_ids ) ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'object_ids' => array_map( 'absint', $object_ids ),
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$choices = array();

	foreach ( $terms as $term ) {
		$choices[ $term->slug ] = $term->name;
	}

	return $choices;
}

/**
 * Return distinct stored values for one approved free-text Persona field.
 *
 * @return array<string,string>
 */
function meta_choices( string $meta_key ): array {
	if ( ! in_array( $meta_key, array( 'ligatura_tradition', 'ligatura_player', 'ligatura_origin', 'ligatura_occupation' ), true ) ) {
		return array();
	}

	$values = array();

	foreach ( readable_characters() as $post ) {
		$value = trim( sanitize_text_field( (string) get_post_meta( $post->ID, $meta_key, true ) ) );

		if ( '' !== $value ) {
			$values[ $value ] = $value;
		}
	}

	natcasesort( $values );

	return $values;
}

/**
 * Render only the default Character Type groups.
 */
function render_grouped_directory(): string {
	$html = '';

	foreach ( grouped_characters() as $group ) {
		if ( empty( $group['posts'] ) ) {
			continue;
		}

		$term       = $group['term'];
		$heading_id = 'personae-character-type-' . absint( $term->term_id );
		$html      .= '<section class="manuscriptum-illuminatum-directory-group manuscriptum-illuminatum-personae-group" data-character-type="' . esc_attr( $term->slug ) . '" aria-labelledby="' . esc_attr( $heading_id ) . '">';
		$html      .= '<h2 id="' . esc_attr( $heading_id ) . '">' . esc_html( $term->name ) . '</h2>';
		$html      .= render_persona_teasers( $group['posts'] );
		$html      .= '</section>';
	}

	return $html;
}

/**
 * Render a flat Personae result set through the shared teaser component.
 *
 * @param \WP_Post[] $posts Readable matching Personae.
 */
function render_search_results( array $posts ): string {
	if ( empty( $posts ) ) {
		return '';
	}

	$html  = '<section class="manuscriptum-illuminatum-personae-search-results" aria-label="' . esc_attr__( 'Personae search results', 'ligatura-manuscripti-illuminati' ) . '">';
	$html .= '<h2 class="screen-reader-text">' . esc_html__( 'Search results', 'ligatura-manuscripti-illuminati' ) . '</h2>';
	$html .= render_persona_teasers( $posts );

	return $html . '</section>';
}

/**
 * Render theme-provided Persona teasers, with a linked-list fallback.
 *
 * @param \WP_Post[] $posts Readable characters.
 */
function render_persona_teasers( array $posts ): string {
	if ( empty( $posts ) ) {
		return '';
	}

	if ( ! function_exists( 'paginae_manuscripti_illuminati_render_persona_teaser' ) ) {
		return render_persona_list( $posts );
	}

	$html = '<div class="manuscriptum-illuminatum-directory-teaser-grid manuscriptum-illuminatum-personae-teaser-grid">';

	foreach ( $posts as $post ) {
		if ( ! $post instanceof \WP_Post || ! current_user_can( 'read_post', $post->ID ) ) {
			continue;
		}

		$html .= paginae_manuscripti_illuminati_render_persona_teaser( $post->ID );
	}

	return $html . '</div>';
}

/**
 * Render a simple accessible fallback when no compatible theme is active.
 *
 * @param \WP_Post[] $posts Readable characters.
 */
function render_persona_list( array $posts ): string {
	$html = '<ul class="manuscriptum-illuminatum-personae-entry-list">';

	foreach ( $posts as $post ) {
		if ( ! $post instanceof \WP_Post || ! current_user_can( 'read_post', $post->ID ) ) {
			continue;
		}

		$html .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></li>';
	}

	return $html . '</ul>';
}
