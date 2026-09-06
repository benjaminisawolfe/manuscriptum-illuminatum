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
		add_action( 'rest_api_init', '\LigaturaManuscriptiIlluminati\CharacterPublication\register_persistence_probe_route' );
		add_action( 'rest_api_init', '\LigaturaManuscriptiIlluminati\PlayerAccess\register_rest_routes' );
	}
);
