<?php
/**
 * Searchable Covenant Records directory and REST endpoint.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\CovenantDirectory;

use LigaturaManuscriptiIlluminati\JournalDates;
use LigaturaManuscriptiIlluminati\JournalDirectory;
use LigaturaManuscriptiIlluminati\MetaBoxes;
use LigaturaManuscriptiIlluminati\PostTypes;
use LigaturaManuscriptiIlluminati\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PER_PAGE = 10;
const META_KEY = 'ligatura_covenant_saga_date';

/**
 * Return the validated collection scope represented by the current route.
 *
 * Generic slug captures are validated at request time, so taxonomy changes do
 * not require term-dependent rewrite rules or extra flushes.
 *
 * @return array{taxonomy:string,filter:string,slug:string,term:\WP_Term}|array{}
 */
function collection_scope(): array {
	$routes = array(
		'ligatura_covenant_entry_type' => array( 'taxonomy' => 'ligatura_entry_type', 'filter' => 'covenant_entry_type' ),
		'ligatura_covenant_topic'      => array( 'taxonomy' => 'ligatura_saga_topic', 'filter' => 'covenant_topic' ),
	);

	foreach ( $routes as $query_var => $route ) {
		$slug = sanitize_title( (string) get_query_var( $query_var ) );

		if ( '' === $slug ) {
			continue;
		}

		$term = get_term_by( 'slug', $slug, $route['taxonomy'] );

		if ( $term instanceof \WP_Term ) {
			return array(
				'taxonomy' => $route['taxonomy'],
				'filter'   => $route['filter'],
				'slug'     => $slug,
				'term'     => $term,
			);
		}

		return array();
	}

	return array();
}

/**
 * Return whether the request contains either reserved collection query var.
 */
function has_collection_request(): bool {
	return '' !== (string) get_query_var( 'ligatura_covenant_entry_type' ) || '' !== (string) get_query_var( 'ligatura_covenant_topic' );
}

/**
 * Turn nonexistent scoped taxonomy slugs into real 404 responses.
 */
function maybe_404_invalid_collection(): void {
	if ( ! has_collection_request() || collection_scope() ) {
		return;
	}

	global $wp_query;
	$wp_query->set_404();
	remove_action( 'template_redirect', 'redirect_canonical' );
	status_header( 404 );
	nocache_headers();
}

/**
 * Return the canonical URL for a Covenant Records taxonomy collection.
 */
function collection_url( string $taxonomy, string $slug ): string {
	$namespace = 'ligatura_entry_type' === $taxonomy ? 'type' : 'topics';

	return home_url( user_trailingslashit( 'covenant-records/' . $namespace . '/' . sanitize_title( $slug ) ) );
}

/**
 * Register the authenticated Covenant Records directory REST route.
 */
