<?php
/**
 * REST route controls.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\Rest;

use LigaturaManuscriptiIlluminati\PrivateNotes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register REST routes and defensive filters.
 */
function register_routes(): void {
	register_rest_route(
		'ligatura-manuscripti-illuminati/v1',
		'/storyguide-notes/(?P<id>\d+)',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_storyguide_notes',
			'permission_callback' => __NAMESPACE__ . '\\can_read_storyguide_notes',
			'args'                => array(
				'id' => array(
					'validate_callback' => static fn( $value ): bool => absint( $value ) > 0,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);

	add_filter( 'rest_prepare_ligatura_wiki', __NAMESPACE__ . '\\remove_private_meta_from_response', 10, 3 );
}

/**
 * Permission check for private notes route.
 *
 * @param \WP_REST_Request $request Request object.
 */
function can_read_storyguide_notes( \WP_REST_Request $request ): bool {
	$post_id = absint( $request['id'] );

	return PrivateNotes\can_view_storyguide_notes() && current_user_can( 'read_post', $post_id );
}

/**
 * Return Storyguide notes through a permission-checked custom route.
 *
 * @param \WP_REST_Request $request Request object.
 */
function get_storyguide_notes( \WP_REST_Request $request ): \WP_REST_Response {
	$post_id = absint( $request['id'] );

	return new \WP_REST_Response(
		array(
			'id'    => $post_id,
			'notes' => PrivateNotes\get_storyguide_notes( $post_id ),
		)
	);
}

/**
 * Ensure private note meta never appears in default wiki REST responses.
 *
 * @param \WP_REST_Response $response REST response.
 */
function remove_private_meta_from_response( \WP_REST_Response $response ): \WP_REST_Response {
	$data = $response->get_data();

	if ( isset( $data['meta'][ PrivateNotes\META_KEY ] ) ) {
		unset( $data['meta'][ PrivateNotes\META_KEY ] );
		$response->set_data( $data );
	}

	return $response;
}
