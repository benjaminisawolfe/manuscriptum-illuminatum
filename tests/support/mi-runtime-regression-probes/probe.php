<?php
/**
 * Plugin Name: Manuscriptum Runtime Regression Probes
 * Description: Temporary administrator-only integration-test support. Never distribute with the product.
 * Version: 1.0
 */

namespace ManuscriptumRuntimeRegressionProbes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! function_exists( '\LigaturaManuscriptiIlluminati\DemoContent\is_safe_environment' )
			|| ! \LigaturaManuscriptiIlluminati\DemoContent\is_safe_environment() ) {
			return;
		}

		require_once __DIR__ . '/character-publication.php';
		require_once __DIR__ . '/player-access.php';
		require_once __DIR__ . '/meta-saving.php';
		require_once __DIR__ . '/role-migration.php';
		add_filter( 'upload_size_limit', static function ( int $bytes ): int {
			if ( '1024' !== ( $_SERVER['HTTP_X_MI_FIXTURE_UPLOAD_LIMIT'] ?? '' )
				|| ! preg_match( '#/characters/(\d+)/documents/upload#', (string) ( $_SERVER['REQUEST_URI'] ?? '' ), $matches ) ) {
				return $bytes;
			}
			$post = get_post( (int) $matches[1] );
			return $post && 'ligatura_character' === $post->post_type && str_starts_with( $post->post_title, 'Fixture ' ) && current_user_can( 'edit_post', $post->ID )
				? min( $bytes, 1024 ) : $bytes;
		} );
		// Exercise core classic editing only for administrator-owned test requests.
		add_filter( 'use_block_editor_for_post', static function ( bool $use_block_editor, \WP_Post $post ): bool {
			if ( current_user_can( 'manage_options' ) && isset( $_GET['mi_fixture_classic'] )
				&& '1' === $_GET['mi_fixture_classic'] && str_starts_with( $post->post_title, 'Fixture ' ) ) {
				return false;
			}
			return $use_block_editor;
		}, 10, 2 );
		add_action( 'rest_api_init', '\LigaturaManuscriptiIlluminati\CharacterPublication\register_persistence_probe_route' );
		add_action( 'rest_api_init', '\LigaturaManuscriptiIlluminati\PlayerAccess\register_rest_routes' );
		add_action( 'rest_api_init', '\ManuscriptumRuntimeRegressionProbes\MetaSaving\register_routes' );
		add_action( 'rest_api_init', '\ManuscriptumRuntimeRegressionProbes\Roles\register_routes' );
	}
);
