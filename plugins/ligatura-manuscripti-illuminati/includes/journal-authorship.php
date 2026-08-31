<?php
/**
 * Character-authored Journal validation and helpers.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\JournalAuthorship;

use LigaturaManuscriptiIlluminati\PlayerAccess;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META_KEY           = 'ligatura_diary_character';
const ERROR_CODE         = 'ligatura_character_author_required';
const FORBIDDEN_CODE     = 'ligatura_character_author_forbidden';
const PUBLIC_STATUSES    = array( 'publish', 'future' );

/**
 * Return a valid Character Author ID, or zero for unassigned Journals.
 */
function get_character_id( int $journal_id ): int {
	$character_id = absint( get_post_meta( $journal_id, META_KEY, true ) );

	return $character_id > 0 && 'ligatura_character' === get_post_type( $character_id ) ? $character_id : 0;
}

/**
 * Return the visible in-world Journal author name.
 */
function get_character_name( int $journal_id ): string {
	$character_id = get_character_id( $journal_id );

	return $character_id > 0 ? get_the_title( $character_id ) : '';
}

/**
 * Return the visible author's Campaign Image attachment ID.
 */
function get_character_image_id( int $journal_id ): int {
	$character_id = get_character_id( $journal_id );

	return $character_id > 0 ? get_post_thumbnail_id( $character_id ) : 0;
}

/**
 * Prevent WordPress's author-{user_nicename} CSS class from leaking technical ownership.
 *
 * @param string[] $classes Generated post classes.
 * @return string[]
 */
function remove_technical_author_post_class( array $classes, array $extra_classes, int $post_id ): array {
	unset( $extra_classes );

	if ( 'ligatura_diary' !== get_post_type( $post_id ) ) {
		return $classes;
	}

	return array_values(
		array_filter(
			$classes,
			static fn( string $class_name ): bool => ! str_starts_with( $class_name, 'author-' )
		)
	);
}

/**
 * Whether a user may select a Persona as Character Author.
 */
function user_can_author_as_character( int $user_id, int $character_id ): bool {
	if ( $user_id < 1 || $character_id < 1 || 'ligatura_character' !== get_post_type( $character_id ) ) {
		return false;
	}

	if ( PlayerAccess\is_restricted_player( $user_id ) ) {
		return PlayerAccess\user_is_assigned_to_character( $user_id, $character_id );
	}

	return user_can( $user_id, 'read_post', $character_id );
}

/**
 * Return assigned Persona IDs for a Player, stopping after the requested limit.
 *
 * @return int[]
 */
function get_assigned_character_ids( int $user_id, int $limit = 2 ): array {
	if ( ! PlayerAccess\is_restricted_player( $user_id ) ) {
		return array();
	}

	$character_ids = get_posts(
		array(
			'post_type'      => 'ligatura_character',
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
			'posts_per_page' => max( 1, $limit ),
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_key'       => PlayerAccess\ASSIGNED_PLAYER_META,
			'meta_value'     => $user_id,
			'no_found_rows'  => true,
		)
	);

	return array_values(
		array_filter(
			array_map( 'absint', $character_ids ),
			static fn( int $character_id ): bool => PlayerAccess\user_is_assigned_to_character( $user_id, $character_id )
		)
	);
}

/**
 * Return the sole Persona assigned to a Player, or zero for none/multiple.
 */
function get_single_assigned_character_id( int $user_id ): int {
	$character_ids = get_assigned_character_ids( $user_id, 2 );

	return 1 === count( $character_ids ) ? $character_ids[0] : 0;
}

/**
 * Clear publication error shown across REST and ordinary editor flows.
 */
function validation_message( int $user_id = 0 ): string {
	$user_id = $user_id ?: get_current_user_id();

	if ( PlayerAccess\is_restricted_player( $user_id ) && empty( get_assigned_character_ids( $user_id, 1 ) ) ) {
		return __( 'You need an assigned Persona before publishing Commentarii.', 'ligatura-manuscripti-illuminati' );
	}

	return __( 'Choose a Character Author assigned to you before publishing this Journal.', 'ligatura-manuscripti-illuminati' );
}

/**
 * Clear forged-selection message.
 */
function forbidden_message(): string {
	return __( 'You cannot use that Persona as the Character Author.', 'ligatura-manuscripti-illuminati' );
}

/**
 * Determine whether a requested Journal status needs Character Author validation.
 */
