<?php
namespace LigaturaManuscriptiIlluminati\PlayerAccess;

use LigaturaManuscriptiIlluminati\DemoContent;
use LigaturaManuscriptiIlluminati\RolesCapabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const REST_NAMESPACE = 'ligatura-manuscripti-illuminati/v1';

/**
 * Register guarded diagnostics for integration tests.
 */
function register_rest_routes(): void {
	if ( ! DemoContent\is_safe_environment() ) {
		return;
	}

	register_rest_route(
		REST_NAMESPACE,
		'/player-access/status',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\rest_status',
			'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
		)
	);

	register_rest_route(
		REST_NAMESPACE,
		'/player-access/bootstrap-probe',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\rest_bootstrap_probe',
			'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
			'args'                => array(
				'player_id'               => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				'assigned_character_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				'other_character_id'    => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				'own_journal_id'        => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				'other_journal_id'      => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				'publish_character_id'  => array( 'required' => false, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
			),
		)
	);
}

/**
 * Return role state without exposing user identity details.
 */
function rest_status(): \WP_REST_Response {
	$role       = get_role( RolesCapabilities\PLAYER_ROLE );
	$role_names = wp_roles()->role_names;
	$role_name  = isset( $role_names[ RolesCapabilities\PLAYER_ROLE ] ) ? translate_user_role( $role_names[ RolesCapabilities\PLAYER_ROLE ] ) : '';

	return rest_ensure_response(
		array(
			'role'            => RolesCapabilities\PLAYER_ROLE,
			'role_name'       => $role_name,
			'capabilities'    => $role ? array_keys( array_filter( $role->capabilities ) ) : array(),
			'role_version'    => get_option( RolesCapabilities\ROLE_VERSION_OPTION ),
			'assignment_meta' => ASSIGNED_PLAYER_META,
			'default_admin'   => default_administrator_id(),
		)
	);
}

/**
 * Exercise repeated Player capability decisions in a genuinely authenticated
 * request and report bounded request/query memory observations.
 */
function rest_bootstrap_probe( \WP_REST_Request $request ): \WP_REST_Response {
	global $wpdb;

	$player_id             = absint( $request['player_id'] );
	$assigned_character_id = absint( $request['assigned_character_id'] );
	$other_character_id    = absint( $request['other_character_id'] );
	$own_journal_id        = absint( $request['own_journal_id'] );
	$other_journal_id      = absint( $request['other_journal_id'] );
	$publish_character_id  = absint( $request['publish_character_id'] ?? 0 );
	$memory_before         = memory_get_usage( true );
	$queries_before        = (int) $wpdb->num_queries;
	$capabilities          = array();
	$rest_results          = array();
	$admin_routing         = array();
	$original_user_id      = get_current_user_id();

	if ( ! is_player( $player_id ) ) {
		return new \WP_REST_Response( array( 'code' => 'ligatura_player_probe_invalid_user' ), 400 );
	}

	wp_set_current_user( $player_id );

	try {
		for ( $iteration = 0; $iteration < 25; ++$iteration ) {
			$capabilities = array(
				'read'                   => current_user_can( 'read' ),
				'upload_files'           => current_user_can( 'upload_files' ),
				'create_journal'         => current_user_can( 'create_ligatura_diary_entries' ),
				'publish_journal'        => current_user_can( 'publish_ligatura_diary_entries' ),
				'create_persona'         => current_user_can( 'create_ligatura_characters' ),
				'edit_assigned_persona'  => current_user_can( 'edit_post', $assigned_character_id ),
				'delete_assigned_persona' => current_user_can( 'delete_post', $assigned_character_id ),
				'edit_other_persona'     => current_user_can( 'edit_post', $other_character_id ),
				'edit_own_journal'       => current_user_can( 'edit_post', $own_journal_id ),
				'edit_other_journal'     => current_user_can( 'edit_post', $other_journal_id ),
				'manage_options'         => current_user_can( 'manage_options' ),
				'assign_persona_players' => current_user_can( 'ligatura_assign_persona_players' ),
			);
		}

		$rest_results = array(
			'character_collection' => rest_probe_response_data(
				'/wp/v2/ligatura_character',
				array( 'context' => 'edit', 'per_page' => 100 )
			),
			'assigned_persona'     => rest_probe_response_data( '/wp/v2/ligatura_character/' . $assigned_character_id ),
			'other_persona'        => rest_probe_response_data( '/wp/v2/ligatura_character/' . $other_character_id ),
			'own_journal'          => rest_probe_response_data( '/wp/v2/ligatura_diary/' . $own_journal_id ),
			'other_journal'        => rest_probe_response_data( '/wp/v2/ligatura_diary/' . $other_journal_id ),
			'media_collection'     => rest_probe_response_data( '/wp/v2/media', array( 'context' => 'view', 'per_page' => 100 ) ),
			'publish_own_journal' => rest_probe_write_data(
				'/wp/v2/ligatura_diary/' . $own_journal_id,
				array_merge(
					array( 'status' => 'publish' ),
					$publish_character_id > 0 ? array( 'meta' => array( 'ligatura_diary_character' => $publish_character_id ) ) : array()
				)
			),
			'forge_character_author' => rest_probe_write_data(
				'/wp/v2/ligatura_diary/' . $own_journal_id,
				array(
					'status' => 'publish',
					'meta'   => array( 'ligatura_diary_character' => $other_character_id ),
				)
			),
			'update_assigned_persona' => rest_probe_write_data(
				'/wp/v2/ligatura_character/' . $assigned_character_id,
				array( 'meta' => array( 'ligatura_occupation' => 'Updated by assigned Player probe' ) )
			),
			'forge_persona_assignment' => rest_probe_write_data(
				'/wp/v2/ligatura_character/' . $assigned_character_id,
				array( 'meta' => array( ASSIGNED_PLAYER_META => get_assigned_player_id( $other_character_id ) ) )
			),
			'delete_assigned_persona' => rest_probe_write_data(
				'/wp/v2/ligatura_character/' . $assigned_character_id,
				array( 'force' => true ),
				'DELETE'
			),
			'write_other_journal' => rest_probe_write_data(
				'/wp/v2/ligatura_diary/' . $other_journal_id,
				array( 'content' => '<p>Forbidden Player probe.</p>' )
			),
		);
		$admin_routing = array(
			'dashboard'             => player_admin_screen_decision( 'index.php' ),
			'landing'               => player_admin_screen_decision( 'edit.php', 'ligatura_diary' ),
			'forbidden'             => player_admin_screen_decision( 'options-general.php' ),
			'assigned_persona_edit' => player_admin_screen_decision( 'post.php', '', $assigned_character_id ),
			'other_persona_edit'    => player_admin_screen_decision( 'post.php', '', $other_character_id ),
		);

		$character_admin_query = new \WP_Query();
		$character_admin_query->set( 'author', $player_id );
		apply_player_character_query_scope( $character_admin_query, $player_id );
		$admin_routing['character_query'] = array(
			'author'     => (string) $character_admin_query->get( 'author' ),
			'meta_key'   => (string) $character_admin_query->get( 'meta_key' ),
			'meta_value' => absint( $character_admin_query->get( 'meta_value' ) ),
		);
	} finally {
		wp_set_current_user( $original_user_id );
	}

	return rest_ensure_response(
		array(
			'authenticated'       => $player_id > 0,
			'player'              => is_player( $player_id ),
			'restricted_player'   => is_restricted_player( $player_id ),
			'capabilities'        => $capabilities,
			'rest'                => $rest_results,
			'admin_routing'       => $admin_routing,
			'iterations'          => 25,
			'query_count'         => (int) $wpdb->num_queries - $queries_before,
			'memory_growth_bytes' => max( 0, memory_get_usage( true ) - $memory_before ),
			'memory_peak_bytes'   => memory_get_peak_usage( true ),
			'memory_limit_bytes'  => wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) ),
		)
	);
}

