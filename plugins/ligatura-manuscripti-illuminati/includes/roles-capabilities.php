<?php
/**
 * Roles and capabilities.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\RolesCapabilities;

use LigaturaManuscriptiIlluminati\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PLAYER_ROLE         = 'ligatura_player';
const ROLE_VERSION        = '2026-10-15-legacy-role-migration-v2';
const ROLE_VERSION_OPTION = 'ligatura_manuscripti_illuminati_role_version';

/**
 * Get all campaign post type capabilities.
 *
 * @return string[]
 */
function get_post_type_capabilities(): array {
	$caps = array();

	foreach ( PostTypes\get_definitions() as $definition ) {
		$caps = array_merge(
			$caps,
			array_values( PostTypes\capability_map( $definition['capability'][0], $definition['capability'][1] ) )
		);
	}

	return array_values( array_unique( $caps ) );
}

/**
 * Capabilities needed by Storyguides.
 *
 * @return array<string,bool>
 */
function get_storyguide_capabilities(): array {
	$caps = array_fill_keys( get_post_type_capabilities(), true );

	$caps['read']                         = true;
	$caps['upload_files']                 = true;
	$caps['moderate_comments']            = true;
	$caps['ligatura_manage_storyguide_notes']  = true;
	$caps['ligatura_manage_campaign_terms']    = true;
	$caps['ligatura_assign_campaign_terms']    = true;
	$caps['ligatura_assign_persona_players']   = true;
	$caps['ligatura_edit_assigned_personae']   = true;
	$caps['unfiltered_html']              = false;

	return $caps;
}

/**
 * Capabilities needed by Players.
 *
 * @return array<string,bool>
 */
function get_player_capabilities(): array {
	return array(
		'read'                                  => true,
		'upload_files'                          => true,
		'ligatura_assign_campaign_terms'             => true,
		'ligatura_edit_assigned_personae'             => true,
		'edit_ligatura_characters'                    => true,
		'read_ligatura_diary_entry'                  => true,
		'edit_ligatura_diary_entry'                  => true,
		'delete_ligatura_diary_entry'                => true,
		'edit_ligatura_diary_entries'                => true,
		'create_ligatura_diary_entries'              => true,
		'publish_ligatura_diary_entries'             => true,
		'delete_ligatura_diary_entries'              => true,
		'edit_published_ligatura_diary_entries'      => true,
		'delete_published_ligatura_diary_entries'    => true,
	);
}

/**
 * Synchronize project-owned capabilities while preserving third-party grants.
 *
 * @param array<string,bool> $capabilities Desired primitive capabilities.
 */
function sync_role_capabilities( \WP_Role $role, array $capabilities ): void {
	foreach ( array_keys( $role->capabilities ) as $capability ) {
		if ( str_starts_with( $capability, 'ligatura_' ) && ! array_key_exists( $capability, $capabilities ) ) {
			$role->remove_cap( $capability );
		}
	}

	foreach ( $capabilities as $capability => $grant ) {
		$role->add_cap( $capability, $grant );
	}
}

/**
 * Keep the existing role slug/user assignments while updating its visible name.
 */
function update_role_name( string $role_slug, string $name ): void {
	global $wp_roles;

	if ( ! $wp_roles instanceof \WP_Roles || ! isset( $wp_roles->roles[ $role_slug ] ) ) {
		return;
	}

	$wp_roles->roles[ $role_slug ]['name'] = $name;
	$wp_roles->role_names[ $role_slug ]    = $name;
	update_option( $wp_roles->role_key, $wp_roles->roles );
}

/**
 * Add roles and grant administrators all custom capabilities.
 */
function add_roles_and_capabilities(): void {
	$storyguide_caps = get_storyguide_capabilities();
	$player_caps     = get_player_capabilities();

	$storyguide_role = get_role( 'ligatura_storyguide' );
	if ( ! $storyguide_role ) {
		add_role( 'ligatura_storyguide', __( 'Storyguide', 'ligatura-manuscripti-illuminati' ), $storyguide_caps );
	} else {
		foreach ( $storyguide_caps as $capability => $grant ) {
			$storyguide_role->add_cap( $capability, $grant );
		}
		update_role_name( 'ligatura_storyguide', __( 'Storyguide', 'ligatura-manuscripti-illuminati' ) );
	}

	$player_role = get_role( PLAYER_ROLE );
	if ( ! $player_role ) {
		add_role( PLAYER_ROLE, __( 'Player', 'ligatura-manuscripti-illuminati' ), $player_caps );
	} else {
		sync_role_capabilities( $player_role, $player_caps );
		update_role_name( PLAYER_ROLE, __( 'Player', 'ligatura-manuscripti-illuminati' ) );
	}

	$administrator = get_role( 'administrator' );
	if ( $administrator ) {
		foreach ( $storyguide_caps as $capability => $grant ) {
			$administrator->add_cap( $capability, $grant );
		}
	}

	if ( migrate_legacy_roles() ) {
		update_option( ROLE_VERSION_OPTION, ROLE_VERSION, false );
	}
}

