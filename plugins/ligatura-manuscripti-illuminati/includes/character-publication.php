<?php
/**
 * Character Type publication requirements.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\CharacterPublication;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TAXONOMY        = 'ligatura_character_type';
const REQUIRED_STATUS = array( 'publish', 'future' );
const ERROR_CODE      = 'ligatura_character_type_required';

/**
 * Store whether the standard REST controller already validated this save.
 */
function rest_request_validated( ?bool $new_value = null ): bool {
	static $validated = false;

	if ( null !== $new_value ) {
		$validated = $new_value;
	}

	return $validated;
}

/**
 * Return request-local Character IDs queued for a final relationship check.
 *
 * @return array<int,bool>
 */
function &queued_characters(): array {
	static $characters = array();

	return $characters;
}

/**
 * Track the corrective save so its own post-insert hooks cannot requeue it.
 */
function persisted_state_correction_in_progress( ?bool $new_value = null ): bool {
	static $in_progress = false;

	if ( null !== $new_value ) {
		$in_progress = $new_value;
	}

	return $in_progress;
}

/**
 * Return the clear validation message used across save paths.
 */
function validation_message(): string {
	return __( 'A Character Type is required before this Persona can be published.', 'ligatura-manuscripti-illuminati' );
}

/**
 * Determine whether a status requires a valid Character Type.
 */
function status_requires_character_type( string $status ): bool {
	return in_array( $status, REQUIRED_STATUS, true );
}

/**
 * Determine whether a saved Character has at least one valid type term.
 */
function character_has_valid_type( int $post_id ): bool {
	if ( $post_id < 1 || 'ligatura_character' !== get_post_type( $post_id ) ) {
		return false;
	}

	$term_ids = wp_get_object_terms( $post_id, TAXONOMY, array( 'fields' => 'ids' ) );

	return ! is_wp_error( $term_ids ) && ! empty( $term_ids );
}

/**
 * Determine whether submitted term values identify an existing Character Type.
 *
 * @param mixed $values Submitted IDs or names.
 */
