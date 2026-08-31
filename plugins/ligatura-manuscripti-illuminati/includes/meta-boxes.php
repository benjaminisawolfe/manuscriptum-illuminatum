<?php
/**
 * Meta registration and admin meta boxes.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\MetaBoxes;

use LigaturaManuscriptiIlluminati\CharacterFields;
use LigaturaManuscriptiIlluminati\JournalAuthorship;
use LigaturaManuscriptiIlluminati\JournalDates;
use LigaturaManuscriptiIlluminati\PlayerAccess;
use LigaturaManuscriptiIlluminati\PrivateNotes;
use LigaturaManuscriptiIlluminati\Sanitization;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const NONCE_ACTION = 'ligatura_manuscripti_illuminati_save_meta';
const NONCE_NAME   = 'ligatura_manuscripti_illuminati_meta_nonce';

/**
 * Register meta keys with sanitization and REST exposure rules.
 */
function register_meta_fields(): void {
	foreach ( CharacterFields\get_character_fields() as $field ) {
		register_campaign_meta( 'ligatura_character', $field, true );
	}

	foreach ( CharacterFields\get_wiki_fields() as $field ) {
		register_campaign_meta( 'ligatura_wiki', $field, true );
	}

	foreach ( CharacterFields\get_diary_fields() as $field ) {
		register_campaign_meta( 'ligatura_diary', $field, true );
	}

	foreach ( CharacterFields\get_covenant_fields() as $field ) {
		register_campaign_meta( 'ligatura_covenant', $field, true );
	}

	register_post_meta(
		'ligatura_wiki',
		PrivateNotes\META_KEY,
		array(
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_rich_text',
			'auth_callback'     => __NAMESPACE__ . '\\can_access_storyguide_notes_meta',
			'show_in_rest'      => false,
		)
	);
}

/**
 * Register a standard campaign meta key.
 *
 * @param string               $post_type    Post type.
 * @param array<string,string> $field        Field definition.
 * @param bool                 $show_in_rest Whether field is REST-visible to logged-in readers.
 */
function register_campaign_meta( string $post_type, array $field, bool $show_in_rest ): void {
	register_post_meta(
		$post_type,
		$field['key'],
		array(
			'type'              => in_array( $field['sanitize'], array( 'integer', 'character_author' ), true ) ? 'integer' : 'string',
			'single'            => true,
			'sanitize_callback' => get_sanitize_callback( $field['sanitize'] ),
			'auth_callback'     => __NAMESPACE__ . '\\can_access_public_meta',
			'show_in_rest'      => $show_in_rest,
		)
	);
}

/**
 * Standard meta authorization.
 *
 * @param bool|null $allowed   Existing result.
 * @param string    $meta_key  Meta key.
 * @param int       $object_id Post ID.
 * @param int       $user_id   User ID.
 * @param string    $cap       Requested meta capability.
 */
function can_access_public_meta( $allowed, string $meta_key, int $object_id, int $user_id = 0, string $cap = 'read_post_meta' ): bool {
	unset( $allowed, $meta_key, $user_id );

	if ( ! is_user_logged_in() ) {
		return false;
	}

	if ( str_contains( $cap, 'edit' ) || str_contains( $cap, 'delete' ) || str_contains( $cap, 'add' ) ) {
		return current_user_can( 'edit_post', $object_id );
	}

	return current_user_can( 'read_post', $object_id );
}

/**
 * Private-note meta authorization.
 *
 * @param bool|null $allowed   Existing result.
 * @param string    $meta_key  Meta key.
 * @param int       $object_id Post ID.
 * @param int       $user_id   User ID.
 * @param string    $cap       Requested meta capability.
 */
function can_access_storyguide_notes_meta( $allowed, string $meta_key, int $object_id, int $user_id = 0, string $cap = 'read_post_meta' ): bool {
	unset( $allowed, $meta_key, $user_id );

	if ( ! PrivateNotes\can_view_storyguide_notes() ) {
		return false;
	}

	if ( str_contains( $cap, 'edit' ) || str_contains( $cap, 'delete' ) || str_contains( $cap, 'add' ) ) {
		return current_user_can( 'edit_post', $object_id );
	}

	return current_user_can( 'read_post', $object_id );
}

/**
 * Map field definition names to callbacks.
 *
 * @param string $type Sanitizer type.
 * @return callable
 */