/**
 * Replace only the two roles shipped by the former Ars Campaign Core plugin.
 * Roles and capability keys belong to the current site, including on multisite.
 * A failed write leaves the legacy definition and version available for retry.
 */
function migrate_legacy_roles(): bool {
	$roles = array( 'ars_player' => PLAYER_ROLE, 'ars_storyguide' => 'ligatura_storyguide' );
	$stored_roles = get_option( wp_roles()->role_key, array() );

	// Never remove a user's old access before the replacement is persisted.
	foreach ( $roles as $canonical ) {
		if ( ! isset( $stored_roles[ $canonical ] ) || $stored_roles[ $canonical ] !== wp_roles()->roles[ $canonical ] ) {
			return false;
		}
	}

	foreach ( $roles as $legacy => $canonical ) {
		$legacy_role = get_role( $legacy );
		$user_ids = get_users(
			array( 'role' => $legacy, 'fields' => 'ID', 'blog_id' => get_current_blog_id() )
		);
		foreach ( $user_ids as $user_id ) {
			$user = new \WP_User( (int) $user_id, '', get_current_blog_id() );
			if ( ! migrate_legacy_user_role( $user, $legacy, $canonical, $legacy_role ? $legacy_role->capabilities : array() ) ) {
				return false;
			}
		}
		// Recheck persisted memberships before retiring the definition, including
		// accounts added while this upgrade was running.
		if ( get_users( array( 'role' => $legacy, 'fields' => 'ID', 'blog_id' => get_current_blog_id(), 'number' => 1, 'cache_results' => false ) ) ) {
			return false;
		}

		if ( $legacy === get_option( 'default_role' ) ) {
			update_option( 'default_role', $canonical );
			if ( $canonical !== get_option( 'default_role' ) ) {
				return false;
			}
		}

		if ( $legacy_role ) {
			remove_role( $legacy );
			$stored_roles = get_option( wp_roles()->role_key, array() );
			if ( isset( $stored_roles[ $legacy ] ) ) {
				return false;
			}
		}
	}

	// The logged-in WP_User may have been loaded before init ran the upgrade.
	wp_get_current_user()->for_site( get_current_blog_id() );
	return true;
}

/**
 * Preserve unrelated memberships and individual grants, using role APIs rather
 * than set_role(), which would discard every other role. Keep unusual grants
 * from the retired role on its existing members only, never on every Player.
 *
 * @param array<string,bool> $legacy_capabilities Retiring role's grants/denials.
 */
function migrate_legacy_user_role( \WP_User $user, string $legacy, string $canonical, array $legacy_capabilities ): bool {
	$canonical_role = get_role( $canonical );
	if ( ! $canonical_role || ! $user->exists() ) {
		return false;
	}
	if ( ! array_key_exists( $legacy, $user->caps ) ) {
		return false;
	}
	$effective_capabilities = $user->allcaps;
	// Use WordPress's own merge rules on a copy: an existing canonical role
	// keeps its original position, so another retained role can override it.
	$replacement_user = clone $user;
	unset( $replacement_user->caps[ $legacy ] );
	$replacement_user->caps[ $canonical ] = true;
	$replacement_capabilities = $replacement_user->get_role_caps();
	foreach ( $legacy_capabilities as $capability => $grant ) {
		// Another retained role may override the old role's grant or denial.
		$grant = $effective_capabilities[ $capability ] ?? $grant;
		if ( array_key_exists( $capability, $user->caps )
			|| ( array_key_exists( $capability, $replacement_capabilities ) && $replacement_capabilities[ $capability ] === $grant ) ) {
			continue;
		}
		// A role name in individual caps grants membership. Do not turn an
		// unusual inherited capability into another role during migration.
		if ( wp_roles()->is_role( $capability ) ) {
			return false;
		}
		$user->add_cap( $capability, $grant );
		if ( get_user_meta( $user->ID, $user->cap_key, true ) !== $user->caps ) {
			return false;
		}
	}

	$user->add_role( $canonical );
	$stored_caps = get_user_meta( $user->ID, $user->cap_key, true );
	if ( empty( $stored_caps[ $canonical ] ) ) {
		return false;
	}
	if ( in_array( $legacy, $user->roles, true ) ) {
		$user->remove_role( $legacy );
	} else {
		// Also repair known orphan memberships whose definition was removed.
		$user->remove_cap( $legacy );
	}
	$stored_caps = get_user_meta( $user->ID, $user->cap_key, true );
	if ( ! is_array( $stored_caps ) || array_key_exists( $legacy, $stored_caps ) || empty( $stored_caps[ $canonical ] ) ) {
		return false;
	}
	if ( get_current_user_id() === $user->ID ) {
		wp_get_current_user()->for_site( get_current_blog_id() );
	}
	return true;
}

/**
 * Upgrade roles on existing installations only when the definition changes.
 */
function maybe_upgrade_roles(): void {
	if ( ROLE_VERSION === get_option( ROLE_VERSION_OPTION ) ) {
		return;
	}

	add_roles_and_capabilities();
}
