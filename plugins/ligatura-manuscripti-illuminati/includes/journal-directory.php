<?php
/**
 * Searchable Journal directory and REST endpoint.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\JournalDirectory;

use LigaturaManuscriptiIlluminati\JournalDates;
use LigaturaManuscriptiIlluminati\PostTypes;
use LigaturaManuscriptiIlluminati\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PER_PAGE = 10;

/**
 * Register the authenticated Journal-directory REST route.
 */
function register_rest_route(): void {
	\register_rest_route(
		'ligatura-manuscripti-illuminati/v1',
		'/journals',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_journals',
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
 * Return one filtered page of Journals.
 */
function get_journals( \WP_REST_Request $request ): \WP_REST_Response {
	$page    = max( 1, absint( $request->get_param( 'page' ) ) );
	$filters = sanitize_filters( $request->get_params() );
	$query   = query_journals( $filters, $page );

	return new \WP_REST_Response(
		array(
			'html'     => render_rows( $query->posts ),
			'page'     => $page,
			'hasMore'  => $page < (int) $query->max_num_pages,
			'count'    => count( $query->posts ),
			'total'    => (int) $query->found_posts,
		)
	);
}

/**
 * Render the Journal directory for the editable Commentarii root Page.
 */
function render_directory(): string {
	$filters    = sanitize_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only bookmarkable filters.
	$persona    = current_persona();

	if ( $persona ) {
		$filters['journal_persona'] = $persona->post_name;
	}

	$query      = query_journals( $filters, 1 );
	$rows       = render_rows( $query->posts );
	$has_more   = 1 < (int) $query->max_num_pages;
	$action_url = $persona ? persona_collection_url( $persona ) : PostTypes\get_directory_url( 'ligatura_diary' );
	$topics     = topic_choices();
	$characters = character_choices();

	ob_start();
	?>
	<section
		id="commentarii-directory"
		class="manuscriptum-illuminatum-journal-directory"
		aria-label="<?php esc_attr_e( 'Commentarii Journal directory', 'ligatura-manuscripti-illuminati' ); ?>"
		data-endpoint="<?php echo esc_url( rest_url( 'ligatura-manuscripti-illuminati/v1/journals' ) ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
		data-page="1"
		data-has-more="<?php echo $has_more ? 'true' : 'false'; ?>"
	>
		<form class="manuscriptum-illuminatum-journal-search" method="get" action="<?php echo esc_url( $action_url ); ?>" data-journal-search-form>
			<?php if ( $persona ) : ?>
				<input type="hidden" name="journal_persona" value="<?php echo esc_attr( $persona->post_name ); ?>" data-directory-context />
			<?php endif; ?>
			<div class="manuscriptum-illuminatum-journal-search__primary">
				<label for="journal-search-query"><?php esc_html_e( 'Search Journals', 'ligatura-manuscripti-illuminati' ); ?></label>
				<div class="manuscriptum-illuminatum-journal-search__query">
					<input
						type="search"
						id="journal-search-query"
						name="journal_q"
						value="<?php echo esc_attr( $filters['journal_q'] ); ?>"
						placeholder="<?php esc_attr_e( 'Search Journal title or text…', 'ligatura-manuscripti-illuminati' ); ?>"
					/>
					<button type="submit"><?php esc_html_e( 'Search', 'ligatura-manuscripti-illuminati' ); ?></button>
				</div>
				<button
					type="button"
					class="manuscriptum-illuminatum-journal-search__advanced-toggle"
					aria-expanded="false"
					aria-controls="journal-advanced-search"
					data-journal-advanced-toggle
				>
					<?php esc_html_e( 'Advanced Search', 'ligatura-manuscripti-illuminati' ); ?>
				</button>
			</div>

			<div id="journal-advanced-search" class="manuscriptum-illuminatum-journal-search__advanced" data-journal-advanced hidden>
				<div class="manuscriptum-illuminatum-journal-search__field">
					<label for="journal-topic"><?php esc_html_e( 'Topic', 'ligatura-manuscripti-illuminati' ); ?></label>
					<select id="journal-topic" name="journal_topic">
						<option value=""><?php esc_html_e( 'All topics', 'ligatura-manuscripti-illuminati' ); ?></option>
						<?php foreach ( $topics as $slug => $name ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"<?php echo selected( $filters['journal_topic'], $slug, false ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="manuscriptum-illuminatum-journal-search__field">
					<label for="journal-character"><?php esc_html_e( 'Character Author', 'ligatura-manuscripti-illuminati' ); ?></label>
					<select id="journal-character" name="journal_character">
						<option value=""><?php esc_html_e( 'All Character Authors', 'ligatura-manuscripti-illuminati' ); ?></option>
						<?php foreach ( $characters as $character_id => $title ) : ?>
							<option value="<?php echo esc_attr( (string) $character_id ); ?>"<?php echo selected( $filters['journal_character'], $character_id, false ); ?>><?php echo esc_html( $title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="manuscriptum-illuminatum-journal-search__field">
					<label for="journal-session-from"><?php esc_html_e( 'From Session Date', 'ligatura-manuscripti-illuminati' ); ?></label>
					<input type="date" id="journal-session-from" name="journal_session_from" value="<?php echo esc_attr( $filters['journal_session_from'] ); ?>" />
				</div>

				<div class="manuscriptum-illuminatum-journal-search__field">
					<label for="journal-session-to"><?php esc_html_e( 'To Session Date', 'ligatura-manuscripti-illuminati' ); ?></label>
					<input type="date" id="journal-session-to" name="journal_session_to" value="<?php echo esc_attr( $filters['journal_session_to'] ); ?>" />
				</div>

				<div class="manuscriptum-illuminatum-journal-search__field">
					<label for="journal-saga-from"><?php esc_html_e( 'From Saga Date', 'ligatura-manuscripti-illuminati' ); ?></label>
					<input type="date" id="journal-saga-from" name="journal_saga_from" value="<?php echo esc_attr( $filters['journal_saga_from'] ); ?>" />
				</div>

				<div class="manuscriptum-illuminatum-journal-search__field">
					<label for="journal-saga-to"><?php esc_html_e( 'To Saga Date', 'ligatura-manuscripti-illuminati' ); ?></label>
					<input type="date" id="journal-saga-to" name="journal_saga_to" value="<?php echo esc_attr( $filters['journal_saga_to'] ); ?>" />
				</div>

				<div class="manuscriptum-illuminatum-journal-search__actions">
					<button type="submit"><?php esc_html_e( 'Apply Filters', 'ligatura-manuscripti-illuminati' ); ?></button>
					<a href="<?php echo esc_url( $action_url ); ?>" data-journal-clear><?php esc_html_e( 'Clear filters', 'ligatura-manuscripti-illuminati' ); ?></a>
				</div>
			</div>
		</form>

		<?php if ( $persona ) : ?>
			<section class="manuscriptum-illuminatum-commentarii-persona-context" aria-labelledby="commentarii-persona-heading">
				<h2 id="commentarii-persona-heading"><?php echo esc_html( sprintf( __( 'Commentarii by %s', 'ligatura-manuscripti-illuminati' ), get_the_title( $persona ) ) ); ?></h2>
				<p><a href="<?php echo esc_url( PostTypes\get_directory_url( 'ligatura_diary' ) ); ?>"><?php esc_html_e( 'All Commentarii', 'ligatura-manuscripti-illuminati' ); ?></a></p>
			</section>
		<?php else : ?>
			<?php echo render_persona_links(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Function escapes all dynamic values. ?>
		<?php endif; ?>

		<p class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true" data-journal-status></p>
		<p class="manuscriptum-illuminatum-journal-directory__error" role="alert" data-journal-error hidden></p>

		<h2 class="screen-reader-text"><?php esc_html_e( 'Journals', 'ligatura-manuscripti-illuminati' ); ?></h2>
		<div class="manuscriptum-illuminatum-journal-list" data-journal-results aria-busy="false">
			<?php echo $rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared theme partial escapes row data. ?>
		</div>

		<p class="manuscriptum-illuminatum-journal-directory__empty" data-journal-empty<?php echo $rows ? ' hidden' : ''; ?>><?php esc_html_e( 'No Journals match these search criteria.', 'ligatura-manuscripti-illuminati' ); ?></p>

		<div class="manuscriptum-illuminatum-journal-directory__more">
			<button type="button" data-journal-load-more<?php echo $has_more ? '' : ' hidden'; ?>><?php esc_html_e( 'More Commentarii', 'ligatura-manuscripti-illuminati' ); ?></button>
		</div>
	</section>
	<?php

	return (string) ob_get_clean();
}

/**
 * Sanitize supported directory filters from a request-like array.
 *
 * @param array<string,mixed> $source Raw request values.
 * @return array<string,string|int>
 */
function sanitize_filters( array $source ): array {
	$filters = array(
		'journal_q'            => sanitize_scalar_text( $source['journal_q'] ?? '' ),
		'journal_topic'        => sanitize_title( sanitize_scalar_text( $source['journal_topic'] ?? '' ) ),
		'journal_character'    => sanitize_id( $source['journal_character'] ?? 0 ),
		'journal_session_from' => sanitize_date( $source['journal_session_from'] ?? '' ),
		'journal_session_to'   => sanitize_date( $source['journal_session_to'] ?? '' ),
		'journal_saga_from'    => sanitize_date( $source['journal_saga_from'] ?? '' ),
		'journal_saga_to'      => sanitize_date( $source['journal_saga_to'] ?? '' ),
		'journal_persona'      => sanitize_title( sanitize_scalar_text( $source['journal_persona'] ?? '' ) ),
	);

	if ( $filters['journal_persona'] && ! persona_from_slug( (string) $filters['journal_persona'] ) ) {
		$filters['journal_persona'] = '__invalid__';
	}

	if ( $filters['journal_topic'] && ! isset( topic_choices()[ $filters['journal_topic'] ] ) ) {
		$filters['journal_topic'] = '';
	}

	if ( $filters['journal_character'] && ! isset( character_choices()[ $filters['journal_character'] ] ) ) {
		$filters['journal_character'] = 0;
	}

	return $filters;
}

/**
 * Sanitize a scalar text filter.
 */
function sanitize_scalar_text( mixed $value ): string {
	return is_scalar( $value ) ? sanitize_text_field( wp_unslash( (string) $value ) ) : '';
}

/**
 * Sanitize a positive integer filter.
 */
function sanitize_id( mixed $value ): int {
	return is_scalar( $value ) ? absint( $value ) : 0;
}

/**
 * Sanitize a YYYY-MM-DD filter value.
 */
function sanitize_date( mixed $value ): string {
	$value = sanitize_scalar_text( $value );

	return JournalDates\is_valid_saga_date( $value ) ? $value : '';
}

/**
 * Build a privacy-aware Journal query from fixed, validated filters.
 *
 * @param array<string,string|int> $filters Sanitized filters.
 */
function query_journals( array $filters, int $page = 1 ): \WP_Query {
	$args = JournalDates\journal_query_args(
		array(
			'post_status'    => array( 'publish', 'private' ),
			'perm'           => 'readable',
			'posts_per_page' => PER_PAGE,
			'paged'          => max( 1, $page ),
			'no_found_rows'  => false,
		),
		'DESC'
	);

	if ( $filters['journal_q'] ) {
		$args['s'] = $filters['journal_q'];
	}

	if ( $filters['journal_topic'] ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'ligatura_saga_topic',
				'field'    => 'slug',
				'terms'    => $filters['journal_topic'],
			),
		);
	}

	$meta_query = array( 'relation' => 'AND' );

	if ( '__invalid__' === $filters['journal_persona'] ) {
		$args['post__in'] = array( 0 );
	} elseif ( $filters['journal_persona'] ) {
		$persona = persona_from_slug( (string) $filters['journal_persona'] );

		if ( $persona ) {
			$meta_query[] = array(
				'key'     => 'ligatura_diary_character',
				'value'   => $persona->ID,
				'compare' => '=',
				'type'    => 'NUMERIC',
			);
		}
	}

	if ( $filters['journal_character'] ) {
		$meta_query[] = array(
			'key'     => 'ligatura_diary_character',
			'value'   => $filters['journal_character'],
			'compare' => '=',
			'type'    => 'NUMERIC',
		);
	}

	append_date_range( $meta_query, 'ligatura_diary_session_date', (string) $filters['journal_session_from'], (string) $filters['journal_session_to'] );
	append_date_range( $meta_query, JournalDates\META_KEY, (string) $filters['journal_saga_from'], (string) $filters['journal_saga_to'] );

	if ( 1 < count( $meta_query ) ) {
		$args['meta_query'] = $meta_query;
	}

	return new \WP_Query( $args );
}

/**
 * Add validated date bounds to a fixed meta query.
 *
 * @param array<int|string,mixed> $meta_query Meta query clauses.
 */
function append_date_range( array &$meta_query, string $meta_key, string $from, string $to ): void {
	if ( $from ) {
		$meta_query[] = array(
			'key'     => $meta_key,
			'value'   => $from,
			'compare' => '>=',
			'type'    => 'DATE',
		);
	}

	if ( $to ) {
		$meta_query[] = array(
			'key'     => $meta_key,
			'value'   => $to,
			'compare' => '<=',
			'type'    => 'DATE',
		);
	}
}

/**
 * Render Journal rows through the active theme's shared row component.
 *
 * @param \WP_Post[] $posts Journal posts.
 */
function render_rows( array $posts ): string {
	if ( ! function_exists( 'paginae_manuscripti_illuminati_render_journal_row' ) ) {
		return '';
	}

	$rows = '';

	foreach ( $posts as $post ) {
		if ( ! $post instanceof \WP_Post || ! \LigaturaManuscriptiIlluminati\Privacy\can_read_post( $post->ID ) ) {
			continue;
		}

		$rows .= paginae_manuscripti_illuminati_render_journal_row( $post->ID );
	}

	return $rows;
}

/**
 * Return Saga Topic choices used by readable Journals.
 *
 * @return array<string,string>
 */
function topic_choices(): array {
	$journal_ids = readable_post_ids( 'ligatura_diary' );

	if ( empty( $journal_ids ) ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'ligatura_saga_topic',
			'hide_empty' => true,
			'object_ids' => $journal_ids,
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
 * Return readable Character titles sorted alphabetically.
 *
 * @return array<int,string>
 */
function character_choices(): array {
	$choices = array();

	foreach ( readable_post_ids( 'ligatura_character' ) as $post_id ) {
		$choices[ $post_id ] = get_the_title( $post_id );
	}

	natcasesort( $choices );

	return $choices;
}

/**
 * Return IDs for readable entries of a campaign post type.
 *
 * @return int[]
 */
function readable_post_ids( string $post_type ): array {
	$query = new \WP_Query(
		array(
			'post_type'           => $post_type,
			'post_status'         => array( 'publish', 'private' ),
			'perm'                => 'readable',
			'posts_per_page'      => -1,
			'fields'              => 'ids',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'orderby'             => 'title',
			'order'               => 'ASC',
		)
	);

	return array_map( 'absint', $query->posts );
}

/**
 * Return the readable Persona represented by a collection slug.
 */
function persona_from_slug( string $slug ): ?\WP_Post {
	$slug    = sanitize_title( $slug );
	$persona = $slug ? get_page_by_path( $slug, OBJECT, 'ligatura_character' ) : null;

	if ( ! $persona instanceof \WP_Post || ! \LigaturaManuscriptiIlluminati\Privacy\can_read_post( $persona->ID ) ) {
		return null;
	}

	return $persona;
}

/**
 * Return the Persona for the current collection request.
 */
function current_persona(): ?\WP_Post {
	return persona_from_slug( (string) get_query_var( 'ligatura_journal_persona' ) );
}

/**
 * Return a canonical Persona-specific Commentarii collection URL.
 */
function persona_collection_url( \WP_Post $persona ): string {
	return home_url( user_trailingslashit( 'journals/persona/' . $persona->post_name ) );
}

/**
 * Return readable Personae that have at least one readable Journal.
 *
 * @return array<int,\WP_Post>
 */
function personae_with_journals(): array {
	$personae = array();

	foreach ( readable_post_ids( 'ligatura_diary' ) as $journal_id ) {
		$persona_id = absint( get_post_meta( $journal_id, 'ligatura_diary_character', true ) );

		if ( $persona_id < 1 || isset( $personae[ $persona_id ] ) || 'ligatura_character' !== get_post_type( $persona_id ) || ! \LigaturaManuscriptiIlluminati\Privacy\can_read_post( $persona_id ) ) {
			continue;
		}

		$persona = get_post( $persona_id );

		if ( $persona instanceof \WP_Post && '' !== $persona->post_name ) {
			$personae[ $persona_id ] = $persona;
		}
	}

	uasort(
		$personae,
		static function ( \WP_Post $left, \WP_Post $right ): int {
			return strnatcasecmp( get_the_title( $left ), get_the_title( $right ) );
		}
	);

	return $personae;
}

/**
 * Render the compact Persona collection directory on the main Commentarii Page.
 */
function render_persona_links(): string {
	$personae = personae_with_journals();

	if ( empty( $personae ) ) {
		return '';
	}

	$html = '<nav class="manuscriptum-illuminatum-commentarii-personae" aria-labelledby="commentarii-personae-heading">';
	$html .= '<h2 id="commentarii-personae-heading">' . esc_html__( 'Commentarii by Persona', 'ligatura-manuscripti-illuminati' ) . '</h2>';
	$html .= '<ul>';

	foreach ( $personae as $persona ) {
		$html .= '<li><a href="' . esc_url( persona_collection_url( $persona ) ) . '">' . esc_html( get_the_title( $persona ) ) . '</a></li>';
	}

	$html .= '</ul></nav>';

	return $html;
}

/**
 * Convert invalid or inaccessible Persona collection routes into a real 404.
 */
function maybe_404_invalid_persona_collection(): void {
	$slug = (string) get_query_var( 'ligatura_journal_persona' );

	if ( '' === $slug || persona_from_slug( $slug ) ) {
		return;
	}

	global $wp_query;

	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