function get_sanitize_callback( string $type ): callable {
	return match ( $type ) {
		'integer'           => __NAMESPACE__ . '\\sanitize_integer',
		'character_author'  => __NAMESPACE__ . '\\sanitize_character_author',
		'rich_text'         => __NAMESPACE__ . '\\sanitize_rich_text',
		'id_list'           => __NAMESPACE__ . '\\sanitize_id_list',
		'character_id_list' => __NAMESPACE__ . '\\sanitize_character_id_list',
		'place_id_list'     => __NAMESPACE__ . '\\sanitize_place_id_list',
		'wiki_id_list'      => __NAMESPACE__ . '\\sanitize_wiki_id_list',
		'textarea'          => __NAMESPACE__ . '\\sanitize_textarea',
		'saga_date'         => __NAMESPACE__ . '\\sanitize_saga_date',
		default             => __NAMESPACE__ . '\\sanitize_text',
	};
}

/**
 * Meta sanitizer wrappers.
 *
 * @param mixed $value Raw value.
 */
function sanitize_text( mixed $value ): string {
	return Sanitization\text( $value );
}

/**
 * Meta sanitizer wrappers.
 *
 * @param mixed $value Raw value.
 */
function sanitize_textarea( mixed $value ): string {
	return Sanitization\textarea( $value );
}

/**
 * Meta sanitizer wrappers.
 *
 * @param mixed $value Raw value.
 */
function sanitize_rich_text( mixed $value ): string {
	return Sanitization\rich_text( $value );
}

/**
 * Meta sanitizer wrappers.
 *
 * @param mixed $value Raw value.
 */
function sanitize_integer( mixed $value ): int {
	return Sanitization\integer( $value );
}

/**
 * Sanitize a Journal Character Author against the current Player assignment.
 */
function sanitize_character_author( mixed $value ): int {
	$character_id = Sanitization\integer( $value );

	if ( $character_id < 1 || 'ligatura_character' !== get_post_type( $character_id ) ) {
		return 0;
	}

	if ( PlayerAccess\is_restricted_player() && ! JournalAuthorship\user_can_author_as_character( get_current_user_id(), $character_id ) ) {
		return 0;
	}

	return $character_id;
}

/**
 * Meta sanitizer wrappers.
 *
 * @param mixed $value Raw value.
 */
function sanitize_saga_date( mixed $value ): string {
	return JournalDates\sanitize_saga_date( $value );
}

/**
 * Meta sanitizer wrappers.
 *
 * @param mixed $value Raw value.
 */
function sanitize_id_list( mixed $value ): string {
	return Sanitization\id_list( $value );
}

/**
 * Sanitize a Character relationship ID list for UI and REST writes.
 */
function sanitize_character_id_list( mixed $value ): string {
	return sanitize_entity_id_list( $value, 'ligatura_character' );
}

/**
 * Sanitize a Place relationship ID list for UI and REST writes.
 */
function sanitize_place_id_list( mixed $value ): string {
	return sanitize_entity_id_list( $value, 'ligatura_wiki', true );
}

/**
 * Sanitize a Speculum relationship ID list for UI and REST writes.
 */
function sanitize_wiki_id_list( mixed $value ): string {
	return sanitize_entity_id_list( $value, 'ligatura_wiki' );
}

/**
 * Keep only IDs from the configured entity source.
 */
function sanitize_entity_id_list( mixed $value, string $post_type, bool $require_place = false ): string {
	$valid = array();

	foreach ( parse_id_list( $value ) as $entity_id ) {
		if ( $post_type !== get_post_type( $entity_id ) ) {
			continue;
		}

		if ( $require_place && ! has_term( 'place', 'ligatura_entry_type', $entity_id ) ) {
			continue;
		}

		$valid[] = $entity_id;
	}

	return implode( ',', array_unique( $valid ) );
}

/**
 * Add campaign meta boxes.
 */