function status_requires_character_author( string $status ): bool {
	return in_array( $status, PUBLIC_STATUSES, true );
}

/**
 * Preserve a safe unpublished state after a blocked ordinary save.
 */
function fallback_status( string $old_status ): string {
	return in_array( $old_status, array( 'draft', 'pending', 'auto-draft' ), true ) ? $old_status : 'draft';
}

/**
 * Remember an editor-facing validation failure across redirect-based saves.
 */
function record_validation_failure( int $user_id = 0 ): void {
	$user_id = $user_id ?: get_current_user_id();

	if ( $user_id > 0 ) {
		set_transient( ERROR_CODE . '_' . $user_id, 1, MINUTE_IN_SECONDS );
	}
}

/**
 * Read a Character Author explicitly submitted through supported save shapes.
 *
 * @param array<string,mixed> $postarr Prepared or unsanitized post arguments.
 * @return int|null Null when no Character Author was submitted.
 */
function submitted_character_id( array $postarr ): ?int {
	if ( isset( $postarr['meta_input'] ) && is_array( $postarr['meta_input'] ) && array_key_exists( META_KEY, $postarr['meta_input'] ) ) {
		return absint( $postarr['meta_input'][ META_KEY ] );
	}

	if ( array_key_exists( META_KEY, $postarr ) ) {
		return absint( $postarr[ META_KEY ] );
	}

	if ( isset( $_POST[ META_KEY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Validation only; the meta save performs nonce verification.
		return absint( $_POST[ META_KEY ] );
	}

	return null;
}

/**
 * Persist a valid submitted author or the safe sole-assigned-Persona default.
 *
 * Returns zero when no valid choice can be resolved. A forged non-zero
 * submitted value is never replaced with a fallback.
 */
function persist_character_author( int $user_id, int $post_id, ?int $submitted ): int {
	if ( null !== $submitted && $submitted > 0 ) {
		if ( ! user_can_author_as_character( $user_id, $submitted ) ) {
			return 0;
		}

		$character_id = $submitted;
	} elseif ( null === $submitted && $post_id > 0 && user_can_author_as_character( $user_id, get_character_id( $post_id ) ) ) {
		$character_id = get_character_id( $post_id );
	} else {
		$character_id = get_single_assigned_character_id( $user_id );
	}

	if ( $character_id < 1 ) {
		return 0;
	}

	if ( $post_id > 0 ) {
		update_post_meta( $post_id, META_KEY, $character_id );

		return get_character_id( $post_id );
	}

	return $character_id;
}

/**
 * Prevent technical-author changes and block invalid Player publication paths.
 *
 * @param array<string,mixed> $data                Slashed, sanitized post data.
 * @param array<string,mixed> $postarr             Prepared post arguments.
 * @param array<string,mixed> $unsanitized_postarr Original post arguments.
 * @return array<string,mixed>
 */
function enforce_insert_post_data( array $data, array $postarr, array $unsanitized_postarr ): array {
	if ( 'ligatura_diary' !== ( $data['post_type'] ?? '' ) || ! PlayerAccess\is_restricted_player() ) {
		return $data;
	}

	$user_id   = get_current_user_id();
	$post_id   = absint( $postarr['ID'] ?? $unsanitized_postarr['ID'] ?? 0 );
	$old_post  = $post_id > 0 ? get_post( $post_id ) : null;
	$old_status = $old_post instanceof \WP_Post ? $old_post->post_status : '';

	$data['post_author'] = $old_post instanceof \WP_Post ? (int) $old_post->post_author : $user_id;

	$submitted = submitted_character_id( $unsanitized_postarr );

	if ( null === $submitted ) {
		$submitted = submitted_character_id( $postarr );
	}

	if ( null !== $submitted && $submitted > 0 && ! user_can_author_as_character( $user_id, $submitted ) ) {
		if ( status_requires_character_author( (string) ( $data['post_status'] ?? '' ) ) ) {
			$data['post_status'] = fallback_status( $old_status );
			record_validation_failure();
		}

		return $data;
	}

	$character_id = persist_character_author( $user_id, $post_id, $submitted );

	if ( $character_id > 0 && $post_id < 1 ) {
		$_POST[ META_KEY ] = (string) $character_id; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Server-derived value consumed by the existing nonce-protected meta save.
	}

	if ( ! status_requires_character_author( (string) ( $data['post_status'] ?? '' ) ) ) {
		return $data;
	}

	if ( ! user_can_author_as_character( $user_id, $character_id ) ) {
		$data['post_status'] = fallback_status( $old_status );
		record_validation_failure();
	}

	return $data;
}

/**
 * Reject forged/missing Character Authors through the standard REST controller.
 *
 * @param mixed            $prepared_post Prepared Journal or existing error.
 * @param \WP_REST_Request $request       REST request.
 * @return mixed
 */
function validate_rest_save( mixed $prepared_post, \WP_REST_Request $request ): mixed {
	if ( is_wp_error( $prepared_post ) || ! PlayerAccess\is_restricted_player() ) {
		return $prepared_post;
	}

	$user_id  = get_current_user_id();
	$post_id  = absint( $request['id'] ?? 0 );
	$old_post = $post_id > 0 ? get_post( $post_id ) : null;
	$meta     = $request->get_param( 'meta' );
	$explicit = is_array( $meta ) && array_key_exists( META_KEY, $meta );
	$selected = $explicit ? absint( $meta[ META_KEY ] ) : null;

	if ( $prepared_post instanceof \WP_Post ) {
		$prepared_post->post_author = $old_post instanceof \WP_Post ? (int) $old_post->post_author : $user_id;
	}

	if ( $explicit && $selected > 0 && ! user_can_author_as_character( $user_id, $selected ) ) {
		return new \WP_Error( FORBIDDEN_CODE, forbidden_message(), array( 'status' => 403 ) );
	}

	$selected = persist_character_author( $user_id, $post_id, $selected );

	if ( $selected > 0 ) {
		$meta             = is_array( $meta ) ? $meta : array();
		$meta[ META_KEY ] = $selected;
		$request->set_param( 'meta', $meta );
	}

	$old_status = $old_post instanceof \WP_Post ? $old_post->post_status : '';
	$status     = $request->has_param( 'status' ) ? sanitize_key( (string) $request['status'] ) : $old_status;

	if ( status_requires_character_author( $status ) && ! user_can_author_as_character( $user_id, $selected ) ) {
		return new \WP_Error( ERROR_CODE, validation_message( $user_id ), array( 'status' => 400 ) );
	}

	return $prepared_post;
}

/**
 * Re-read the persisted Character Author after ordinary meta-box saving.
 */
function validate_persisted_author_after_save( int $post_id, \WP_Post $post ): void {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	validate_persisted_author( $post_id, $post );
}

/**
 * Re-read the persisted Character Author after REST additional fields save.
 */
function validate_persisted_author_after_rest_insert( \WP_Post $post ): void {
	validate_persisted_author( $post->ID, $post );
}

/**
 * Keep a Player-owned Journal unpublished unless its saved Persona is valid.
 */
function validate_persisted_author( int $post_id, \WP_Post $post ): void {
	static $correcting = false;

	if ( $correcting
		|| 'ligatura_diary' !== $post->post_type
		|| ! status_requires_character_author( $post->post_status )
		|| ! PlayerAccess\is_restricted_player( (int) $post->post_author )
		|| user_can_author_as_character( (int) $post->post_author, get_character_id( $post_id ) )
	) {
		return;
	}

	$correcting = true;

	try {
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);
		record_validation_failure( (int) $post->post_author );
	} finally {
		$correcting = false;
	}
}

/**
 * Recheck scheduled Player Journals at cron publication time.
 */
function validate_scheduled_publication( string $new_status, string $old_status, \WP_Post $post ): void {
	static $correcting = false;

	if ( $correcting || 'ligatura_diary' !== $post->post_type || 'future' !== $old_status || 'publish' !== $new_status ) {
		return;
	}

	$author_id = (int) $post->post_author;

	if ( ! PlayerAccess\is_restricted_player( $author_id ) || user_can_author_as_character( $author_id, get_character_id( $post->ID ) ) ) {
		return;
	}

	$correcting = true;

	try {
		wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => 'draft',
			)
		);
		record_validation_failure( $author_id );
	} finally {
		$correcting = false;
	}
}

/**
 * Show a clear Player-facing publication error.
 */
function render_admin_notice(): void {
	if ( ! PlayerAccess\is_restricted_player() ) {
		return;
	}

	$user_id = get_current_user_id();

	if ( $user_id > 0 && get_transient( ERROR_CODE . '_' . $user_id ) ) {
		delete_transient( ERROR_CODE . '_' . $user_id );
		echo '<div class="notice notice-error"><p>' . esc_html( validation_message() ) . '</p></div>';
	}
}
