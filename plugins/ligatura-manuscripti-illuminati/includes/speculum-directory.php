<?php
/**
 * Searchable Speculum directory and Entry Type listings.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\SpeculumDirectory;

use LigaturaManuscriptiIlluminati\PostTypes;
use LigaturaManuscriptiIlluminati\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PREVIEW_LIMIT = 8;

/**
 * Register the authenticated Speculum-directory REST route.
 */
function register_rest_route(): void {
	\register_rest_route(
		'ligatura-manuscripti-illuminati/v1',
		'/speculum',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_speculum',
			'permission_callback' => static function (): bool {
				return ! Privacy\login_required() || is_user_logged_in();
			},
		)
	);
}

/**
 * Return filtered Speculum results or the default grouped directory.
 */
function get_speculum( \WP_REST_Request $request ): \WP_REST_Response {
	$filters = sanitize_filters( $request->get_params() );
	$active  = has_active_filters( $filters );
	$posts   = $active ? query_entries( $filters ) : array();
	$html    = $active ? render_search_results( $posts ) : render_grouped_directory();

	return new \WP_REST_Response(
		array(
			'html'    => $html,
			'total'   => $active ? count( $posts ) : count( readable_entry_ids() ),
			'grouped' => ! $active,
		)
	);
}

/**
 * Render the Speculum directory for the editable root Page.
 */