function register_meta_boxes(): void {
	add_meta_box(
		'manuscriptum-illuminatum-character-identity',
		__( 'Basic Identity', 'ligatura-manuscripti-illuminati' ),
		__NAMESPACE__ . '\\render_character_identity_box',
		'ligatura_character',
		'normal',
		'high'
	);

	add_meta_box(
		'manuscriptum-illuminatum-character-role',
		__( 'Ars Magica Role', 'ligatura-manuscripti-illuminati' ),
		__NAMESPACE__ . '\\render_character_role_box',
		'ligatura_character',
		'normal',
		'default'
	);

	add_meta_box(
		'manuscriptum-illuminatum-character-traits',
		__( 'Characteristics and Traits', 'ligatura-manuscripti-illuminati' ),
		__NAMESPACE__ . '\\render_character_traits_box',
		'ligatura_character',
		'normal',
		'default'
	);

	add_meta_box(
		'manuscriptum-illuminatum-character-abilities',
		__( 'Virtues, Flaws, Abilities, Arts, Spells, and Equipment', 'ligatura-manuscripti-illuminati' ),
		__NAMESPACE__ . '\\render_character_abilities_box',
		'ligatura_character',
		'normal',
		'default'
	);

	add_meta_box(
		'manuscriptum-illuminatum-character-full-sheet',
		__( 'Rich Text Full Sheet', 'ligatura-manuscripti-illuminati' ),
		__NAMESPACE__ . '\\render_character_full_sheet_box',
		'ligatura_character',
		'normal',
		'default'
	);

	add_meta_box(
		'manuscriptum-illuminatum-character-public-notes',
		__( 'Public Notes', 'ligatura-manuscripti-illuminati' ),
		__NAMESPACE__ . '\\render_character_public_notes_box',
		'ligatura_character',
		'normal',
		'default'
	);

	add_meta_box(
		'manuscriptum-illuminatum-wiki-details',
		__( 'Wiki Details', 'ligatura-manuscripti-illuminati' ),
		__NAMESPACE__ . '\\render_wiki_details_box',
		'ligatura_wiki',
		'normal',
		'high'
	);

	add_meta_box(
		'manuscriptum-illuminatum-storyguide-notes',
		__( 'Storyguide Notes', 'ligatura-manuscripti-illuminati' ),
		__NAMESPACE__ . '\\render_storyguide_notes_box',
		'ligatura_wiki',
		'normal',
		'default'
	);

	add_meta_box(
		'manuscriptum-illuminatum-diary-details',
		__( 'Journal Details', 'ligatura-manuscripti-illuminati' ),
		__NAMESPACE__ . '\\render_diary_details_box',
		'ligatura_diary',
		'normal',
		'default'
	);

	add_meta_box(
		'manuscriptum-illuminatum-covenant-details',
		__( 'Covenant Record Details', 'ligatura-manuscripti-illuminati' ),
		__NAMESPACE__ . '\\render_covenant_details_box',
		'ligatura_covenant',
		'normal',
		'high'
	);
}

/**
 * Print a nonce once per edit screen.
 */
function render_nonce(): void {
	static $printed = false;

	if ( $printed ) {
		return;
	}

	wp_nonce_field( NONCE_ACTION, NONCE_NAME );
	$printed = true;
}

/**
 * Render standard fields.
 *
 * @param int                  $post_id Post ID.
 * @param array<int,array<string,string>> $fields Field definitions.
 */
function render_fields( int $post_id, array $fields ): void {
	render_nonce();

	echo '<div class="manuscriptum-illuminatum-admin-fields">';

	foreach ( $fields as $field ) {
		$value = get_post_meta( $post_id, $field['key'], true );
		$tag   = 'rich_text' === $field['type'] ? 'div' : 'p';
		echo '<' . esc_attr( $tag ) . ' class="manuscriptum-illuminatum-admin-field manuscriptum-illuminatum-admin-field--' . esc_attr( $field['type'] ) . '">';
		echo '<label for="' . esc_attr( $field['key'] ) . '">' . esc_html( $field['label'] ) . '</label>';

		if ( 'textarea' === $field['type'] ) {
			echo '<textarea id="' . esc_attr( $field['key'] ) . '" name="' . esc_attr( $field['key'] ) . '" rows="5">' . esc_textarea( (string) $value ) . '</textarea>';
		} elseif ( 'character_select' === $field['type'] ) {
			render_character_select_field( $field['key'], absint( $value ) );
		} elseif ( str_ends_with( $field['type'], '_multiselect' ) ) {
			render_relationship_select_field( $post_id, $field, parse_id_list( $value ) );
		} elseif ( 'rich_text' === $field['type'] ) {
			wp_editor(
				(string) $value,
				$field['key'],
				array(
					'textarea_name' => $field['key'],
					'textarea_rows' => 8,
					'media_buttons' => false,
				)
			);
		} else {
			echo '<input type="' . esc_attr( $field['type'] ) . '" id="' . esc_attr( $field['key'] ) . '" name="' . esc_attr( $field['key'] ) . '" value="' . esc_attr( (string) $value ) . '" />';
		}

		echo '</' . esc_attr( $tag ) . '>';
	}

	echo '</div>';
}

