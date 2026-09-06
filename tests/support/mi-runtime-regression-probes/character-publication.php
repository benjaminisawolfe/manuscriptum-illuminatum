<?php
namespace LigaturaManuscriptiIlluminati\CharacterPublication;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const REST_NAMESPACE = 'ligatura-manuscripti-illuminati/v1';

/**
 * Register a guarded test probe for the non-REST wp_insert_post() regression.
 */
function register_persistence_probe_route(): void {
	if ( ! \LigaturaManuscriptiIlluminati\DemoContent\is_safe_environment() ) {
		return;
	}

	register_rest_route(
		REST_NAMESPACE,
		'/character-publication/persistence-probe',
		array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\\rest_run_persistence_probe',
			'permission_callback' => __NAMESPACE__ . '\\can_run_persistence_probe',
			'args'                => array(
				'term_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}

/**
 * Restrict the persistence probe to administrators on safe test environments.
 */
function can_run_persistence_probe(): bool {
	return current_user_can( 'manage_options' ) && \LigaturaManuscriptiIlluminati\DemoContent\is_safe_environment();
}

/**
 * Exercise wp_insert_post() as a caller that cannot assign campaign terms.
 *
 * The submitted valid tax_input passes the preliminary guard, while WordPress
 * intentionally skips persisting it because the simulated caller has no term
 * assignment capability. The shutdown invariant must then restore draft state.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response|\WP_Error
 */
function rest_run_persistence_probe( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
	$term_id = absint( $request['term_id'] );

	if ( ! term_exists( $term_id, TAXONOMY ) ) {
		return new \WP_Error( 'ligatura_invalid_character_type', __( 'The requested Character Type does not exist.', 'ligatura-manuscripti-illuminati' ), array( 'status' => 400 ) );
	}

	$caller_id = get_current_user_id();
	$title     = sprintf( 'Fixture persistence probe %s', gmdate( 'YmdHis' ) );

	try {
		wp_set_current_user( 0 );
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'ligatura_character',
					'post_status'  => 'publish',
					'post_author'  => $caller_id,
					'post_title'   => $title,
					'post_content' => 'Temporary persisted-taxonomy validation probe.',
					'tax_input'    => array( TAXONOMY => array( $term_id ) ),
				)
			),
			true
		);
	} finally {
		wp_set_current_user( $caller_id );
	}

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	return rest_ensure_response(
		array(
			'id'              => $post_id,
			'initial_status'  => get_post_status( $post_id ),
			'persisted_terms' => wp_get_object_terms( $post_id, TAXONOMY, array( 'fields' => 'ids' ) ),
		)
	);
}