function submitted_values_have_valid_type( mixed $values ): bool {
	if ( is_scalar( $values ) ) {
		$values = preg_split( '/\s*,\s*/', (string) $values, -1, PREG_SPLIT_NO_EMPTY );
	}

	if ( ! is_array( $values ) ) {
		return false;
	}

	foreach ( $values as $value ) {
		if ( ! is_scalar( $value ) ) {
			continue;
		}

		$candidate = sanitize_text_field( wp_unslash( (string) $value ) );

		if ( '' !== $candidate && term_exists( ctype_digit( $candidate ) ? absint( $candidate ) : $candidate, TAXONOMY ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Read an explicitly submitted Character Type from wp_insert_post() arguments.
 *
 * @param array<string,mixed> $postarr             Prepared post arguments.
 * @param array<string,mixed> $unsanitized_postarr Original post arguments.
 * @return bool|null True/false when submitted, null when no type input was supplied.
 */
function submitted_type_validity( array $postarr, array $unsanitized_postarr ): ?bool {
	foreach ( array( $unsanitized_postarr, $postarr ) as $source ) {
		if ( ! isset( $source['tax_input'] ) || ! is_array( $source['tax_input'] ) || ! array_key_exists( TAXONOMY, $source['tax_input'] ) ) {
			continue;
		}

		return submitted_values_have_valid_type( $source['tax_input'][ TAXONOMY ] );
	}

	return null;
}

/**
 * Preserve an unpublished state when a non-REST publication attempt is blocked.
 */
function fallback_status( string $old_status ): string {
	return in_array( $old_status, array( 'draft', 'pending', 'auto-draft' ), true ) ? $old_status : 'draft';
}

/**
 * Remember an editor-facing failure across the normal WordPress redirect.
 */
function record_validation_failure(): void {
	$user_id = get_current_user_id();

	if ( $user_id > 0 ) {
		set_transient( ERROR_CODE . '_' . $user_id, 1, MINUTE_IN_SECONDS );
	}
}

/**
 * Enforce the type requirement for Classic Editor, Quick/Bulk Edit, CLI, and wp_insert_post().
 *
 * @param array<string,mixed> $data                Slashed, sanitized post data.
 * @param array<string,mixed> $postarr             Prepared post arguments.
 * @param array<string,mixed> $unsanitized_postarr Original post arguments.
 */
function enforce_insert_post_data( array $data, array $postarr, array $unsanitized_postarr ): array {
	if ( 'ligatura_character' !== ( $data['post_type'] ?? '' ) || ! status_requires_character_type( (string) ( $data['post_status'] ?? '' ) ) ) {
		return $data;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST && rest_request_validated() ) {
		rest_request_validated( false );
		return $data;
	}

	$post_id           = absint( $postarr['ID'] ?? 0 );
	$existing_valid    = character_has_valid_type( $post_id );
	$submitted_valid   = submitted_type_validity( $postarr, $unsanitized_postarr );
	$has_valid_type    = null === $submitted_valid ? $existing_valid : $submitted_valid;
	$old_status        = $post_id ? (string) get_post_status( $post_id ) : '';

	if ( $has_valid_type ) {
		return $data;
	}

	$data['post_status'] = fallback_status( $old_status );
	record_validation_failure();

	return $data;
}

/**
 * Reject invalid publication through the standard WordPress REST controller.
 *
 * @param mixed            $prepared_post Prepared post object or existing error.
 * @param \WP_REST_Request $request       REST request.
 * @return mixed
 */
function validate_rest_publication( mixed $prepared_post, \WP_REST_Request $request ): mixed {
	if ( is_wp_error( $prepared_post ) ) {
		return $prepared_post;
	}

	$post_id    = absint( $request['id'] ?? 0 );
	$old_status = $post_id ? (string) get_post_status( $post_id ) : '';
	$status     = $request->has_param( 'status' ) ? sanitize_key( (string) $request['status'] ) : $old_status;

	if ( ! status_requires_character_type( $status ) ) {
		rest_request_validated( true );
		return $prepared_post;
	}

	$existing_valid = character_has_valid_type( $post_id );
	$explicit_terms = $request->has_param( TAXONOMY );
	$has_valid_type = $explicit_terms
		? submitted_values_have_valid_type( $request->get_param( TAXONOMY ) )
		: $existing_valid;

	if ( ! $has_valid_type ) {
		if ( $post_id > 0 && ! $existing_valid && status_requires_character_type( $old_status ) && $old_status === $status ) {
			rest_request_validated( true );
			return $prepared_post;
		}

		return new \WP_Error( ERROR_CODE, validation_message(), array( 'status' => 400 ) );
	}

	rest_request_validated( true );

	return $prepared_post;
}

/**
 * Queue every newly public/scheduled Persona for a persisted-taxonomy recheck.
 *
 * wp_after_insert_post runs after core has attempted its tax_input assignments.
 * The actual check remains deferred until shutdown so REST and other callers may
 * finish assigning relationships after wp_insert_post() returns.
 *
 * @param int           $post_id    Post ID.
 * @param \WP_Post      $post       Inserted/updated post.
 * @param bool          $update     Whether this was an update.
 * @param \WP_Post|null $post_before Post before the update, when available.
 */
function queue_publication_transition_check( int $post_id, \WP_Post $post, bool $update, ?\WP_Post $post_before ): void {
	unset( $update );

	if ( persisted_state_correction_in_progress()
		|| 'ligatura_character' !== $post->post_type
		|| ! status_requires_character_type( $post->post_status )
	) {
		return;
	}

	$was_public = $post_before instanceof \WP_Post && status_requires_character_type( $post_before->post_status );

	if ( $was_public ) {
		return;
	}

	$queued_characters = &queued_characters();
	$queued_characters[ $post_id ] = true;
}

/**
 * Queue a Character Type relationship change for an end-of-request invariant check.
 *
 * @param int    $object_id Object ID.
 * @param mixed  $terms     Submitted terms.
 * @param mixed  $tt_ids    Term-taxonomy IDs.
 * @param string $taxonomy  Taxonomy name.
 */
function queue_type_relationship_check( int $object_id, mixed $terms, mixed $tt_ids, string $taxonomy ): void {
	unset( $terms, $tt_ids );

	if ( TAXONOMY === $taxonomy && 'ligatura_character' === get_post_type( $object_id ) ) {
		$queued_characters = &queued_characters();
		$queued_characters[ $object_id ] = true;
	}
}

/**
 * Queue a direct Character Type relationship removal for the same final check.
 *
 * @param int    $object_id Object ID.
 * @param mixed  $tt_ids    Removed term-taxonomy IDs.
 * @param string $taxonomy  Taxonomy name.
 */
function queue_deleted_type_relationship_check( int $object_id, mixed $tt_ids, string $taxonomy ): void {
	queue_type_relationship_check( $object_id, array(), $tt_ids, $taxonomy );
}

/**
 * Enforce the invariant after all taxonomy replacements/removals finish.
 */
function enforce_queued_relationship_changes(): void {
	$queued_characters = &queued_characters();
	$post_ids          = array_keys( $queued_characters );
	$queued_characters = array();

	if ( empty( $post_ids ) || persisted_state_correction_in_progress() ) {
		return;
	}

	persisted_state_correction_in_progress( true );

	try {
		foreach ( $post_ids as $post_id ) {
			if ( ! status_requires_character_type( (string) get_post_status( $post_id ) ) || character_has_valid_type( $post_id ) ) {
				continue;
			}

			$result = wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'draft',
				),
				true
			);

			if ( is_wp_error( $result ) ) {
				error_log( sprintf( '[Ligatura Manuscripti Illuminati] Unable to return untyped Persona %d to draft: %s', $post_id, $result->get_error_message() ) );
				continue;
			}

			record_validation_failure();
			error_log( sprintf( '[Ligatura Manuscripti Illuminati] Persona %d was returned to draft because its submitted Character Type relationship did not persist.', $post_id ) );
			do_action( 'ligatura_character_publication_validation_failed', $post_id, validation_message() );
		}
	} finally {
		persisted_state_correction_in_progress( false );
	}
}

/**
 * Show validation warnings on Character admin screens.
 */
function render_admin_notices(): void {
	$screen = get_current_screen();

	if ( ! $screen || 'ligatura_character' !== $screen->post_type || ! current_user_can( 'edit_ligatura_characters' ) ) {
		return;
	}

	$user_id = get_current_user_id();

	if ( $user_id > 0 && get_transient( ERROR_CODE . '_' . $user_id ) ) {
		delete_transient( ERROR_CODE . '_' . $user_id );
		echo '<div class="notice notice-error"><p>' . esc_html( validation_message() ) . '</p></div>';
	}
}