/**
 * Dispatch an edit-context REST read while the diagnostic Player is current.
 *
 * @param array<string,mixed> $parameters Request query parameters.
 * @return array{status:int,ids:int[],authors:int[]}
 */
function rest_probe_response_data( string $route, array $parameters = array() ): array {
	$request = new \WP_REST_Request( 'GET', $route );
	$request->set_query_params( array_merge( array( 'context' => 'edit' ), $parameters ) );
	$response = rest_do_request( $request );
	$data     = $response->get_data();
	$items    = is_array( $data ) && array_is_list( $data ) ? $data : array();

	return array(
		'status'  => $response->get_status(),
		'ids'     => array_values( array_filter( array_map( static fn( mixed $item ): int => is_array( $item ) ? absint( $item['id'] ?? 0 ) : 0, $items ) ) ),
		'authors' => array_values( array_unique( array_filter( array_map( static fn( mixed $item ): int => is_array( $item ) ? absint( $item['author'] ?? 0 ) : 0, $items ) ) ) ),
	);
}

/**
 * Dispatch a REST write while the diagnostic Player is current.
 *
 * @param array<string,mixed> $body Request body parameters.
 * @return array{status:int,author:int,character_author:int,occupation:string,code:string,message:string}
 */
function rest_probe_write_data( string $route, array $body, string $method = 'POST' ): array {
	$request = new \WP_REST_Request( $method, $route );
	$request->set_body_params( $body );
	$response = rest_do_request( $request );
	$data     = $response->get_data();

	return array(
		'status'           => $response->get_status(),
		'author'           => is_array( $data ) ? absint( $data['author'] ?? 0 ) : 0,
		'character_author' => is_array( $data ) ? absint( $data['meta']['ligatura_diary_character'] ?? 0 ) : 0,
		'occupation'       => is_array( $data ) ? sanitize_text_field( (string) ( $data['meta']['ligatura_occupation'] ?? '' ) ) : '',
		'code'             => is_array( $data ) ? sanitize_key( (string) ( $data['code'] ?? '' ) ) : '',
		'message'          => is_array( $data ) ? sanitize_text_field( (string) ( $data['message'] ?? '' ) ) : '',
	);
}
