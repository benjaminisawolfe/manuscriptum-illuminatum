<?php
/**
 * Journal Saga Date helpers.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\JournalDates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META_KEY = 'ligatura_diary_saga_date';

/**
 * Sanitize a normalized Saga Date value.
 *
 * @param mixed $value Raw date value.
 */
function sanitize_saga_date( mixed $value ): string {
	$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	$value = trim( $value );

	return is_valid_saga_date( $value ) ? $value : '';
}

/**
 * Validate YYYY-MM-DD Saga Date values.
 */
function is_valid_saga_date( string $value ): bool {
	if ( ! preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $value, $matches ) ) {
		return false;
	}

	return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] );
}

/**
 * Return the normalized Saga Date for a Journal.
 */
function get_post_saga_date( int $post_id ): string {
	$value = (string) get_post_meta( $post_id, META_KEY, true );

	return is_valid_saga_date( $value ) ? $value : '';
}

/**
 * Format a normalized Saga Date for visitors.
 */
function format_saga_date( string $value ): string {
	if ( ! is_valid_saga_date( $value ) ) {
		return '';
	}

	$parts  = array_map( 'absint', explode( '-', $value ) );
	$months = array(
		1  => __( 'January', 'ligatura-manuscripti-illuminati' ),
		2  => __( 'February', 'ligatura-manuscripti-illuminati' ),
		3  => __( 'March', 'ligatura-manuscripti-illuminati' ),
		4  => __( 'April', 'ligatura-manuscripti-illuminati' ),
		5  => __( 'May', 'ligatura-manuscripti-illuminati' ),
		6  => __( 'June', 'ligatura-manuscripti-illuminati' ),
		7  => __( 'July', 'ligatura-manuscripti-illuminati' ),
		8  => __( 'August', 'ligatura-manuscripti-illuminati' ),
		9  => __( 'September', 'ligatura-manuscripti-illuminati' ),
		10 => __( 'October', 'ligatura-manuscripti-illuminati' ),
		11 => __( 'November', 'ligatura-manuscripti-illuminati' ),
		12 => __( 'December', 'ligatura-manuscripti-illuminati' ),
	);

	return sprintf(
		/* translators: 1: Month name, 2: day of month, 3: year. */
		__( '%1$s %2$d, %3$04d', 'ligatura-manuscripti-illuminati' ),
		$months[ $parts[1] ],
		$parts[2],
		$parts[0]
	);
}

/**
 * Format a Journal's Saga Date for visitors.
 */
function format_post_saga_date( int $post_id ): string {
	return format_saga_date( get_post_saga_date( $post_id ) );
}

/**
 * Mark the main Journal archive query for Saga Date ordering.
 *
 * @param \WP_Query $query Query object.
 */
function order_main_journal_queries( \WP_Query $query ): void {
	if ( is_admin() ) {
		if ( $query->is_main_query() && 'ligatura_diary' === $query->get( 'post_type' ) && 'saga_date' === $query->get( 'orderby' ) ) {
			$query->set( 'ligatura_journal_saga_date_order', strtoupper( (string) ( $query->get( 'order' ) ?: 'ASC' ) ) );
			$query->set( 'orderby', 'none' );
		}

		return;
	}

	if ( ! $query->is_main_query() || ! $query->is_post_type_archive( 'ligatura_diary' ) ) {
		return;
	}

	$query->set( 'ligatura_journal_saga_date_order', 'DESC' );
	$query->set( 'ligatura_journal_secondary_order', 'DESC' );
	$query->set( 'orderby', 'none' );
}

/**
 * Apply Saga Date ordering to marked Journal queries.
 *
 * @param array<string,string> $clauses Query clauses.
 * @param \WP_Query            $query   Query object.
 * @return array<string,string>
 */
function order_journal_query_clauses( array $clauses, \WP_Query $query ): array {
	$order = strtoupper( (string) ( $query->get( 'ligatura_saga_date_order' ) ?: $query->get( 'ligatura_journal_saga_date_order' ) ) );

	if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
		return $clauses;
	}

	global $wpdb;

	$alias       = 'ligatura_saga_date_meta';
	$required    = $query->get( 'ligatura_saga_date_required' ) || $query->get( 'ligatura_journal_require_saga_date' );
	$join_type   = $required ? 'INNER JOIN' : 'LEFT JOIN';
	$meta_key    = (string) ( $query->get( 'ligatura_saga_date_meta_key' ) ?: META_KEY );
	$meta_key    = esc_sql( sanitize_key( $meta_key ) );
	$valid_check = "{$alias}.meta_value REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'";

	if ( ! str_contains( $clauses['join'], " {$alias} " ) ) {
		$clauses['join'] .= " {$join_type} {$wpdb->postmeta} AS {$alias} ON ({$wpdb->posts}.ID = {$alias}.post_id AND {$alias}.meta_key = '{$meta_key}')";
	}

	if ( $required ) {
		$clauses['where'] .= " AND {$valid_check}";
	}

	$secondary_order = strtoupper( (string) ( $query->get( 'ligatura_saga_date_secondary_order' ) ?: $query->get( 'ligatura_journal_secondary_order' ) ?: $order ) );
	$secondary_order = in_array( $secondary_order, array( 'ASC', 'DESC' ), true ) ? $secondary_order : $order;
	$date_fallback   = 'ASC' === $order ? "'9999-12-31'" : "'0000-00-00'";
	$date_sort       = "CASE WHEN {$valid_check} THEN {$alias}.meta_value ELSE {$date_fallback} END {$order}";

	$clauses['orderby'] = "CASE WHEN {$valid_check} THEN 0 ELSE 1 END ASC, {$date_sort}, {$wpdb->posts}.post_date {$secondary_order}, {$wpdb->posts}.ID {$secondary_order}";

	return $clauses;
}

