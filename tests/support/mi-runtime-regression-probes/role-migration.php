<?php
/** Temporary administrator-only role migration integration fixtures. */
namespace ManuscriptumRuntimeRegressionProbes\Roles;

use LigaturaManuscriptiIlluminati\RolesCapabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function register_routes(): void {
	foreach ( array( '/roles/inventory' => 'inventory', '/roles/backup' => 'backup', '/roles/migration-probe' => 'migration_probe' ) as $route => $callback ) {
		register_rest_route(
			'ligatura-manuscripti-illuminati/v1',
			$route,
			array(
				'methods'             => 'migration_probe' === $callback ? 'POST' : 'GET',
				'callback'            => __NAMESPACE__ . '\\' . $callback,
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
			)
		);
	}
}

/** Private rollback snapshot. Save locally in ignored storage; never print it. */
function backup(): \WP_REST_Response {
	global $wpdb;
	$site_id = get_current_blog_id();
	$cap_key = $wpdb->get_blog_prefix( $site_id ) . 'capabilities';
	$level_key = $wpdb->get_blog_prefix( $site_id ) . 'user_level';
	$users = array();
	foreach ( get_users( array( 'role__in' => array( 'ars_player', 'ars_storyguide' ), 'fields' => 'ID', 'blog_id' => $site_id ) ) as $id ) {
		$users[] = array(
			'id' => (int) $id,
			'capabilities' => get_user_meta( $id, $cap_key, true ),
			'user_level' => get_user_meta( $id, $level_key, true ),
			'user_level_exists' => metadata_exists( 'user', $id, $level_key ),
		);
	}
	return rest_ensure_response(
		array(
			'schema_version' => 1,
			'blog_id' => $site_id,
			'roles_option_key' => wp_roles()->role_key,
			'roles' => get_option( wp_roles()->role_key, array() ),
			'role_version_option_key' => RolesCapabilities\ROLE_VERSION_OPTION,
			'role_version' => get_option( RolesCapabilities\ROLE_VERSION_OPTION, null ),
			'default_role' => get_option( 'default_role' ),
			'capabilities_key' => $cap_key,
			'user_level_key' => $level_key,
			'users' => $users,
		)
	);
}

/** Read-only counts and grants, deliberately without account IDs or names. */
function inventory(): \WP_REST_Response {
	$roles = array();
	foreach ( wp_roles()->roles as $slug => $definition ) {
		$users = get_users( array( 'role' => $slug, 'fields' => 'all', 'blog_id' => get_current_blog_id() ) );
		$roles[] = array(
			'slug'             => $slug,
			'name'             => $definition['name'],
			'capabilities'     => $definition['capabilities'],
			'user_count'       => count( $users ),
			'multi_role_count' => count( array_filter( $users, static fn( $user ): bool => count( $user->roles ) > 1 ) ),
		);
	}
	return rest_ensure_response( array( 'roles' => $roles, 'default_role' => get_option( 'default_role' ) ) );
}

function migration_probe(): \WP_REST_Response {
	$results = array();
	foreach ( array( 'clean', 'canonical', 'legacy', 'failed_write' ) as $scenario ) {
		$results[ $scenario ] = run_fixture( $scenario );
	}
	return rest_ensure_response( $results );
}

/**
 * Real WP_Roles/WP_User persistence with an isolated role option and disposable
 * users. Both query and metadata guards prevent a migration touching real users.
 */
