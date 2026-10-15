<?php
/**
 * Player role administration, Persona assignment, and least-privilege access.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\PlayerAccess;

use LigaturaManuscriptiIlluminati\RolesCapabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ASSIGNED_PLAYER_META      = '_ligatura_assigned_player';
const NONCE_ACTION              = 'ligatura_save_assigned_player';
const NONCE_NAME                = 'ligatura_assigned_player_nonce';

/**
 * Whether a user has the dedicated Player role.
 */
function is_player( int $user_id = 0 ): bool {
	$user = get_userdata( $user_id ?: get_current_user_id() );

	return $user instanceof \WP_User && in_array( RolesCapabilities\PLAYER_ROLE, $user->roles, true );
}

/**
 * Read a primitive capability without invoking WordPress capability mapping.
 *
 * This helper is safe to call from map_meta_cap callbacks. Calling user_can(),
 * current_user_can(), or WP_User::has_cap() here would re-enter map_meta_cap.
 */
function user_has_primitive_capability( int $user_id, string $capability ): bool {
	$user = $user_id > 0 ? get_userdata( $user_id ) : false;

	return $user instanceof \WP_User && ! empty( $user->allcaps[ $capability ] );
}

/**
 * Whether a user may administer Persona assignment.
 */
function can_manage_assignments( int $user_id = 0 ): bool {
	$user_id = $user_id ?: get_current_user_id();

	return $user_id > 0
		&& ( user_has_primitive_capability( $user_id, 'ligatura_assign_persona_players' )
			|| user_has_primitive_capability( $user_id, 'manage_options' ) );
}

/**
 * Players with a separate campaign-management grant are not restricted here.
 */
function is_restricted_player( int $user_id = 0 ): bool {
	$user_id = $user_id ?: get_current_user_id();

	return is_player( $user_id ) && ! can_manage_assignments( $user_id );
}

/**
 * Resolve the default administrator without assuming a numeric user ID.
 */
function default_administrator_id( int $excluded_user_id = 0 ): int {
	$admin_email = sanitize_email( (string) get_option( 'admin_email' ) );
	$admin       = $admin_email ? get_user_by( 'email', $admin_email ) : false;

	if ( $admin instanceof \WP_User
		&& $admin->ID !== $excluded_user_id
		&& ( in_array( 'administrator', $admin->roles, true ) || ! empty( $admin->allcaps['manage_options'] ) )
	) {
		return $admin->ID;
	}

	$administrators = get_users(
		array(
			'role'    => 'administrator',
			'orderby' => 'ID',
			'order'   => 'ASC',
		)
	);

	foreach ( $administrators as $candidate ) {
		if ( $candidate instanceof \WP_User && $candidate->ID !== $excluded_user_id && ! empty( $candidate->allcaps['manage_options'] ) ) {
			return $candidate->ID;
		}
	}

	return 0;
}

/**
 * Whether an account is eligible to own assigned Personae.
 */
function is_valid_assigned_player( int $user_id ): bool {
	return $user_id > 0 && get_userdata( $user_id ) instanceof \WP_User && ( is_player( $user_id ) || can_manage_assignments( $user_id ) );
}

/**
 * Sanitize an assignment, falling back to the configured administrator.
 */
function sanitize_assigned_player_id( mixed $value ): int {
	$user_id = absint( $value );

	return is_valid_assigned_player( $user_id ) ? $user_id : default_administrator_id();
}

/**
 * Return a Persona's effective assigned user.
 */
function get_assigned_player_id( int $character_id ): int {
	if ( 'ligatura_character' !== get_post_type( $character_id ) ) {
		return 0;
	}

	$assigned_user_id = absint( get_post_meta( $character_id, ASSIGNED_PLAYER_META, true ) );

	return is_valid_assigned_player( $assigned_user_id ) ? $assigned_user_id : default_administrator_id();
}

/**
 * Whether a user is the persisted effective assignee for a Persona.
 */
