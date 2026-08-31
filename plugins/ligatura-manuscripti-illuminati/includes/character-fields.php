<?php
/**
 * Character, wiki, and diary field definitions.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\CharacterFields;

use LigaturaManuscriptiIlluminati\JournalDates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Character field groups for admin screens and meta registration.
 *
 * @return array<string,array<int,array<string,string>>>
 */
function get_character_field_groups(): array {
	return array(
		'identity'       => array(
			array( 'key' => 'ligatura_character_type', 'label' => __( 'Character Type', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
			array( 'key' => 'ligatura_house', 'label' => __( 'Hermetic House', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
			array( 'key' => 'ligatura_tradition', 'label' => __( 'Tradition', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
			array( 'key' => 'ligatura_player', 'label' => __( 'Player', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
			array( 'key' => 'ligatura_apparent_age', 'label' => __( 'Apparent Age', 'ligatura-manuscripti-illuminati' ), 'type' => 'number', 'sanitize' => 'integer' ),
			array( 'key' => 'ligatura_status', 'label' => __( 'Status', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
			array( 'key' => 'ligatura_age', 'label' => __( 'Age', 'ligatura-manuscripti-illuminati' ), 'type' => 'number', 'sanitize' => 'integer' ),
			array( 'key' => 'ligatura_birth_year', 'label' => __( 'Birth Year', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
			array( 'key' => 'ligatura_origin', 'label' => __( 'Origin', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
			array( 'key' => 'ligatura_occupation', 'label' => __( 'Occupation', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
		),
		'role'           => array(
			array( 'key' => 'ligatura_role', 'label' => __( 'Saga Role', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
			array( 'key' => 'ligatura_covenant_role', 'label' => __( 'Covenant Role', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
			array( 'key' => 'ligatura_brief_description', 'label' => __( 'Brief Description', 'ligatura-manuscripti-illuminati' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),
		),
		'traits'         => array(
			array( 'key' => 'ligatura_characteristics', 'label' => __( 'Characteristics', 'ligatura-manuscripti-illuminati' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),
			array( 'key' => 'ligatura_personality_traits', 'label' => __( 'Personality Traits', 'ligatura-manuscripti-illuminati' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),
			array( 'key' => 'ligatura_reputations', 'label' => __( 'Reputations', 'ligatura-manuscripti-illuminati' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),
			array( 'key' => 'ligatura_confidence', 'label' => __( 'Confidence', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
			array( 'key' => 'ligatura_warping', 'label' => __( 'Warping', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
			array( 'key' => 'ligatura_wounds', 'label' => __( 'Wounds', 'ligatura-manuscripti-illuminati' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),
		),
		'abilities'      => array(
			array( 'key' => 'ligatura_virtues', 'label' => __( 'Virtues', 'ligatura-manuscripti-illuminati' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),
			array( 'key' => 'ligatura_flaws', 'label' => __( 'Flaws', 'ligatura-manuscripti-illuminati' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),
			array( 'key' => 'ligatura_abilities', 'label' => __( 'Abilities', 'ligatura-manuscripti-illuminati' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),
			array( 'key' => 'ligatura_arts', 'label' => __( 'Hermetic Arts', 'ligatura-manuscripti-illuminati' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),
			array( 'key' => 'ligatura_spells', 'label' => __( 'Spells', 'ligatura-manuscripti-illuminati' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),
			array( 'key' => 'ligatura_equipment', 'label' => __( 'Equipment', 'ligatura-manuscripti-illuminati' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),
		),
		'full_sheet'     => array(
			array( 'key' => 'ligatura_full_sheet', 'label' => __( 'Full Sheet', 'ligatura-manuscripti-illuminati' ), 'type' => 'rich_text', 'sanitize' => 'rich_text' ),
		),
		'public_notes'   => array(
			array( 'key' => 'ligatura_notes_public', 'label' => __( 'Public Notes', 'ligatura-manuscripti-illuminati' ), 'type' => 'rich_text', 'sanitize' => 'rich_text' ),
		),
	);
}

/**
 * Flatten character field groups.
 *
 * @return array<int,array<string,string>>
 */
function get_character_fields(): array {
	return array_merge( ...array_values( get_character_field_groups() ) );
}

/**
 * Wiki meta fields.
 *
 * @return array<int,array<string,string>>
 */
function get_wiki_fields(): array {
	return array(
		array( 'key' => 'ligatura_entry_type', 'label' => __( 'Entry Type', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
		array( 'key' => 'ligatura_entry_status', 'label' => __( 'Entry Status', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
		array( 'key' => 'ligatura_in_world_date', 'label' => __( 'In-World Date', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
		array( 'key' => 'ligatura_related_characters', 'label' => __( 'Related Characters', 'ligatura-manuscripti-illuminati' ), 'type' => 'character_multiselect', 'sanitize' => 'character_id_list' ),
		array( 'key' => 'ligatura_related_places', 'label' => __( 'Related Places', 'ligatura-manuscripti-illuminati' ), 'type' => 'place_multiselect', 'sanitize' => 'place_id_list' ),
		array( 'key' => 'ligatura_related_entries', 'label' => __( 'Related Entries', 'ligatura-manuscripti-illuminati' ), 'type' => 'entry_multiselect', 'sanitize' => 'wiki_id_list' ),
		array( 'key' => 'ligatura_public_summary', 'label' => __( 'Public Summary', 'ligatura-manuscripti-illuminati' ), 'type' => 'rich_text', 'sanitize' => 'rich_text' ),
	);
}

/**
 * Journal meta fields.
 *
 * @return array<int,array<string,string>>
 */
function get_diary_fields(): array {
	return array(
		array( 'key' => 'ligatura_diary_character', 'label' => __( 'Character Author', 'ligatura-manuscripti-illuminati' ), 'type' => 'character_select', 'sanitize' => 'character_author' ),
		array( 'key' => JournalDates\META_KEY, 'label' => __( 'Saga Date', 'ligatura-manuscripti-illuminati' ), 'type' => 'date', 'sanitize' => 'saga_date' ),
		array( 'key' => 'ligatura_diary_session_date', 'label' => __( 'Session Date', 'ligatura-manuscripti-illuminati' ), 'type' => 'date', 'sanitize' => 'text' ),
	);
}

/**
 * Covenant Record meta fields.
 *
 * These deliberately mirror the current Speculum metadata interface, with the
 * Journal-style Saga Date replacing Speculum's free-form In-World Date.
 *
 * @return array<int,array<string,string>>
 */
function get_covenant_fields(): array {
	return array(
		array( 'key' => 'ligatura_entry_type', 'label' => __( 'Entry Type', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
		array( 'key' => 'ligatura_entry_status', 'label' => __( 'Entry Status', 'ligatura-manuscripti-illuminati' ), 'type' => 'text', 'sanitize' => 'text' ),
		array( 'key' => 'ligatura_covenant_saga_date', 'label' => __( 'Saga Date', 'ligatura-manuscripti-illuminati' ), 'type' => 'date', 'sanitize' => 'saga_date' ),
		array( 'key' => 'ligatura_related_characters', 'label' => __( 'Related Characters', 'ligatura-manuscripti-illuminati' ), 'type' => 'character_multiselect', 'sanitize' => 'character_id_list' ),
		array( 'key' => 'ligatura_related_places', 'label' => __( 'Related Places', 'ligatura-manuscripti-illuminati' ), 'type' => 'place_multiselect', 'sanitize' => 'place_id_list' ),
		array( 'key' => 'ligatura_related_entries', 'label' => __( 'Related Entries', 'ligatura-manuscripti-illuminati' ), 'type' => 'entry_multiselect', 'sanitize' => 'wiki_id_list' ),
		array( 'key' => 'ligatura_public_summary', 'label' => __( 'Public Summary', 'ligatura-manuscripti-illuminati' ), 'type' => 'rich_text', 'sanitize' => 'rich_text' ),
	);
}