function run_fixture( string $scenario ): array {
	global $wp_roles, $wpdb;
	$original_roles = $wp_roles;
	$original_user  = get_current_user_id();
	$prefix        = 'mi_role_probe_' . wp_generate_password( 12, false, false );
	$role_option   = $prefix . '_roles';
	$version_option = $prefix . '_version';
	$default_option = $prefix . '_default';
	$users = array();
	$posts = array();
	$cap_key = $wpdb->get_blog_prefix( get_current_blog_id() ) . 'capabilities';
	$option_filters = array();
	$is_legacy = in_array( $scenario, array( 'legacy', 'failed_write' ), true );
	$query_guard = static function ( \WP_User_Query $query ) use ( &$users ): void {
		$query->set( 'include', $users ?: array( 0 ) );
		$query->set( 'blog_id', get_current_blog_id() );
	};
	$metadata_guard = static function ( $check, $user_id, $key ) use ( &$users, $cap_key, $scenario ) {
		if ( $cap_key === $key && ( ! in_array( (int) $user_id, $users, true ) || ( 'failed_write' === $scenario && (int) $user_id === ( $users[0] ?? 0 ) ) ) ) {
			return false;
		}
		return $check;
	};

	try {
		$wp_roles = new \WP_Roles();
		$wp_roles->role_key = $role_option;
		$wp_roles->use_db = true;
		$wp_roles->roles = array(
			'administrator' => $original_roles->roles['administrator'],
			'subscriber'    => array( 'name' => 'Subscriber', 'capabilities' => array( 'read' => true ) ),
			'fixture_other' => array( 'name' => 'Independent fixture role', 'capabilities' => array( 'fixture_other_cap' => true, 'fixture_overlap_cap' => false ) ),
			'fixture_read_deny' => array( 'name' => 'Read-denying fixture role', 'capabilities' => array( 'read' => false ) ),
		);
		if ( 'clean' !== $scenario ) {
			$wp_roles->roles['ligatura_player'] = array( 'name' => 'Player', 'capabilities' => array( 'read' => true, 'fixture_canonical_cap' => true ) );
			$wp_roles->roles['ligatura_storyguide'] = array( 'name' => 'Storyguide', 'capabilities' => array( 'read' => true ) );
		}
		if ( $is_legacy ) {
			$wp_roles->roles['ars_player'] = array( 'name' => 'Player', 'capabilities' => array( 'read' => true, 'ars_edit_assigned_personae' => true, 'fixture_legacy_cap' => true, 'fixture_denied_cap' => false, 'fixture_role_denied' => false, 'fixture_overlap_cap' => true ) );
			$wp_roles->roles['ars_storyguide'] = array( 'name' => 'Storyguide', 'capabilities' => array( 'read' => true, 'ars_manage_storyguide_notes' => true ) );
			$wp_roles->roles['third_party_player'] = array( 'name' => 'Player', 'capabilities' => array( 'read' => true ) );
		}
		$wp_roles->init_roles();
		update_option( $role_option, $wp_roles->roles, false );
		update_option( $default_option, $is_legacy ? 'ars_player' : 'subscriber', false );
		if ( 'clean' !== $scenario ) {
			update_option( $version_option, '2026-08-29-player-access-v1', false );
		}
		// Redirect only migration options, keeping live site settings untouched.
		foreach ( array( RolesCapabilities\ROLE_VERSION_OPTION => $version_option, 'default_role' => $default_option ) as $real => $isolated ) {
			$read = static fn() => get_option( $isolated, '__fixture_unset__' );
			$write = static function ( $value, $old ) use ( $isolated ) {
				update_option( $isolated, $value, false );
				return $old;
			};
			add_filter( 'pre_option_' . $real, $read );
			add_filter( 'pre_update_option_' . $real, $write, 10, 2 );
			$option_filters[] = array( $real, $read, $write );
		}
		if ( $is_legacy ) {
			for ( $index = 0; $index < 6; ++$index ) {
				$id = wp_insert_user( array( 'user_login' => $prefix . '_' . $index, 'user_pass' => wp_generate_password( 40, true, true ), 'user_email' => $prefix . '_' . $index . '@example.invalid', 'role' => 'subscriber' ) );
				if ( is_wp_error( $id ) ) {
					throw new \RuntimeException( 'Could not create isolated role fixture.' );
				}
				$users[] = (int) $id;
				$caps = array( 'fixture_other' => true, 'fixture_direct_grant' => true, 'fixture_denied_cap' => false );
				$caps[ 1 === $index ? 'ars_storyguide' : 'ars_player' ] = true;
				if ( 2 === $index ) {
					$caps['ligatura_player'] = true;
					$caps['fixture_legacy_cap'] = false;
					unset( $caps['fixture_other'] );
					$caps['fixture_other'] = true;
				}
				if ( 3 === $index ) {
					$caps['ars_storyguide'] = true;
				}
				if ( $index >= 4 ) {
					// Existing canonical role precedes an unrelated denial, while
					// the legacy role last restores read until it is retired.
					$caps = array( 'ligatura_player' => true, 'fixture_read_deny' => true, 'fixture_other' => true, 'fixture_direct_grant' => true, 'fixture_denied_cap' => false, 'ars_player' => true );
					if ( 5 === $index ) {
						$caps['read'] = false;
					}
				}
				update_user_meta( $id, $cap_key, $caps );
				clean_user_cache( $id );
			}
			$persona = wp_insert_post( array( 'post_type' => 'ligatura_character', 'post_status' => 'draft', 'post_title' => $prefix . ' Persona', 'post_author' => $original_user ), true );
			if ( is_wp_error( $persona ) ) {
				throw new \RuntimeException( 'Could not create role ownership fixtures.' );
			}
			$posts[] = $persona;
			$journal = wp_insert_post( array( 'post_type' => 'ligatura_diary', 'post_status' => 'draft', 'post_title' => $prefix . ' Journal', 'post_author' => $users[0] ), true );
			if ( is_wp_error( $journal ) ) {
				throw new \RuntimeException( 'Could not create role ownership fixtures.' );
			}
			$posts[] = $journal;
			// Seed historical data directly: today's assignment sanitizer correctly
			// rejects a legacy-only account before its role has migrated.
			$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $users[0] ), array( 'post_id' => $persona, 'meta_key' => '_ligatura_assigned_player' ) );
			wp_cache_delete( $persona, 'post_meta' );
		}
		add_action( 'pre_get_users', $query_guard, PHP_INT_MAX );
		add_filter( 'update_user_metadata', $metadata_guard, PHP_INT_MAX, 3 );
		add_filter( 'delete_user_metadata', $metadata_guard, PHP_INT_MAX, 3 );
		if ( $users ) {
			wp_set_current_user( $users[0] );
		}
		$access_before = $users ? array( 'assigned_persona' => current_user_can( 'edit_post', $posts[0] ), 'own_journal' => current_user_can( 'edit_post', $posts[1] ) ) : null;
		$read_before = array_map( static fn( $id ) => ( new \WP_User( $id ) )->allcaps['read'] ?? false, $users );
		RolesCapabilities\maybe_upgrade_roles();
		$access_after = $users ? array( 'assigned_persona' => current_user_can( 'edit_post', $posts[0] ), 'own_journal' => current_user_can( 'edit_post', $posts[1] ) ) : null;
		$current_roles = wp_get_current_user()->roles;
		$user_results = array();
		foreach ( $users as $id ) {
			$fresh = new \WP_User( $id );
			$user_results[] = array( 'roles' => array_values( $fresh->roles ), 'direct' => $fresh->caps, 'allcaps' => $fresh->allcaps );
		}
		$before_repeat = array( get_option( $role_option ), get_option( $version_option ), array_map( static fn( $id ) => get_user_meta( $id, $cap_key, true ), $users ) );
		$writes = 0;
		$count_write = static function () use ( &$writes ): void { ++$writes; };
		add_action( 'updated_option', $count_write );
		RolesCapabilities\maybe_upgrade_roles();
		remove_action( 'updated_option', $count_write );
		$after_repeat = array( get_option( $role_option ), get_option( $version_option ), array_map( static fn( $id ) => get_user_meta( $id, $cap_key, true ), $users ) );
		return array(
			'roles' => get_option( $role_option ),
			'users' => $user_results,
			'current_roles' => $current_roles,
			'access_before' => $access_before,
			'read_before' => $read_before,
			'access_after' => $access_after,
			'default_role' => get_option( 'default_role' ),
			'version_recorded' => RolesCapabilities\ROLE_VERSION === get_option( RolesCapabilities\ROLE_VERSION_OPTION ),
			'repeat_unchanged' => $before_repeat === $after_repeat,
			'repeat_option_writes' => $writes,
			'assignment_preserved' => ! $users || (int) get_post_meta( $posts[0], '_ligatura_assigned_player', true ) === $users[0],
			'journal_owner_preserved' => ! $users || (int) get_post_field( 'post_author', $posts[1] ) === $users[0],
		);
	} finally {
		remove_action( 'pre_get_users', $query_guard, PHP_INT_MAX );
		remove_filter( 'update_user_metadata', $metadata_guard, PHP_INT_MAX );
		remove_filter( 'delete_user_metadata', $metadata_guard, PHP_INT_MAX );
		foreach ( $option_filters as [ $real, $read, $write ] ) {
			remove_filter( 'pre_option_' . $real, $read );
			remove_filter( 'pre_update_option_' . $real, $write );
		}
		$wp_roles = $original_roles;
		wp_set_current_user( $original_user );
		foreach ( $posts as $id ) {
			wp_delete_post( $id, true );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $users as $id ) {
			wp_delete_user( $id );
		}
		foreach ( array( $role_option, $version_option, $default_option ) as $option ) {
			delete_option( $option );
		}
	}
}