function user_is_assigned_to_character( int $user_id, int $character_id ): bool {
	return $user_id > 0 && 'ligatura_character' === get_post_type( $character_id ) && get_assigned_player_id( $character_id ) === $user_id;
}

/**
 * Register the private, administrator-controlled assignment metadata.
 */
function register_assignment_meta(): void {
	register_post_meta(
		'ligatura_character',
		ASSIGNED_PLAYER_META,
		array(
			'type'              => 'integer',
			'single'            => true,
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_assigned_player_id',
			'auth_callback'     => __NAMESPACE__ . '\\can_access_assignment_meta',
			'show_in_rest'      => true,
		)
	);
}

/**
 * Keep assignment metadata out of Player-readable REST responses and writes.
 */
function can_access_assignment_meta( mixed $allowed, string $meta_key, int $object_id, int $user_id = 0, string $cap = 'read_post_meta' ): bool {
	unset( $allowed, $meta_key );

	$user_id = $user_id ?: get_current_user_id();

	return can_manage_assignments( $user_id ) && user_can( $user_id, str_contains( $cap, 'read' ) ? 'read_post' : 'edit_post', $object_id );
}

/**
 * Eligible assignment choices keyed by user ID and sorted by Nickname.
 *
 * @return array<int,string>
 */
function assignment_choices(): array {
	$choices = array();

	foreach ( get_users( array( 'orderby' => 'ID', 'order' => 'ASC' ) ) as $user ) {
		if ( ! $user instanceof \WP_User || ! is_valid_assigned_player( $user->ID ) ) {
			continue;
		}

		$nickname = trim( sanitize_text_field( (string) get_user_meta( $user->ID, 'nickname', true ) ) );
		$choices[ $user->ID ] = '' !== $nickname ? $nickname : sprintf( __( 'User %d', 'ligatura-manuscripti-illuminati' ), $user->ID );
	}

	uasort( $choices, 'strnatcasecmp' );

	return $choices;
}

/**
 * Add the assignment selector only for privileged campaign managers.
 */
function register_assignment_meta_box(): void {
	if ( ! can_manage_assignments() ) {
		return;
	}

	add_meta_box(
		'manuscriptum-illuminatum-assigned-player',
		__( 'Assigned Player', 'ligatura-manuscripti-illuminati' ),
		__NAMESPACE__ . '\\render_assignment_meta_box',
		'ligatura_character',
		'side',
		'high'
	);
}

/**
 * Render a nickname-only user selector.
 */
function render_assignment_meta_box( \WP_Post $post ): void {
	wp_nonce_field( NONCE_ACTION, NONCE_NAME );

	$current_id = get_assigned_player_id( $post->ID );

	echo '<label class="screen-reader-text" for="' . esc_attr( ASSIGNED_PLAYER_META ) . '">' . esc_html__( 'Assigned Player', 'ligatura-manuscripti-illuminati' ) . '</label>';
	echo '<select id="' . esc_attr( ASSIGNED_PLAYER_META ) . '" name="' . esc_attr( ASSIGNED_PLAYER_META ) . '" class="widefat">';
	echo '<option value="">' . esc_html__( 'Default site administrator', 'ligatura-manuscripti-illuminati' ) . '</option>';

	foreach ( assignment_choices() as $user_id => $nickname ) {
		printf(
			'<option value="%1$d"%2$s>%3$s</option>',
			absint( $user_id ),
			selected( $current_id, $user_id, false ),
			esc_html( $nickname )
		);
	}

	echo '</select>';
	echo '<p class="description">' . esc_html__( 'This account may edit the Persona and use it as a Character Author.', 'ligatura-manuscripti-illuminati' ) . '</p>';
}

/**
 * Save assignment only for authorized managers; clearing means the default admin.
 */