/**
 * Parse stored or submitted relationship IDs.
 *
 * @return int[]
 */
function parse_id_list( mixed $value ): array {
	$sanitized = sanitize_id_list( $value );

	if ( '' === $sanitized ) {
		return array();
	}

	return array_values( array_filter( array_map( 'absint', explode( ',', $sanitized ) ) ) );
}

/**
 * Render a labelled, keyboard-accessible relationship multi-select.
 *
 * @param int                  $post_id    Current Speculum entry ID.
 * @param array<string,string> $field      Field definition.
 * @param int[]                $current_ids Stored relationship IDs.
 */
function render_relationship_select_field( int $post_id, array $field, array $current_ids ): void {
	$options = get_relationship_select_options( $post_id, $field['key'] );

	echo '<select id="' . esc_attr( $field['key'] ) . '" name="' . esc_attr( $field['key'] ) . '[]" multiple="multiple" size="8" data-manuscriptum-illuminatum-relationship-select>';

	foreach ( $options as $entity_id => $label ) {
		printf(
			'<option value="%1$d"%2$s>%3$s</option>',
			absint( $entity_id ),
			selected( in_array( $entity_id, $current_ids, true ), true, false ),
			esc_html( $label )
		);
	}

	echo '</select>';
	echo '<span class="description">' . esc_html__( 'Choose one or more names. Hold Ctrl (Windows) or Command (macOS) to change individual selections; deselect all choices to clear the field.', 'ligatura-manuscripti-illuminati' ) . '</span>';
}

/**
 * Return valid relationship choices sorted by human-readable title.
 *
 * Related Places are Speculum entries assigned to the existing Place Entry Type.
 *
 * @return array<int,string>
 */
function get_relationship_select_options( int $post_id, string $field_key ): array {
	$args = array(
		'post_status'      => array( 'publish', 'private', 'draft', 'pending', 'future' ),
		'posts_per_page'   => -1,
		'suppress_filters' => false,
	);

	if ( 'ligatura_related_characters' === $field_key ) {
		$args['post_type'] = 'ligatura_character';
	} elseif ( 'ligatura_related_places' === $field_key ) {
		$args['post_type'] = 'ligatura_wiki';
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'ligatura_entry_type',
				'field'    => 'slug',
				'terms'    => 'place',
			),
		);
	} elseif ( 'ligatura_related_entries' === $field_key ) {
		$args['post_type']    = 'ligatura_wiki';
		$args['post__not_in'] = array( $post_id );
	} else {
		return array();
	}

	$options = array();

	foreach ( get_posts( $args ) as $entity ) {
		if ( ! $entity instanceof \WP_Post || ! current_user_can( 'read_post', $entity->ID ) ) {
			continue;
		}

		$options[ $entity->ID ] = get_the_title( $entity );
	}

	uasort( $options, __NAMESPACE__ . '\compare_relationship_labels' );

	return $options;
}

/**
 * Compare relationship labels alphabetically, ignoring a leading “The”.
 */
function compare_relationship_labels( string $left, string $right ): int {
	$left_key  = (string) preg_replace( '/^the\s+/iu', '', trim( wp_strip_all_tags( $left ) ) );
	$right_key = (string) preg_replace( '/^the\s+/iu', '', trim( wp_strip_all_tags( $right ) ) );
	$result    = strnatcasecmp( $left_key, $right_key );

	return 0 !== $result ? $result : strnatcasecmp( $left, $right );
}

/**
 * Render a Character dropdown for Journal relationships.
 *
 * @param string $field_key  Meta field key.
 * @param int    $current_id Currently stored Character ID.
 */
function render_character_select_field( string $field_key, int $current_id ): void {
	$options = get_character_select_options( $current_id );

	if ( 0 === $current_id && PlayerAccess\is_restricted_player() && 1 === count( $options ) ) {
		$current_id = (int) array_key_first( $options );
	}

	echo '<select id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '">';
	echo '<option value="">' . esc_html__( 'No Character Author', 'ligatura-manuscripti-illuminati' ) . '</option>';

	foreach ( $options as $character_id => $label ) {
		printf(
			'<option value="%1$d"%2$s>%3$s</option>',
			absint( $character_id ),
			selected( $current_id, $character_id, false ),
			esc_html( $label )
		);
	}

	if ( $current_id && ! isset( $options[ $current_id ] ) && ! PlayerAccess\is_restricted_player() ) {
		printf(
			'<option value="%1$d" selected="selected">%2$s</option>',
			absint( $current_id ),
			esc_html__( 'Current character unavailable', 'ligatura-manuscripti-illuminati' )
		);
	}

	echo '</select>';

	if ( PlayerAccess\is_restricted_player() && empty( $options ) ) {
		echo '<span class="description manuscriptum-illuminatum-character-author-warning">' . esc_html__( 'No Persona is assigned to you. You may save a draft, but you cannot publish until an administrator assigns one.', 'ligatura-manuscripti-illuminati' ) . '</span>';
	}
}

