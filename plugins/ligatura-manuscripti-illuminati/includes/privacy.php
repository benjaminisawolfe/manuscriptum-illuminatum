<?php
/**
 * Login-only privacy enforcement.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether this installation requires authentication for campaign content.
 *
 * The site is private by default. Production may opt into public access from
 * wp-config.php with `define( 'LIGATURA_MANUSCRIPTI_ILLUMINATI_REQUIRE_LOGIN', false );`.
 */
function login_required(): bool {
	$required = defined( 'LIGATURA_MANUSCRIPTI_ILLUMINATI_REQUIRE_LOGIN' ) ? (bool) LIGATURA_MANUSCRIPTI_ILLUMINATI_REQUIRE_LOGIN : true;

	/**
	 * Filters whether front-end, feed, and REST campaign access requires login.
	 *
	 * @param bool $required Whether authentication is required.
	 */
	return (bool) apply_filters( 'ligatura_manuscripti_illuminati_require_login', $required );
}

/**
 * Determine whether the current visitor may see an entry in front-end listings.
 *
 * Anonymous visitors have no read capability, even for published posts. Honor
 * the site's explicit public mode without granting access to private content.
 */
function can_read_post( int $post_id ): bool {
	$post = get_post( $post_id );

	if ( ! $post instanceof \WP_Post ) {
		return false;
	}

	if ( is_user_logged_in() ) {
		return current_user_can( 'read_post', $post_id );
	}

	return ! login_required() && is_post_publicly_viewable( $post ) && ! post_password_required( $post );
}

/**
 * Whether the current request targets a WordPress feed endpoint.
 *
 * The path fallback covers installations where custom root rewrites prevent
 * WordPress from setting its feed query flag before the privacy gate runs.
 */
function is_feed_request(): bool {
	if ( is_feed() || '' !== (string) get_query_var( 'feed' ) ) {
		return true;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

	return 1 === preg_match( '#(?:^|/)feed(?:/|$)#', $path );
}

/**
 * Site-wide front-end login gate.
 */
function enforce_login_only(): void {
	if ( ! login_required() || is_user_logged_in() ) {
		return;
	}

	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	if ( is_feed_request() ) {
		block_guest_feeds();
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : home_url( '/' );
	$redirect    = wp_login_url( home_url( $request_uri ) );

	wp_safe_redirect( $redirect );
	exit;
}

/**
 * Prevent the REST API from bypassing the login-only site boundary.
 *
 * @param mixed $result Existing authentication result.
 * @return mixed
 */
function require_rest_login( mixed $result ): mixed {
	if ( ! login_required() || ! empty( $result ) || is_user_logged_in() ) {
		return $result;
	}

	return new \WP_Error(
		'ligatura_rest_authentication_required',
		__( 'Campaign REST data is available only to logged-in users.', 'ligatura-manuscripti-illuminati' ),
		array( 'status' => 401 )
	);
}

/**
 * Prevent unauthenticated feed access.
 */
function block_guest_feeds(): void {
	if ( ! login_required() || is_user_logged_in() ) {
		return;
	}

	status_header( 403 );
	wp_die(
		esc_html__( 'Campaign feeds are available only to logged-in users.', 'ligatura-manuscripti-illuminati' ),
		esc_html__( 'Private Campaign Feed', 'ligatura-manuscripti-illuminati' ),
		array( 'response' => 403 )
	);
}
