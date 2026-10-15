<?php
namespace ManuscriptumRuntimeRegressionProbes\MetaSaving;

use LigaturaManuscriptiIlluminati\CharacterFields;
use LigaturaManuscriptiIlluminati\PrivateNotes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function register_routes(): void {
	register_rest_route(
		'ligatura-manuscripti-illuminati/v1',
		'/meta-saving/stored',
		array(
			'methods' => 'GET',
			'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
			'args' => array( 'post_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ) ),
			'callback' => static function ( \WP_REST_Request $request ) {
				$post = get_post( $request['post_id'] );
				if ( ! $post || ! str_starts_with( $post->post_title, 'Fixture ' ) ) {
					return new \WP_Error( 'fixture_required', 'Only disposable fixture records may be inspected.', array( 'status' => 403 ) );
				}
				$fields = match ( $post->post_type ) {
					'ligatura_character' => CharacterFields\get_character_fields(),
					'ligatura_diary' => CharacterFields\get_diary_fields(),
					'ligatura_covenant' => CharacterFields\get_covenant_fields(),
					'ligatura_wiki' => array_merge( CharacterFields\get_wiki_fields(), array( array( 'key' => PrivateNotes\META_KEY ) ) ),
					default => array(),
				};
				$meta = array();
				foreach ( $fields as $field ) {
					$meta[ $field['key'] ] = get_post_meta( $post->ID, $field['key'], true );
				}
				return rest_ensure_response( array( 'meta' => $meta ) );
			},
		)
	);
}