/**
 * Return accessible Characters sorted alphabetically for a select control.
 *
 * @return array<int,string>
 */
function get_character_select_options( int $current_id = 0 ): array {
	$characters = get_posts(
		array(
			'post_type'        => 'ligatura_character',
			'post_status'      => array( 'publish', 'private', 'draft', 'pending', 'future' ),
			'posts_per_page'   => -1,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		)
	);
	$options    = array();

	foreach ( $characters as $character ) {
		if ( ! $character instanceof \WP_Post || ! can_select_character( $character->ID ) ) {
			continue;
		}

		$options[ $character->ID ] = get_the_title( $character );
	}

	if ( $current_id && ! isset( $options[ $current_id ] ) && can_select_character( $current_id ) ) {
		$options[ $current_id ] = get_the_title( $current_id );
	}

	natcasesort( $options );

	return $options;
}

/**
 * Determine whether the current editor may use a Character as a Journal relation.
 */
function can_select_character( int $character_id ): bool {
	return JournalAuthorship\user_can_author_as_character( get_current_user_id(), $character_id );
}

/**
 * Render rich text field with wp_editor().
 *
 * @param int                  $post_id Post ID.
 * @param array<string,string> $field   Field definition.
 */
function render_rich_text_field( int $post_id, array $field ): void {
	render_nonce();

	$value = (string) get_post_meta( $post_id, $field['key'], true );

	wp_editor(
		$value,
		$field['key'],
		array(
			'textarea_name' => $field['key'],
			'textarea_rows' => 12,
			'media_buttons' => true,
		)
	);
}

/**
 * Character identity meta box.
 *
 * @param \WP_Post $post Post object.
 */
function render_character_identity_box( \WP_Post $post ): void {
	render_fields( $post->ID, CharacterFields\get_character_field_groups()['identity'] );
}

/**
 * Character role meta box.
 *
 * @param \WP_Post $post Post object.
 */
function render_character_role_box( \WP_Post $post ): void {
	render_fields( $post->ID, CharacterFields\get_character_field_groups()['role'] );
}

/**
 * Character traits meta box.
 *
 * @param \WP_Post $post Post object.
 */
function render_character_traits_box( \WP_Post $post ): void {
	render_fields( $post->ID, CharacterFields\get_character_field_groups()['traits'] );
}

/**
 * Character abilities meta box.
 *
 * @param \WP_Post $post Post object.
 */
function render_character_abilities_box( \WP_Post $post ): void {
	render_fields( $post->ID, CharacterFields\get_character_field_groups()['abilities'] );
}

/**
 * Character full sheet meta box.
 *
 * @param \WP_Post $post Post object.
 */
function render_character_full_sheet_box( \WP_Post $post ): void {
	$field = CharacterFields\get_character_field_groups()['full_sheet'][0];
	render_rich_text_field( $post->ID, $field );
}

/**
 * Character public notes meta box.
 *
 * @param \WP_Post $post Post object.
 */
function render_character_public_notes_box( \WP_Post $post ): void {
	$field = CharacterFields\get_character_field_groups()['public_notes'][0];
	render_rich_text_field( $post->ID, $field );
}

/**
 * Wiki details meta box.
 *
 * @param \WP_Post $post Post object.
 */
function render_wiki_details_box( \WP_Post $post ): void {
	render_fields( $post->ID, CharacterFields\get_wiki_fields() );
}

/**
 * Storyguide notes meta box.
 *
 * @param \WP_Post $post Post object.
 */
