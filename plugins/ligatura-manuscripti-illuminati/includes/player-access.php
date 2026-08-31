<?php
/**
 * Player role administration, Persona assignment, and least-privilege access.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\PlayerAccess;

use LigaturaManuscriptiIlluminati\DemoContent;
use LigaturaManuscriptiIlluminati\RolesCapabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ASSIGNED_PLAYER_META      = '_ligatura_assigned_player';
const NONCE_ACTION              = 'ligatura_save_assigned_player';
const NONCE_NAME                = 'ligatura_assigned_player_nonce';
const REST_NAMESPACE            = 'ligatura-manuscripti-illuminati/v1';

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

	if ( ! isset( $_POST[ NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ NONCE_NAME ] ) ), NONCE_ACTION ) ) {
		return;
	}

	if ( ! can_manage_assignments() || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$assignment = sanitize_assigned_player_id( $_POST[ ASSIGNED_PLAYER_META ] ?? 0 );

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
 * Enforce assignment/ownership at WordPress's post meta-capability boundary.
 *
 * @param string[] $caps Required primitive capabilities.
 * @param mixed[]  $args Capability arguments.
 * @return string[]
 */
function map_player_post_meta_caps( array $caps, string $cap, int $user_id, array $args ): array {
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