/**
 * Return query args for any campaign content ordered by a normalized Saga Date.
 *
 * @param string              $post_type Post type.
 * @param string              $meta_key  Saga Date meta key.
 * @param array<string,mixed> $args      Additional query args.
 * @param string              $order     ASC or DESC.
 * @return array<string,mixed>
 */
function saga_date_query_args( string $post_type, string $meta_key, array $args = array(), string $order = 'DESC' ): array {
	$order = strtoupper( $order );
	$order = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';

	return array_merge(
		array(
			'post_type'                     => sanitize_key( $post_type ),
			'post_status'                   => 'publish',
			'ignore_sticky_posts'           => true,
			'ligatura_saga_date_order'           => $order,
			'ligatura_saga_date_secondary_order' => $order,
			'ligatura_saga_date_meta_key'        => sanitize_key( $meta_key ),
			'orderby'                       => 'none',
		),
		$args
	);
}

/**
 * Add Saga Date to the Journals admin list table.
 *
 * @param array<string,string> $columns Existing columns.
 * @return array<string,string>
 */
function add_admin_saga_date_column( array $columns ): array {
	$updated = array();

	foreach ( $columns as $key => $label ) {
		$updated[ $key ] = $label;

		if ( 'title' === $key ) {
			$updated['ligatura_saga_date'] = __( 'Saga Date', 'ligatura-manuscripti-illuminati' );
		}
	}

	return $updated;
}

/**
 * Render the Saga Date admin column.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function render_admin_saga_date_column( string $column, int $post_id ): void {
	if ( 'ligatura_saga_date' !== $column ) {
		return;
	}

	$date = get_post_saga_date( $post_id );

	echo esc_html( $date ? format_saga_date( $date ) : __( 'Undated', 'ligatura-manuscripti-illuminati' ) );
}

/**
 * Make the Saga Date admin column sortable.
 *
 * @param array<string,string> $columns Existing columns.
 * @return array<string,string>
 */
function add_sortable_admin_column( array $columns ): array {
	$columns['ligatura_saga_date'] = 'saga_date';

	return $columns;
}

/**
 * Return public Journal query args with Saga Date ordering enabled.
 *
 * @param array<string,mixed> $args  Query args.
 * @param string              $order ASC or DESC.
 * @return array<string,mixed>
 */
function journal_query_args( array $args = array(), string $order = 'DESC' ): array {
	return saga_date_query_args( 'ligatura_diary', META_KEY, $args, $order );
}

/**
 * Return the latest N Journals by Saga Date, newest-to-oldest.
 *
 * @param int $count Number of Journals.
 * @return \WP_Post[]
 */
function latest_journal_posts( int $count ): array {
	if ( $count < 1 ) {
		return array();
	}

	$dated_query = new \WP_Query(
		journal_query_args(
			array(
				'posts_per_page'                => $count,
				'no_found_rows'                 => true,
				'ligatura_journal_require_saga_date' => true,
				'ligatura_journal_secondary_order'   => 'DESC',
			),
			'DESC'
		)
	);

	$posts = $dated_query->posts;

	if ( count( $posts ) < $count ) {
		$undated_query = new \WP_Query(
			array(
				'post_type'           => 'ligatura_diary',
				'post_status'         => 'publish',
				'posts_per_page'      => $count - count( $posts ),
				'post__not_in'        => wp_list_pluck( $posts, 'ID' ),
				'ignore_sticky_posts' => true,
				'orderby'             => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				'meta_query'          => array(
					'relation' => 'OR',
					array(
						'key'     => META_KEY,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => META_KEY,
						'value' => '',
					),
					array(
						'key'     => META_KEY,
						'value'   => '^[0-9]{4}-[0-9]{2}-[0-9]{2}$',
						'compare' => 'NOT REGEXP',
					),
				),
			)
		);

		$posts = array_merge( $posts, $undated_query->posts );
	}

	usort( $posts, __NAMESPACE__ . '\\compare_journal_posts_descending' );

	return $posts;
}

/**
 * Compare two Journal posts newest-to-oldest by Saga Date.
 */
function compare_journal_posts_descending( \WP_Post $a, \WP_Post $b ): int {
	$a_date = get_post_saga_date( $a->ID );
	$b_date = get_post_saga_date( $b->ID );

	if ( $a_date && $b_date && $a_date !== $b_date ) {
		return strcmp( $b_date, $a_date );
	}

	if ( $a_date && ! $b_date ) {
		return -1;
	}

	if ( ! $a_date && $b_date ) {
		return 1;
	}

	if ( $a->post_date !== $b->post_date ) {
		return strcmp( $b->post_date, $a->post_date );
	}

	return $b->ID <=> $a->ID;
}