function render_directory(): string {
	$filters    = sanitize_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only bookmarkable filters.
	$active     = has_active_filters( $filters );
	$posts      = $active ? query_entries( $filters ) : array();
	$listing    = $active ? render_search_results( $posts ) : render_grouped_directory();
	$action_url = PostTypes\get_directory_url( 'ligatura_wiki' );
	$types      = entry_type_choices();

	ob_start();
	?>
	<section
		id="speculum-directory"
		class="manuscriptum-illuminatum-content-index manuscriptum-illuminatum-wiki-directory"
		aria-label="<?php esc_attr_e( 'Speculum directory', 'ligatura-manuscripti-illuminati' ); ?>"
		data-endpoint="<?php echo esc_url( rest_url( 'ligatura-manuscripti-illuminati/v1/speculum' ) ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
	>
		<form class="manuscriptum-illuminatum-journal-search manuscriptum-illuminatum-wiki-search" method="get" action="<?php echo esc_url( $action_url ); ?>" data-speculum-search-form>
			<div class="manuscriptum-illuminatum-journal-search__primary">
				<label for="speculum-search-query"><?php esc_html_e( 'Search Speculum', 'ligatura-manuscripti-illuminati' ); ?></label>
				<div class="manuscriptum-illuminatum-journal-search__query">
					<input
						type="search"
						id="speculum-search-query"
						name="speculum_q"
						value="<?php echo esc_attr( $filters['speculum_q'] ); ?>"
						placeholder="<?php esc_attr_e( 'Search Speculum title or text…', 'ligatura-manuscripti-illuminati' ); ?>"
					/>
					<button type="submit"><?php esc_html_e( 'Search', 'ligatura-manuscripti-illuminati' ); ?></button>
				</div>
				<button
					type="button"
					class="manuscriptum-illuminatum-journal-search__advanced-toggle"
					aria-expanded="false"
					aria-controls="speculum-advanced-search"
					data-speculum-advanced-toggle
				>
					<?php esc_html_e( 'Advanced Search', 'ligatura-manuscripti-illuminati' ); ?>
				</button>
			</div>

			<div id="speculum-advanced-search" class="manuscriptum-illuminatum-journal-search__advanced" data-speculum-advanced hidden>
				<div class="manuscriptum-illuminatum-journal-search__field">
					<label for="speculum-entry-type"><?php esc_html_e( 'Entry Type', 'ligatura-manuscripti-illuminati' ); ?></label>
					<select id="speculum-entry-type" name="speculum_entry_type">
						<option value=""><?php esc_html_e( 'All entry types', 'ligatura-manuscripti-illuminati' ); ?></option>
						<?php foreach ( $types as $slug => $name ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"<?php echo selected( $filters['speculum_entry_type'], $slug, false ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="manuscriptum-illuminatum-journal-search__actions">
					<button type="submit"><?php esc_html_e( 'Apply Filters', 'ligatura-manuscripti-illuminati' ); ?></button>
					<a href="<?php echo esc_url( $action_url ); ?>" data-speculum-clear><?php esc_html_e( 'Clear filters', 'ligatura-manuscripti-illuminati' ); ?></a>
				</div>
			</div>
		</form>

		<p class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true" data-speculum-status></p>
		<p class="manuscriptum-illuminatum-wiki-directory__error" role="alert" data-speculum-error hidden></p>

		<div class="manuscriptum-illuminatum-wiki-directory__results" data-speculum-results aria-busy="false">
			<?php echo $listing; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Render helpers escape all dynamic output. ?>
		</div>

		<p class="manuscriptum-illuminatum-wiki-directory__empty" data-speculum-empty<?php echo $listing ? ' hidden' : ''; ?>><?php esc_html_e( 'No Speculum entries match these search criteria.', 'ligatura-manuscripti-illuminati' ); ?></p>
	</section>
	<?php

	return (string) ob_get_clean();
}

/**
 * Sanitize supported directory filters.
 *
 * @param array<string,mixed> $source Raw request values.
 * @return array<string,string>
 */
function sanitize_filters( array $source ): array {
	$filters = array(
		'speculum_q'          => sanitize_scalar_text( $source['speculum_q'] ?? '' ),
		'speculum_entry_type' => sanitize_title( sanitize_scalar_text( $source['speculum_entry_type'] ?? '' ) ),
	);

	if ( $filters['speculum_entry_type'] && ! isset( entry_type_choices()[ $filters['speculum_entry_type'] ] ) ) {
		$filters['speculum_entry_type'] = '';
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
 * Determine whether any Speculum search/filter is active.
 *
 * @param array<string,string> $filters Sanitized filters.
 */
function has_active_filters( array $filters ): bool {
	return '' !== $filters['speculum_q'] || '' !== $filters['speculum_entry_type'];
}

/**
 * Query readable Speculum entries with fixed, validated filters.
 *
 * @param array<string,string> $filters Sanitized filters.
 * @return \WP_Post[]
 */
function query_entries( array $filters ): array {
	$args = array(
		'post_type'           => 'ligatura_wiki',
		'post_status'         => array( 'publish', 'private' ),
		'perm'                => 'readable',
		'posts_per_page'      => -1,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);

	if ( $filters['speculum_q'] ) {
		$args['s']       = $filters['speculum_q'];
		$args['orderby'] = array(
			'relevance' => 'DESC',
			'ID'        => 'ASC',
		);
	}

	if ( $filters['speculum_entry_type'] ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'ligatura_entry_type',
				'field'    => 'slug',
				'terms'    => $filters['speculum_entry_type'],
			),
		);
	}

	$posts = array_values(
		array_filter(
			( new \WP_Query( $args ) )->posts,
			static function ( $post ): bool {
				return $post instanceof \WP_Post && current_user_can( 'read_post', $post->ID );
			}
		)
	);

	if ( ! $filters['speculum_q'] ) {
		sort_entries( $posts );
	}

	return $posts;
}

/**
 * Return all readable Speculum entry IDs.
 *
 * @return int[]
 */
function readable_entry_ids(): array {
	$query = new \WP_Query(
		array(
			'post_type'           => 'ligatura_wiki',
			'post_status'         => array( 'publish', 'private' ),
			'perm'                => 'readable',
			'posts_per_page'      => -1,
			'fields'              => 'ids',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	return array_map( 'absint', $query->posts );
}

/**
 * Return non-empty Entry Type choices used by readable Speculum entries.
 *
 * @return array<string,string>
 */
function entry_type_choices(): array {
	$entry_ids = readable_entry_ids();

	if ( empty( $entry_ids ) ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'ligatura_entry_type',
			'hide_empty' => true,
			'object_ids' => $entry_ids,
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
 * Return readable entries assigned to one Entry Type.
 *
 * @return \WP_Post[]
 */
function entries_for_type( string $slug ): array {
	$slug = sanitize_title( $slug );

	if ( '' === $slug || ! get_term_by( 'slug', $slug, 'ligatura_entry_type' ) ) {
		return array();
	}

	return query_entries(
		array(
			'speculum_q'          => '',
			'speculum_entry_type' => $slug,
		)
	);
}

/**
 * Return readable entries assigned to one Saga Topic.
 *
 * @return \WP_Post[]
 */
function entries_for_topic( string $slug ): array {
	$slug = sanitize_title( $slug );

	if ( '' === $slug || ! get_term_by( 'slug', $slug, 'ligatura_saga_topic' ) ) {
		return array();
	}

	$posts = array_values(
		array_filter(
			get_posts(
				array(
					'post_type'           => 'ligatura_wiki',
					'post_status'         => array( 'publish', 'private' ),
					'perm'                => 'readable',
					'posts_per_page'      => -1,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'tax_query'           => array(
						array(
							'taxonomy' => 'ligatura_saga_topic',
							'field'    => 'slug',
							'terms'    => $slug,
						),
					),
				)
			),
			static function ( $post ): bool {
				return $post instanceof \WP_Post && current_user_can( 'read_post', $post->ID );
			}
		)
	);

	sort_entries( $posts );

	return $posts;
}

/**
 * Sort entries alphabetically while ignoring a leading standalone “The”.
 *
 * @param \WP_Post[] $posts Posts to sort in place.
 */
function sort_entries( array &$posts ): void {
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
 * Render the default Entry Type groups.
 */
function render_grouped_directory(): string {
	$groups = '';

	foreach ( entry_type_choices() as $slug => $name ) {
		$posts = entries_for_type( $slug );

		if ( empty( $posts ) ) {
			continue;
		}

		$preview = array_slice( $posts, 0, PREVIEW_LIMIT );
		$groups .= '<section class="manuscriptum-illuminatum-directory-group manuscriptum-illuminatum-wiki-group" data-entry-type="' . esc_attr( $slug ) . '">';
		$groups .= '<h2>' . esc_html( $name ) . '</h2>';
		$groups .= render_entry_teasers( $preview );

		if ( count( $posts ) > PREVIEW_LIMIT ) {
			$term = get_term_by( 'slug', $slug, 'ligatura_entry_type' );

			if ( $term instanceof \WP_Term ) {
				$groups .= '<a class="manuscriptum-illuminatum-wiki-group__more" href="' . esc_url( get_term_link( $term ) ) . '" aria-label="' . esc_attr( sprintf( __( 'See more %s entries', 'ligatura-manuscripti-illuminati' ), $name ) ) . '">' . esc_html__( 'See More...', 'ligatura-manuscripti-illuminati' ) . '</a>';
			}
		}

		$groups .= '</section>';
	}

	return $groups;
}

/**
 * Render shared theme teasers for a grouped Entry Type preview.
 *
 * @param \WP_Post[] $posts Readable Speculum entries.
 */
function render_entry_teasers( array $posts ): string {
	if ( empty( $posts ) ) {
		return '';
	}

	if ( ! function_exists( 'paginae_manuscripti_illuminati_render_speculum_teaser' ) ) {
		return render_entry_list( $posts );
	}

	$html = '<div class="manuscriptum-illuminatum-directory-teaser-grid manuscriptum-illuminatum-wiki-teaser-grid">';

	foreach ( $posts as $post ) {
		if ( ! $post instanceof \WP_Post || ! current_user_can( 'read_post', $post->ID ) ) {
			continue;
		}

		$html .= paginae_manuscripti_illuminati_render_speculum_teaser(
			$post->ID,
			array(
				'show_entry_type' => false,
				'show_modified'   => false,
			)
		);
	}

	return $html . '</div>';
}

/**
 * Render a simple linked entry list.
 *
 * @param \WP_Post[] $posts Readable Speculum entries.
 */
function render_entry_list( array $posts ): string {
	if ( empty( $posts ) ) {
		return '';
	}

	$html = '<ul class="manuscriptum-illuminatum-wiki-entry-list">';

	foreach ( $posts as $post ) {
		if ( ! $post instanceof \WP_Post || ! current_user_can( 'read_post', $post->ID ) ) {
			continue;
		}

		$html .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></li>';
	}

	return $html . '</ul>';
}

/**
 * Render text-focused search results.
 *
 * @param \WP_Post[] $posts Readable Speculum entries.
 */
function render_search_results( array $posts ): string {
	if ( empty( $posts ) ) {
		return '';
	}

	$html = '<section class="manuscriptum-illuminatum-wiki-search-results" aria-label="' . esc_attr__( 'Speculum search results', 'ligatura-manuscripti-illuminati' ) . '">';
	$html .= '<h2 class="screen-reader-text">' . esc_html__( 'Search results', 'ligatura-manuscripti-illuminati' ) . '</h2>';

	foreach ( $posts as $post ) {
		if ( ! $post instanceof \WP_Post || ! current_user_can( 'read_post', $post->ID ) ) {
			continue;
		}

		$summary = get_post_meta( $post->ID, 'ligatura_public_summary', true );
		$excerpt = wp_trim_words( $summary ? (string) $summary : get_the_excerpt( $post ), 32 );
		$types   = get_the_terms( $post, 'ligatura_entry_type' );
		$meta    = array();

		if ( $types && ! is_wp_error( $types ) ) {
			usort( $types, static fn( \WP_Term $left, \WP_Term $right ): int => strnatcasecmp( $left->name, $right->name ) );
			$meta[] = implode( ' / ', wp_list_pluck( $types, 'name' ) );
		}

		$meta[] = sprintf(
			/* translators: %s: modified date. */
			__( 'Last edited: %s', 'ligatura-manuscripti-illuminati' ),
			get_the_modified_date( get_option( 'date_format' ), $post )
		);

		$html .= '<article class="manuscriptum-illuminatum-wiki-result">';
		$html .= '<h3><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3>';
		$html .= '<p>' . esc_html( $excerpt ) . '</p>';
		$html .= '<p class="manuscriptum-illuminatum-wiki-result__meta">' . esc_html( implode( ' · ', array_filter( $meta ) ) ) . '</p>';
		$html .= '</article>';
	}

	return $html . '</section>';
}