function save_assignment( int $post_id, \WP_Post $post ): void {
	if ( 'ligatura_character' !== $post->post_type || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! array_key_exists( ASSIGNED_PLAYER_META, $_POST ) ) {
		return;
	}

	if ( ! isset( $_POST[ NONCE_NAME ] ) || ! is_scalar( $_POST[ NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ NONCE_NAME ] ) ), NONCE_ACTION ) ) {
		wp_die( esc_html__( 'The Persona assignment save could not be verified. Reload the editor and try again.', 'ligatura-manuscripti-illuminati' ), '', array( 'response' => 403 ) );
	}

	if ( ! can_manage_assignments() || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You cannot change Persona assignments.', 'ligatura-manuscripti-illuminati' ), '', array( 'response' => 403 ) );
	}

	if ( ! is_scalar( $_POST[ ASSIGNED_PLAYER_META ] ) ) {
		wp_die( esc_html__( 'Choose a valid Persona assignment.', 'ligatura-manuscripti-illuminati' ), '', array( 'response' => 400 ) );
	}

	$assignment = sanitize_assigned_player_id( $_POST[ ASSIGNED_PLAYER_META ] );

	if ( $assignment > 0 ) {
		update_post_meta( $post_id, ASSIGNED_PLAYER_META, $assignment );
	}
}

/**
 * Ensure a new/updated Persona never remains orphaned.
 */
function ensure_character_assignment( int $post_id, \WP_Post $post ): void {
	if ( 'ligatura_character' !== $post->post_type ) {
		return;
	}

	$stored = absint( get_post_meta( $post_id, ASSIGNED_PLAYER_META, true ) );

	if ( is_valid_assigned_player( $stored ) ) {
		return;
	}

	$default_id = default_administrator_id();

	if ( $default_id > 0 ) {
		update_post_meta( $post_id, ASSIGNED_PLAYER_META, $default_id );
	}
}

/**
 * Reassign affected Personae before an assigned account is deleted.
 */
function reassign_deleted_user_personae( int $user_id ): void {
	$default_id = default_administrator_id( $user_id );

	if ( $default_id < 1 ) {
		error_log( sprintf( '[Ligatura Manuscripti Illuminati] Unable to reassign Personae before deleting user %d: no alternate administrator exists.', $user_id ) );
		return;
	}

	$character_ids = get_posts(
		array(
			'post_type'      => 'ligatura_character',
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future', 'trash' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => ASSIGNED_PLAYER_META,
			'meta_value'     => $user_id,
			'no_found_rows'  => true,
		)
	);

	foreach ( $character_ids as $character_id ) {
		update_post_meta( $character_id, ASSIGNED_PLAYER_META, $default_id );
	}
}

/**
 * Keep the author-preserving permission bridge local to one verified form save.
 *
 * @param array<string,int>|null $context Verified post and current user IDs.
 * @return array<string,int>
 */
function player_character_form_context( ?array $context = null ): array {
	static $verified = array();

	if ( null !== $context ) {
		$verified = $context;
	}

	return $verified;
}

/**
 * Preserve technical ownership when a Player submits an assigned Persona form.
 *
 * Core's _wp_translate_postdata() checks edit_others_posts even when an existing
 * other author's ID is unchanged. Validate this particular form before allowing
 * that narrow check; Players still cannot select a different author or status.
 */