function register_rest_route(): void {
	\register_rest_route(
		'ligatura-manuscripti-illuminati/v1',
		'/covenant-records',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_records',
			'permission_callback' => static function (): bool {
				return ! Privacy\login_required() || is_user_logged_in();
			},
			'args'                => array(
				'page' => array(
					'default'           => 1,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}

/**
 * Return one filtered page of Covenant Records.
 */
function get_records( \WP_REST_Request $request ): \WP_REST_Response {
	$page    = max( 1, absint( $request->get_param( 'page' ) ) );
	$filters = sanitize_filters( $request->get_params() );
	$query   = query_records( $filters, $page );

	return new \WP_REST_Response(
		array(
			'html'    => render_rows( $query->posts ),
			'page'    => $page,
			'hasMore' => $page < (int) $query->max_num_pages,
			'count'   => count( $query->posts ),
			'total'   => (int) $query->found_posts,
		)
	);
}

/**
 * Render the Covenant Records directory below its editable root Page content.
 */
function render_directory(): string {
	$scope        = collection_scope();
	$filters      = sanitize_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only bookmarkable filters.

	if ( $scope ) {
		$filters[ $scope['filter'] ] = $scope['slug'];
	}

	$query        = query_records( $filters, 1 );
	$rows         = render_rows( $query->posts );
	$has_more     = 1 < (int) $query->max_num_pages;
	$action_url   = $scope ? collection_url( $scope['taxonomy'], $scope['slug'] ) : PostTypes\get_directory_url( 'ligatura_covenant' );
	$topics       = taxonomy_choices( 'ligatura_saga_topic' );
	$entry_types  = taxonomy_choices( 'ligatura_entry_type' );
	$personae     = relationship_choices( 'ligatura_related_characters', 'ligatura_character' );
	$places       = relationship_choices( 'ligatura_related_places', 'ligatura_wiki', true );
	$entries      = relationship_choices( 'ligatura_related_entries', 'ligatura_wiki' );
	$scope_label  = $scope ? $scope['term']->name : '';

	ob_start();
	?>
	<section
		id="covenant-records-directory"
		class="manuscriptum-illuminatum-journal-directory manuscriptum-illuminatum-covenant-directory"
		aria-label="<?php esc_attr_e( 'Covenant Records directory', 'ligatura-manuscripti-illuminati' ); ?>"
		data-endpoint="<?php echo esc_url( rest_url( 'ligatura-manuscripti-illuminati/v1/covenant-records' ) ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
		data-page="1"
		data-has-more="<?php echo $has_more ? 'true' : 'false'; ?>"
	>
		<?php if ( $scope_label ) : ?>
			<header class="manuscriptum-illuminatum-directory-context">
				<h2><?php echo esc_html( sprintf( __( 'Covenant Records: %s', 'ligatura-manuscripti-illuminati' ), $scope_label ) ); ?></h2>
			</header>
		<?php endif; ?>
		<form class="manuscriptum-illuminatum-journal-search manuscriptum-illuminatum-covenant-search" method="get" action="<?php echo esc_url( $action_url ); ?>" data-covenant-search-form>
			<?php if ( $scope ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $scope['filter'] ); ?>" value="<?php echo esc_attr( $scope['slug'] ); ?>" data-directory-context />
			<?php endif; ?>
			<div class="manuscriptum-illuminatum-journal-search__primary">
				<label for="covenant-search-query"><?php esc_html_e( 'Search Covenant Records', 'ligatura-manuscripti-illuminati' ); ?></label>
				<div class="manuscriptum-illuminatum-journal-search__query">
					<input type="search" id="covenant-search-query" name="covenant_q" value="<?php echo esc_attr( $filters['covenant_q'] ); ?>" placeholder="<?php esc_attr_e( 'Search record title, text, or Public Summary…', 'ligatura-manuscripti-illuminati' ); ?>" />
					<button type="submit"><?php esc_html_e( 'Search', 'ligatura-manuscripti-illuminati' ); ?></button>
				</div>
				<button type="button" class="manuscriptum-illuminatum-journal-search__advanced-toggle" aria-expanded="false" aria-controls="covenant-advanced-search" data-covenant-advanced-toggle><?php esc_html_e( 'Advanced Search', 'ligatura-manuscripti-illuminati' ); ?></button>
			</div>

			<div id="covenant-advanced-search" class="manuscriptum-illuminatum-journal-search__advanced" data-covenant-advanced hidden>
				<?php if ( ! $scope || 'covenant_topic' !== $scope['filter'] ) : ?>
					<?php render_choice_field( 'covenant-topic', 'covenant_topic', __( 'Saga Topic', 'ligatura-manuscripti-illuminati' ), __( 'All topics', 'ligatura-manuscripti-illuminati' ), $topics, (string) $filters['covenant_topic'] ); ?>
				<?php endif; ?>
				<?php if ( ! $scope || 'covenant_entry_type' !== $scope['filter'] ) : ?>
					<?php render_choice_field( 'covenant-entry-type', 'covenant_entry_type', __( 'Entry Type', 'ligatura-manuscripti-illuminati' ), __( 'All entry types', 'ligatura-manuscripti-illuminati' ), $entry_types, (string) $filters['covenant_entry_type'] ); ?>
				<?php endif; ?>
				<?php render_choice_field( 'covenant-persona', 'covenant_persona', __( 'Related Persona', 'ligatura-manuscripti-illuminati' ), __( 'All Personae', 'ligatura-manuscripti-illuminati' ), $personae, (string) $filters['covenant_persona'] ); ?>
				<?php render_choice_field( 'covenant-place', 'covenant_place', __( 'Related Place', 'ligatura-manuscripti-illuminati' ), __( 'All places', 'ligatura-manuscripti-illuminati' ), $places, (string) $filters['covenant_place'] ); ?>
				<?php render_choice_field( 'covenant-entry', 'covenant_entry', __( 'Related Entry', 'ligatura-manuscripti-illuminati' ), __( 'All entries', 'ligatura-manuscripti-illuminati' ), $entries, (string) $filters['covenant_entry'] ); ?>

				<div class="manuscriptum-illuminatum-journal-search__field">
					<label for="covenant-saga-from"><?php esc_html_e( 'From Saga Date', 'ligatura-manuscripti-illuminati' ); ?></label>
					<input type="date" id="covenant-saga-from" name="covenant_saga_from" value="<?php echo esc_attr( $filters['covenant_saga_from'] ); ?>" />
				</div>
				<div class="manuscriptum-illuminatum-journal-search__field">
					<label for="covenant-saga-to"><?php esc_html_e( 'To Saga Date', 'ligatura-manuscripti-illuminati' ); ?></label>
					<input type="date" id="covenant-saga-to" name="covenant_saga_to" value="<?php echo esc_attr( $filters['covenant_saga_to'] ); ?>" />
				</div>

				<div class="manuscriptum-illuminatum-journal-search__actions">
					<button type="submit"><?php esc_html_e( 'Apply Filters', 'ligatura-manuscripti-illuminati' ); ?></button>
					<a href="<?php echo esc_url( $action_url ); ?>" data-covenant-clear><?php esc_html_e( 'Clear filters', 'ligatura-manuscripti-illuminati' ); ?></a>
				</div>
			</div>
		</form>

		<p class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true" data-covenant-status></p>
		<p class="manuscriptum-illuminatum-journal-directory__error" role="alert" data-covenant-error hidden></p>
		<?php if ( ! $scope_label ) : ?>
			<h2 class="screen-reader-text"><?php esc_html_e( 'Covenant Records', 'ligatura-manuscripti-illuminati' ); ?></h2>
		<?php endif; ?>
		<div class="manuscriptum-illuminatum-journal-list manuscriptum-illuminatum-covenant-list" data-covenant-results aria-busy="false">
			<?php echo $rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared theme partial escapes row data. ?>
		</div>
		<p class="manuscriptum-illuminatum-journal-directory__empty" data-covenant-empty<?php echo $rows ? ' hidden' : ''; ?>><?php esc_html_e( 'No Covenant Records match these search criteria.', 'ligatura-manuscripti-illuminati' ); ?></p>
		<div class="manuscriptum-illuminatum-journal-directory__more">
			<button type="button" data-covenant-load-more<?php echo $has_more ? '' : ' hidden'; ?>><?php esc_html_e( 'More Covenant Records', 'ligatura-manuscripti-illuminati' ); ?></button>
		</div>
	</section>
	<?php

	return (string) ob_get_clean();
}

/**
 * Render one human-readable select field.
 *
 * @param array<int|string,string> $choices Choices.
 */
function render_choice_field( string $id, string $name, string $label, string $empty_label, array $choices, string $current ): void {
	?>
	<div class="manuscriptum-illuminatum-journal-search__field">
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
			<option value=""><?php echo esc_html( $empty_label ); ?></option>
			<?php foreach ( $choices as $value => $choice_label ) : ?>
				<option value="<?php echo esc_attr( (string) $value ); ?>"<?php echo selected( $current, (string) $value, false ); ?>><?php echo esc_html( $choice_label ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php
}

/**
 * Sanitize directory filters.
 *
 * @param array<string,mixed> $source Raw request values.
 * @return array<string,string|int>
 */
function sanitize_filters( array $source ): array {
	$filters = array(
		'covenant_q'          => JournalDirectory\sanitize_scalar_text( $source['covenant_q'] ?? '' ),
		'covenant_topic'      => sanitize_title( JournalDirectory\sanitize_scalar_text( $source['covenant_topic'] ?? '' ) ),
		'covenant_entry_type' => sanitize_title( JournalDirectory\sanitize_scalar_text( $source['covenant_entry_type'] ?? '' ) ),
		'covenant_persona'    => JournalDirectory\sanitize_id( $source['covenant_persona'] ?? 0 ),
		'covenant_place'      => JournalDirectory\sanitize_id( $source['covenant_place'] ?? 0 ),
		'covenant_entry'      => JournalDirectory\sanitize_id( $source['covenant_entry'] ?? 0 ),
		'covenant_saga_from'  => JournalDirectory\sanitize_date( $source['covenant_saga_from'] ?? '' ),
		'covenant_saga_to'    => JournalDirectory\sanitize_date( $source['covenant_saga_to'] ?? '' ),
	);

	$taxonomy_filters = array(
		'covenant_topic'      => 'ligatura_saga_topic',
		'covenant_entry_type' => 'ligatura_entry_type',
	);

	foreach ( $taxonomy_filters as $key => $taxonomy ) {
		$term = $filters[ $key ] ? get_term_by( 'slug', (string) $filters[ $key ], $taxonomy ) : null;

		if ( $filters[ $key ] && ! $term instanceof \WP_Term ) {
			$filters[ $key ] = '';
		}
	}

	$choice_maps = array(
		'covenant_persona'    => relationship_choices( 'ligatura_related_characters', 'ligatura_character' ),
		'covenant_place'      => relationship_choices( 'ligatura_related_places', 'ligatura_wiki', true ),
		'covenant_entry'      => relationship_choices( 'ligatura_related_entries', 'ligatura_wiki' ),
	);

	foreach ( $choice_maps as $key => $choices ) {
		if ( $filters[ $key ] && ! isset( $choices[ $filters[ $key ] ] ) ) {
			$filters[ $key ] = 0;
		}
	}

	return $filters;
}

/**
 * Query readable Covenant Records with fixed, validated filters.
 *
 * @param array<string,string|int> $filters Sanitized filters.
 */
function query_records( array $filters, int $page = 1 ): \WP_Query {
	$args = JournalDates\saga_date_query_args(
		'ligatura_covenant',
		META_KEY,
		array(
			'post_status'    => array( 'publish', 'private' ),
			'perm'           => 'readable',
			'posts_per_page' => PER_PAGE,
			'paged'          => max( 1, $page ),
			'no_found_rows'  => false,
		),
		'DESC'
	);

	if ( $filters['covenant_q'] ) {
		$args['post__in'] = text_search_post_ids( (string) $filters['covenant_q'] );
	}

	$tax_query = array( 'relation' => 'AND' );

	if ( $filters['covenant_topic'] ) {
		$tax_query[] = array( 'taxonomy' => 'ligatura_saga_topic', 'field' => 'slug', 'terms' => $filters['covenant_topic'] );
	}

	if ( $filters['covenant_entry_type'] ) {
		$tax_query[] = array( 'taxonomy' => 'ligatura_entry_type', 'field' => 'slug', 'terms' => $filters['covenant_entry_type'] );
	}

	if ( 1 < count( $tax_query ) ) {
		$args['tax_query'] = $tax_query;
	}

	$meta_query = array( 'relation' => 'AND' );
	append_relationship_filter( $meta_query, 'ligatura_related_characters', absint( $filters['covenant_persona'] ) );
	append_relationship_filter( $meta_query, 'ligatura_related_places', absint( $filters['covenant_place'] ) );
	append_relationship_filter( $meta_query, 'ligatura_related_entries', absint( $filters['covenant_entry'] ) );
	JournalDirectory\append_date_range( $meta_query, META_KEY, (string) $filters['covenant_saga_from'], (string) $filters['covenant_saga_to'] );

	if ( 1 < count( $meta_query ) ) {
		$args['meta_query'] = $meta_query;
	}

	return new \WP_Query( $args );
}

/**
 * Return readable record IDs matching title/content/excerpt or Public Summary.
 *
 * @return int[]
 */
function text_search_post_ids( string $search ): array {
	$base = array(
		'post_type'      => 'ligatura_covenant',
		'post_status'    => array( 'publish', 'private' ),
		'perm'           => 'readable',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	);
	$content_query = new \WP_Query( array_merge( $base, array( 's' => $search ) ) );
	$summary_query = new \WP_Query(
		array_merge(
			$base,
			array(
				'meta_query' => array(
					array( 'key' => 'ligatura_public_summary', 'value' => $search, 'compare' => 'LIKE' ),
				),
			)
		)
	);

	$ids = array_unique( array_merge( array_map( 'absint', $content_query->posts ), array_map( 'absint', $summary_query->posts ) ) );

	return $ids ?: array( 0 );
}

/**
 * Add a token-safe CSV relationship filter.
 *
 * @param array<int|string,mixed> $meta_query Meta query clauses.
 */
function append_relationship_filter( array &$meta_query, string $meta_key, int $entity_id ): void {
	if ( $entity_id < 1 ) {
		return;
	}

	$meta_query[] = array(
		'key'     => $meta_key,
		'value'   => '(^|,)' . $entity_id . '(,|$)',
		'compare' => 'REGEXP',
	);
}

/**
 * Render records with the theme's shared full-width row component.
 *
 * @param \WP_Post[] $posts Covenant Records.
 */
function render_rows( array $posts ): string {
	if ( ! function_exists( 'paginae_manuscripti_illuminati_render_covenant_record_row' ) ) {
		return '';
	}

	$rows = '';

	foreach ( $posts as $post ) {
		if ( $post instanceof \WP_Post && \LigaturaManuscriptiIlluminati\Privacy\can_read_post( $post->ID ) ) {
			$rows .= paginae_manuscripti_illuminati_render_covenant_record_row( $post->ID );
		}
	}

	return $rows;
}

/**
 * Return taxonomy choices represented by readable Covenant Records.
 *
 * @return array<string,string>
 */
function taxonomy_choices( string $taxonomy ): array {
	$record_ids = JournalDirectory\readable_post_ids( 'ligatura_covenant' );

	if ( empty( $record_ids ) ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'object_ids' => $record_ids,
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
 * Return relationship choices actually used by readable records.
 *
 * @return array<int,string>
 */
function relationship_choices( string $meta_key, string $post_type, bool $require_place = false ): array {
	$choices = array();

	foreach ( JournalDirectory\readable_post_ids( 'ligatura_covenant' ) as $record_id ) {
		foreach ( MetaBoxes\parse_id_list( get_post_meta( $record_id, $meta_key, true ) ) as $entity_id ) {
			if ( $post_type !== get_post_type( $entity_id ) || ! \LigaturaManuscriptiIlluminati\Privacy\can_read_post( $entity_id ) ) {
				continue;
			}

			if ( $require_place && ! has_term( 'place', 'ligatura_entry_type', $entity_id ) ) {
				continue;
			}

			$choices[ $entity_id ] = get_the_title( $entity_id );
		}
	}

	uasort( $choices, 'strnatcasecmp' );

	return $choices;
}

/**
 * Return a Covenant Record's normalized Saga Date.
 */
function get_post_saga_date( int $post_id ): string {
	$value = (string) get_post_meta( $post_id, META_KEY, true );

	return JournalDates\is_valid_saga_date( $value ) ? $value : '';
}

/**
 * Return a Covenant Record's formatted Saga Date.
 */
function format_post_saga_date( int $post_id ): string {
	return JournalDates\format_saga_date( get_post_saga_date( $post_id ) );
}
