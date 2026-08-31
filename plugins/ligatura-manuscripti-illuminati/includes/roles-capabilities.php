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
const ROLE_VERSION        = '2026-08-29-player-access-v1';
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

	update_option( ROLE_VERSION_OPTION, ROLE_VERSION, false );
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