function prepare_player_character_form(): void {
	global $pagenow;

	if ( 'post.php' !== $pagenow || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! is_restricted_player() ) {
		return;
	}

	if ( ! isset( $_POST['action'], $_POST['post_ID'] ) || ! is_scalar( $_POST['action'] ) || ! is_scalar( $_POST['post_ID'] ) || 'editpost' !== wp_unslash( (string) $_POST['action'] ) ) {
		return;
	}

	$post_id = absint( $_POST['post_ID'] );
	$post    = get_post( $post_id );
	$user_id = get_current_user_id();

	if ( ! $post instanceof \WP_Post || 'ligatura_character' !== $post->post_type || ! user_is_assigned_to_character( $user_id, $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['_wpnonce'] ) || ! is_scalar( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'update-post_' . $post_id ) ) {
		wp_die( esc_html__( 'The Persona save could not be verified. Reload the editor and try again.', 'ligatura-manuscripti-illuminati' ), '', array( 'response' => 403 ) );
	}

	if ( isset( $_POST['post_type'] ) && ( ! is_scalar( $_POST['post_type'] ) || 'ligatura_character' !== wp_unslash( (string) $_POST['post_type'] ) ) ) {
		wp_die( esc_html__( 'Players cannot change a Persona post type.', 'ligatura-manuscripti-illuminati' ), '', array( 'response' => 403 ) );
	}

	foreach ( array( 'post_author', 'post_author_override' ) as $author_field ) {
		if ( isset( $_POST[ $author_field ] ) && ( ! is_scalar( $_POST[ $author_field ] ) || (int) $_POST[ $author_field ] !== (int) $post->post_author ) ) {
			wp_die( esc_html__( 'Players cannot change a Persona author.', 'ligatura-manuscripti-illuminati' ), '', array( 'response' => 403 ) );
		}
	}

	// edit_post() translates visibility before its status checks. Preserve both
	// visibility and password, even when other core checks would silently drop them.
	$visibility = 'public';
	if ( '' !== $post->post_password ) {
		$visibility = 'password';
	}
	if ( 'private' === $post->post_status ) {
		$visibility = 'private';
	}

	if ( ( isset( $_POST['visibility'] ) && ( ! is_scalar( $_POST['visibility'] ) || $visibility !== wp_unslash( (string) $_POST['visibility'] ) ) )
		|| ( isset( $_POST['post_password'] ) && ( ! is_scalar( $_POST['post_password'] ) || $post->post_password !== wp_unslash( (string) $_POST['post_password'] ) ) )
	) {
		wp_die( esc_html__( 'Players cannot change a Persona visibility or password.', 'ligatura-manuscripti-illuminati' ), '', array( 'response' => 403 ) );
	}

	// A changed publication date can turn publish into future (or the reverse)
	// without a changed post_status. Accept unchanged hidden editor date fields,
	// but reject either direct date writes or the components core translates.
	foreach ( array( 'post_date', 'post_date_gmt' ) as $date_field ) {
		if ( isset( $_POST[ $date_field ] ) && ( ! is_scalar( $_POST[ $date_field ] ) || $post->$date_field !== str_replace( 'T', ' ', trim( wp_unslash( (string) $_POST[ $date_field ] ) ) ) ) ) {
			wp_die( esc_html__( 'Players cannot change a Persona publication date.', 'ligatura-manuscripti-illuminati' ), '', array( 'response' => 403 ) );
		}
	}

	$date_parts = sscanf( $post->post_date, '%d-%d-%d %d:%d:%d' );

	foreach ( array( 'aa', 'mm', 'jj', 'hh', 'mn', 'ss' ) as $index => $date_part ) {
		if ( isset( $_POST[ $date_part ] ) && ( ! is_scalar( $_POST[ $date_part ] ) || ! preg_match( '/^\d+$/', (string) $_POST[ $date_part ] ) || (int) $_POST[ $date_part ] !== ( $date_parts[ $index ] ?? -1 ) ) ) {
			wp_die( esc_html__( 'Players cannot change a Persona publication date.', 'ligatura-manuscripti-illuminati' ), '', array( 'response' => 403 ) );
		}

		// Hidden values can independently activate core's date reconstruction,
		// including with missing components. The stored direct dates remain final.
		unset( $_POST[ 'hidden_' . $date_part ] );
	}
	unset( $_POST['edit_date'] );

	$status = $post->post_status;

	if ( isset( $_POST['post_status'] ) ) {
		$status = is_scalar( $_POST['post_status'] ) ? sanitize_key( wp_unslash( (string) $_POST['post_status'] ) ) : '';
	}

	// Account for the alternate status buttons accepted by core's form translator.
	foreach ( array( 'saveasdraft' => 'draft', 'saveasprivate' => 'private', 'publish' => 'publish', 'advanced' => 'draft', 'pending' => 'pending' ) as $button => $button_status ) {
		if ( isset( $_POST[ $button ] ) && '' !== $_POST[ $button ] && ! ( 'publish' === $button && 'private' === $status ) ) {
			$status = $button_status;
		}
	}

	if ( $status !== $post->post_status ) {
		wp_die( esc_html__( 'Players cannot change the publication status of assigned Personae.', 'ligatura-manuscripti-illuminati' ), '', array( 'response' => 403 ) );
	}

	// Core otherwise defaults an omitted post_author to the current Player.
	$_POST['post_type']     = $post->post_type;
	$_POST['post_author']   = (string) $post->post_author;
	$_POST['post_date']     = $post->post_date;
	$_POST['post_date_gmt'] = $post->post_date_gmt;
	player_character_form_context( array( 'post_id' => $post_id, 'user_id' => $user_id ) );
}