function render_storyguide_notes_box( \WP_Post $post ): void {
	render_nonce();

	if ( ! PrivateNotes\can_view_storyguide_notes() ) {
		echo '<p>' . esc_html__( 'Only Storyguides and Administrators can see this.', 'ligatura-manuscripti-illuminati' ) . '</p>';
		return;
	}

	echo '<p class="manuscriptum-illuminatum-admin-private-warning">' . esc_html__( 'Only Storyguides and Administrators can see this.', 'ligatura-manuscripti-illuminati' ) . '</p>';

	wp_editor(
		(string) get_post_meta( $post->ID, PrivateNotes\META_KEY, true ),
		PrivateNotes\META_KEY,
		array(
			'textarea_name' => PrivateNotes\META_KEY,
			'textarea_rows' => 10,
			'media_buttons' => false,
		)
	);
}

/**
 * Journal details meta box.
 *
 * @param \WP_Post $post Post object.
 */
function render_diary_details_box( \WP_Post $post ): void {
	render_fields( $post->ID, CharacterFields\get_diary_fields() );
}

/**
 * Covenant Record details meta box.
 *
 * @param \WP_Post $post Post object.
 */
function render_covenant_details_box( \WP_Post $post ): void {
	render_fields( $post->ID, CharacterFields\get_covenant_fields() );
}

/**
 * Save all campaign meta boxes.
 *
 * @param int      $post_id Post ID.
 * @param \WP_Post $post    Post object.
 */
function save_meta_boxes( int $post_id, \WP_Post $post ): void {
	if ( ! in_array( $post->post_type, array( 'ligatura_character', 'ligatura_wiki', 'ligatura_diary', 'ligatura_covenant' ), true ) ) {
		return;
	}

	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST[ NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ NONCE_NAME ] ) ), NONCE_ACTION ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( 'ligatura_character' === $post->post_type ) {
		save_fields( $post_id, CharacterFields\get_character_fields() );
	}

	if ( 'ligatura_wiki' === $post->post_type ) {
		save_fields( $post_id, CharacterFields\get_wiki_fields() );
		save_storyguide_notes( $post_id );
	}

	if ( 'ligatura_diary' === $post->post_type ) {
		save_fields( $post_id, CharacterFields\get_diary_fields() );
	}

	if ( 'ligatura_covenant' === $post->post_type ) {
		save_fields( $post_id, CharacterFields\get_covenant_fields() );
	}
}

/**
 * Save a field collection.
 *
 * @param int                         $post_id Post ID.
 * @param array<int,array<string,string>> $fields  Field definitions.
 */
function save_fields( int $post_id, array $fields ): void {
	foreach ( $fields as $field ) {
		$value     = $_POST[ $field['key'] ] ?? '';
		$raw_value = is_scalar( $value ) ? (string) $value : '';

		if ( 'saga_date' === $field['sanitize'] && '' !== trim( $raw_value ) && ! JournalDates\is_valid_saga_date( sanitize_text_field( wp_unslash( $raw_value ) ) ) ) {
			continue;
		}

		$sanitized = call_user_func( get_sanitize_callback( $field['sanitize'] ), $value );

		if ( str_ends_with( $field['type'], '_multiselect' ) ) {
			$sanitized = validate_relationship_ids( $post_id, $field['key'], parse_id_list( $value ) );

			if ( '' === $sanitized ) {
				delete_post_meta( $post_id, $field['key'] );
				continue;
			}
		}

		if ( 'ligatura_diary_character' === $field['key'] ) {
			if ( $sanitized > 0 && ! can_select_character( $sanitized ) ) {
				continue;
			}

			if ( 0 === $sanitized ) {
				delete_post_meta( $post_id, $field['key'] );
				continue;
			}
		}

		update_post_meta( $post_id, $field['key'], $sanitized );
	}
}

/**
 * Validate submitted relationships against their configured entity source.
 *
 * @param int   $post_id Current Speculum entry ID.
 * @param int[] $ids     Submitted entity IDs.
 */
function validate_relationship_ids( int $post_id, string $field_key, array $ids ): string {
	$allowed = array_keys( get_relationship_select_options( $post_id, $field_key ) );
	$valid   = array_values( array_intersect( $ids, $allowed ) );

	return implode( ',', array_unique( array_map( 'absint', $valid ) ) );
}

/**
 * Save Storyguide notes with extra capability checks.
 *
 * @param int $post_id Post ID.
 */
function save_storyguide_notes( int $post_id ): void {
	if ( ! PrivateNotes\can_view_storyguide_notes() ) {
		return;
	}

	$value = $_POST[ PrivateNotes\META_KEY ] ?? '';
	update_post_meta( $post_id, PrivateNotes\META_KEY, sanitize_rich_text( $value ) );
}