/**
 * Enforce assignment/ownership at WordPress's post meta-capability boundary.
 *
 * @param string[] $caps Required primitive capabilities.
 * @param mixed[]  $args Capability arguments.
 * @return string[]
 */
function map_player_post_meta_caps( array $caps, string $cap, int $user_id, array $args ): array {
	if ( 'edit_others_ligatura_characters' === $cap ) {
		$context = player_character_form_context();

		if ( $user_id === get_current_user_id()
			&& $user_id === ( $context['user_id'] ?? 0 )
			&& is_restricted_player( $user_id )
			&& user_is_assigned_to_character( $user_id, $context['post_id'] ?? 0 )
		) {
			return array( 'ligatura_edit_assigned_personae' );
		}

		return $caps;
	}

	$owned_capabilities = array(
		'edit_post',
		'delete_post',
		'edit_ligatura_character',
		'delete_ligatura_character',
		'edit_ligatura_diary_entry',
		'delete_ligatura_diary_entry',
	);

	if ( ! in_array( $cap, $owned_capabilities, true ) || empty( $args[0] ) ) {
		return $caps;
	}

	if ( ! is_restricted_player( $user_id ) ) {
		return $caps;
	}

	$post_id = absint( $args[0] );
	$post    = get_post( $post_id );

	if ( ! $post instanceof \WP_Post ) {
		return $caps;
	}

	$is_edit   = in_array( $cap, array( 'edit_post', 'edit_ligatura_character', 'edit_ligatura_diary_entry' ), true );
	$is_delete = in_array( $cap, array( 'delete_post', 'delete_ligatura_character', 'delete_ligatura_diary_entry' ), true );

	if ( 'ligatura_character' === $post->post_type ) {
		if ( $is_delete ) {
			return array( 'do_not_allow' );
		}

		if ( $is_edit ) {
			return user_is_assigned_to_character( $user_id, $post_id ) ? array( 'ligatura_edit_assigned_personae' ) : array( 'do_not_allow' );
		}
	}

	if ( 'ligatura_diary' === $post->post_type && ( $is_edit || $is_delete ) && (int) $post->post_author !== $user_id ) {
		return array( 'do_not_allow' );
	}

	return $caps;
}

/**
 * Restrict Player admin collections to assigned Personae, owned Journals/media.
 */
function scope_player_admin_queries( \WP_Query $query ): void {
	if ( ! is_admin() || ! is_restricted_player() || ! $query->is_main_query() ) {
		return;
	}

	$post_type = $query->get( 'post_type' );

	if ( 'ligatura_diary' === $post_type ) {
		$query->set( 'author', get_current_user_id() );
	} elseif ( 'ligatura_character' === $post_type ) {
		apply_player_character_query_scope( $query, get_current_user_id() );
	} elseif ( 'attachment' === $post_type ) {
		$query->set( 'author', get_current_user_id() );
	}
}

/**
 * Scope a Character collection by assignment rather than technical post author.
 */
function apply_player_character_query_scope( \WP_Query $query, int $user_id ): void {
	$query->set( 'author', '' );
	$query->set( 'author_name', '' );
	$query->set( 'meta_key', ASSIGNED_PLAYER_META );
	$query->set( 'meta_value', $user_id );
}

/**
 * Restrict Player edit-context REST collections for technical ownership privacy.
 *
 * @param array<string,mixed> $args Query arguments.
 * @return array<string,mixed>
 */
function scope_player_journal_rest_query( array $args, \WP_REST_Request $request ): array {
	if ( is_restricted_player() && 'edit' === $request->get_param( 'context' ) ) {
		$args['author'] = get_current_user_id();
	}

	return $args;
}

/**
 * Restrict Player Persona edit-context collections to persisted assignments.
 *
 * @param array<string,mixed> $args Query arguments.
 * @return array<string,mixed>
 */
function scope_player_character_rest_query( array $args, \WP_REST_Request $request ): array {
	if ( is_restricted_player() && 'edit' === $request->get_param( 'context' ) ) {
		unset( $args['author'], $args['author_name'] );
		$args['post_status'] = 'any';
		$args['meta_key']   = ASSIGNED_PLAYER_META;
		$args['meta_value'] = get_current_user_id();
	}

	return $args;
}

/**
 * Restrict the modal Media Library to Player-owned uploads.
 *
 * @param array<string,mixed> $query Query arguments.
 * @return array<string,mixed>
 */
function scope_player_attachment_ajax_query( array $query ): array {
	if ( is_restricted_player() ) {
		$query['author'] = get_current_user_id();
	}

	return $query;
}

/**
 * Restrict the REST Media Library collection to Player-owned uploads.
 *
 * @param array<string,mixed> $args Query arguments.
 * @return array<string,mixed>
 */
function scope_player_attachment_rest_query( array $args, ?\WP_REST_Request $request = null ): array {
	unset( $request );
	if ( is_restricted_player() ) {
		$args['author'] = get_current_user_id();
	}

	return $args;
}

/**
 * Whether a Player may newly select an attachment they uploaded themselves.
 */
function player_can_select_attachment( int $user_id, int $attachment_id ): bool {
	return $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) && (int) get_post_field( 'post_author', $attachment_id ) === $user_id;
}

/**
 * Reject crafted REST attempts to select another user's media.
 *
 * @param mixed            $prepared_post Prepared post or existing error.
 * @param \WP_REST_Request $request       REST request.
 * @return mixed
 */
function validate_player_featured_media( mixed $prepared_post, \WP_REST_Request $request ): mixed {
	if ( is_wp_error( $prepared_post ) || ! is_restricted_player() || ! $request->has_param( 'featured_media' ) ) {
		return $prepared_post;
	}

	$post_id       = absint( $request['id'] ?? 0 );
	$current_media = $post_id > 0 ? get_post_thumbnail_id( $post_id ) : 0;
	$new_media     = absint( $request['featured_media'] );

	if ( 0 === $new_media || $new_media === $current_media || player_can_select_attachment( get_current_user_id(), $new_media ) ) {
		return $prepared_post;
	}

	return new \WP_Error( 'ligatura_player_media_forbidden', __( 'You may select only media that you uploaded.', 'ligatura-manuscripti-illuminati' ), array( 'status' => 403 ) );
}

/**
 * Explicitly reject Player attempts to submit privileged assignment metadata.
 */
function reject_player_assignment_rest_write( mixed $prepared_post, \WP_REST_Request $request ): mixed {
	if ( is_wp_error( $prepared_post ) || ! is_restricted_player() ) {
		return $prepared_post;
	}

	$meta = $request->get_param( 'meta' );

	if ( is_array( $meta ) && array_key_exists( ASSIGNED_PLAYER_META, $meta ) ) {
		return new \WP_Error(
			'ligatura_player_assignment_forbidden',
			__( 'Players cannot change Persona assignments.', 'ligatura-manuscripti-illuminati' ),
			array( 'status' => 403 )
		);
	}

	return $prepared_post;
}

/**
 * Keep an assigned published Persona published when Gutenberg sends its current status.
 *
 * Gutenberg uses the broad publish capability to decide whether an existing public
 * post gets an Update or Submit for Review action. Players intentionally lack that
 * capability. For an assigned Persona that is already published, remove only the
 * no-op `status: publish` field before core performs its broad publication check.
 * Any attempted status transition remains forbidden.
 *
 * @param mixed                $response Existing REST response or error.
 * @param array<string,mixed>  $handler  Matched REST route handler.
 * @param \WP_REST_Request     $request  Matched REST request.
 * @return mixed
 */
function preserve_player_published_character_status( mixed $response, array $handler, \WP_REST_Request $request ): mixed {
	unset( $handler );

	if ( is_wp_error( $response ) || ! is_restricted_player() || ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
		return $response;
	}

	if ( ! preg_match( '#^/wp/v2/ligatura_character/(?P<id>\d+)$#', $request->get_route(), $matches ) || ! $request->has_param( 'status' ) ) {
		return $response;
	}

	$post_id = absint( $matches['id'] );
	$post    = get_post( $post_id );

	if ( ! $post instanceof \WP_Post || 'ligatura_character' !== $post->post_type || 'publish' !== $post->post_status || ! user_is_assigned_to_character( get_current_user_id(), $post_id ) ) {
		return $response;
	}

	if ( 'publish' !== sanitize_key( (string) $request->get_param( 'status' ) ) ) {
		return new \WP_Error(
			'ligatura_player_character_status_forbidden',
			__( 'Players cannot change the publication status of assigned Personae.', 'ligatura-manuscripti-illuminati' ),
			array( 'status' => 403 )
		);
	}

	unset( $request['status'] );

	return $response;
}

/**
 * Tell Gutenberg that an assigned published Persona has an update action.
 *
 * This relation changes the editor action from Submit for Review to Update. It
 * does not grant `publish_ligatura_characters`; the REST guard above permits only a
 * no-op preservation of an already-published assigned Persona's status.
 */
function expose_player_character_update_action( \WP_REST_Response $response, \WP_Post $post, \WP_REST_Request $request ): \WP_REST_Response {
	if (
		is_restricted_player()
		&& 'edit' === $request->get_param( 'context' )
		&& 'publish' === $post->post_status
		&& user_is_assigned_to_character( get_current_user_id(), $post->ID )
	) {
		$response->add_link( 'https://api.w.org/action-publish', rest_url( rest_get_route_for_post( $post->ID ) ) );
	}

	return $response;
}

/**
 * Defend assignment and Campaign Image metadata in non-REST meta_input paths.
 *
 * @param mixed $check Existing short-circuit value.
 * @return mixed
 */
function protect_player_post_meta_update( mixed $check, int $object_id, string $meta_key, mixed $meta_value ): mixed {
	if ( null !== $check || ! is_restricted_player() ) {
		return $check;
	}

	if ( ASSIGNED_PLAYER_META === $meta_key ) {
		return false;
	}

	if ( '_thumbnail_id' !== $meta_key || ! in_array( get_post_type( $object_id ), array( 'ligatura_character', 'ligatura_diary' ), true ) ) {
		return $check;
	}

	$new_media     = absint( $meta_value );
	$current_media = get_post_thumbnail_id( $object_id );

	return $new_media === $current_media || player_can_select_attachment( get_current_user_id(), $new_media ) ? $check : false;
}

/**
 * Prevent non-REST removal of privileged assignment metadata.
 *
 * @param mixed $check Existing short-circuit value.
 * @return mixed
 */
function protect_player_post_meta_delete( mixed $check, int $object_id, string $meta_key ): mixed {
	unset( $object_id );

	if ( null === $check && is_restricted_player() && ASSIGNED_PLAYER_META === $meta_key ) {
		return false;
	}

	return $check;
}

/**
 * Hide irrelevant Player menus as a small-interface UX supplement.
 */
function restrict_player_admin_menu(): void {
	if ( ! is_restricted_player() ) {
		return;
	}

	global $menu;
	$allowed = array( 'edit.php?post_type=ligatura_diary', 'edit.php?post_type=ligatura_character', 'upload.php', 'profile.php' );

	foreach ( (array) $menu as $item ) {
		$slug = isset( $item[2] ) ? (string) $item[2] : '';

		if ( '' !== $slug && ! in_array( $slug, $allowed, true ) ) {
			remove_menu_page( $slug );
		}
	}
}

/**
 * Keep WordPress's generic Posts gate from shadowing the permitted Character list.
 *
 * Players cannot create Characters or manage Character taxonomies, so core removes
 * the sole remaining Character submenu and cannot resolve the custom post type's
 * parent. It then mistakes edit.php?post_type=ligatura_character for the forbidden Posts
 * screen. The plugin's admin_init allowlist still independently rejects edit.php
 * and every other unapproved post type before this menu check runs.
 */
function allow_player_character_collection_menu_gate(): void {
	if ( ! is_restricted_player() || ! current_user_can( 'edit_ligatura_characters' ) ) {
		return;
	}

	global $pagenow, $typenow, $_wp_menu_nopriv, $_wp_submenu_nopriv;

	if ( 'edit.php' !== $pagenow || 'ligatura_character' !== $typenow ) {
		return;
	}

	unset( $_wp_menu_nopriv['edit.php'], $_wp_submenu_nopriv['edit.php']['edit.php'] );
}

/**
 * Resolve Player admin routing without issuing a redirect or terminating PHP.
 *
 * Keeping the decision separate makes the one-way Dashboard redirect and
 * permitted landing screen directly regression-testable.
 */
function player_admin_screen_decision( string $page, string $post_type = '', int $post_id = 0 ): string {
	if ( 'index.php' === $page ) {
		return 'redirect';
	}

	if ( in_array( $page, array( 'profile.php', 'upload.php', 'media-new.php', 'async-upload.php' ), true ) ) {
		return 'allow';
	}

	if ( 'edit.php' === $page && in_array( $post_type, array( 'ligatura_diary', 'ligatura_character' ), true ) ) {
		return 'allow';
	}

	if ( 'post-new.php' === $page && 'ligatura_diary' === $post_type && current_user_can( 'create_ligatura_diary_entries' ) ) {
		return 'allow';
	}

	if ( 'post.php' === $page ) {
		$type = get_post_type( $post_id );

		if ( in_array( $type, array( 'ligatura_diary', 'ligatura_character', 'attachment' ), true ) && current_user_can( 'edit_post', $post_id ) ) {
			return 'allow';
		}
	}

	return 'deny';
}

/**
 * Redirect the otherwise-empty Player Dashboard and reject crafted admin URLs.
 */
function enforce_player_admin_screen_access(): void {
	if ( ! is_restricted_player() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}

	global $pagenow;
	$page      = (string) $pagenow;
	$post_type = isset( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authorization routing only.
	$post_id   = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authorization routing only.
	$decision  = player_admin_screen_decision( $page, $post_type, $post_id );

	if ( 'redirect' === $decision ) {
		wp_safe_redirect( admin_url( 'edit.php?post_type=ligatura_diary' ) );
		exit;
	}

	if ( 'allow' === $decision ) {
		return;
	}

	wp_die( esc_html__( 'You are not allowed to access this administration screen.', 'ligatura-manuscripti-illuminati' ), '', array( 'response' => 403 ) );
}

/**
 * Prevent Player editors from receiving a native technical-author selector.
 */
function remove_player_author_support(): void {
	if ( is_restricted_player() ) {
		remove_post_type_support( 'ligatura_diary', 'author' );
	}
}
