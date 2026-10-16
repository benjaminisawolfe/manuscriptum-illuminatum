<?php
/**
 * Character document relationships and narrowly scoped native uploads.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\CharacterDocuments;

use LigaturaManuscriptiIlluminati\PlayerAccess;
use LigaturaManuscriptiIlluminati\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META_KEY       = '_ligatura_character_documents';
const NONCE_NAME     = 'ligatura_character_documents_nonce';
const MAX_DOCUMENTS  = 50;
const MIME_READ_SIZE = 262144;

/**
 * Keep this relationship out of core REST writes and the generic custom-field UI.
 * The dedicated routes and form saver validate the whole proposed list first.
 */
function register_meta(): void {
	register_post_meta(
		'ligatura_character',
		META_KEY,
		array(
			'type'          => 'array',
			'single'        => true,
			'default'       => array(),
			'show_in_rest'  => false,
			// Generic custom-field APIs (including XML-RPC) cannot validate document
			// ownership. Only the dedicated, nonce-checked savers below may write.
			'auth_callback' => '__return_false',
		)
	);
}

/**
 * The allowlist narrows the site's current WordPress policy; it never expands it.
 *
 * @return array<string,string>
 */
function allowed_mimes(): array {
	$documents = array(
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'odt'  => 'application/vnd.oasis.opendocument.text',
		'rtf'  => 'application/rtf',
		'txt'  => 'text/plain',
	);
	$allowed = get_allowed_mime_types();

	foreach ( $documents as $extension => $mime ) {
		$core = wp_check_filetype( 'document.' . $extension, $allowed );
		if ( $core['type'] !== $mime ) {
			unset( $documents[ $extension ] );
		}
	}

	return $documents;
}

/**
 * Errors never include local filesystem paths or another user's attachment title.
 */
function document_error( string $code, string $message, int $status = 400 ): \WP_Error {
	return new \WP_Error( 'ligatura_document_' . $code, $message, array( 'status' => $status ) );
}

/**
 * Validate real content in addition to WordPress's filename/MIME checks.
 *
 * Core deliberately permits generic ZIP/binary MIME results for application/*.
 * Documents fail closed instead: an ordinary archive renamed .pdf/.docx is not
 * accepted. Bounded Fileinfo inspection avoids reading entire documents into PHP.
 * No document content is parsed as markup, extracted, or executed.
 *
 * @return array{extension:string,mime:string}|\WP_Error
 */
function validate_file( string $path, string $filename ): array|\WP_Error {
	if ( ! is_file( $path ) || ! is_readable( $path ) || filesize( $path ) < 1 ) {
		return document_error( 'unavailable', __( 'This document is unavailable or empty. Remove it from the list or upload it again.', 'ligatura-manuscripti-illuminati' ) );
	}

	$filename  = sanitize_file_name( wp_basename( $filename ) );
	$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	$mimes     = allowed_mimes();
	if ( ! isset( $mimes[ $extension ] ) ) {
		return document_error( 'type', __( 'Choose a PDF, DOC, DOCX, ODT, RTF, or TXT document permitted by this site.', 'ligatura-manuscripti-illuminati' ) );
	}

	$core = wp_check_filetype_and_ext( $path, $filename, $mimes );
	if ( $core['ext'] !== $extension || $core['type'] !== $mimes[ $extension ] || ! empty( $core['proper_filename'] ) ) {
		return document_error( 'type', __( 'The document contents do not match its filename or WordPress does not permit this file type.', 'ligatura-manuscripti-illuminati' ) );
	}

	if ( ! class_exists( '\finfo' ) ) {
		return document_error( 'unidentified', __( 'The server cannot safely identify document contents. Contact the site administrator.', 'ligatura-manuscripti-illuminati' ), 415 );
	}

	$sample = file_get_contents( $path, false, null, 0, MIME_READ_SIZE );
	if ( false === $sample ) {
		return document_error( 'unavailable', __( 'This document could not be read. Upload it again.', 'ligatura-manuscripti-illuminati' ) );
	}

	try {
		$detected = ( new \finfo( FILEINFO_MIME_TYPE ) )->buffer( $sample );
	} catch ( \Throwable $error ) {
		return document_error( 'unidentified', __( 'The server cannot safely identify this document format. Try a PDF or plain text export.', 'ligatura-manuscripti-illuminati' ), 415 );
	}

	$expected = array( $mimes[ $extension ] );
	if ( 'rtf' === $extension ) {
		$expected[] = 'text/rtf';
	}
	if ( ! in_array( $detected, $expected, true ) ) {
		return document_error( 'unidentified', __( 'The server cannot verify that this file is the document format named by its extension. Generic archives, binary files, and renamed files are not accepted. Try a PDF or plain text export.', 'ligatura-manuscripti-illuminati' ), 415 );
	}

	return array( 'extension' => $extension, 'mime' => $mimes[ $extension ] );
}

/**
 * Resolve an attachment only when its actual local file remains inside uploads.
 *
 * @return array<string,int|string>|\WP_Error
 */
function attachment_descriptor( int $attachment_id ): array|\WP_Error {
	$attachment = get_post( $attachment_id );
	if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type || 'trash' === $attachment->post_status ) {
		return document_error( 'unavailable', __( 'This document is no longer available. Remove it from the list or upload it again.', 'ligatura-manuscripti-illuminati' ) );
	}

	$uploads = wp_upload_dir( null, false );
	$path    = get_attached_file( $attachment_id, true );
	$root    = empty( $uploads['error'] ) ? realpath( $uploads['basedir'] ) : false;
	$real    = is_string( $path ) && '' !== $path ? realpath( $path ) : false;
	if ( false === $root || false === $real ) {
		return document_error( 'unavailable', __( 'This document is not available in the site upload storage.', 'ligatura-manuscripti-illuminati' ) );
	}

	$prefix = trailingslashit( wp_normalize_path( $root ) );
	$local  = wp_normalize_path( $real );
	if ( '\\' === DIRECTORY_SEPARATOR ) {
		$prefix = strtolower( $prefix );
		$local  = strtolower( $local );
	}
	if ( ! str_starts_with( $local, $prefix ) ) {
		return document_error( 'unavailable', __( 'This document is not available in the site upload storage.', 'ligatura-manuscripti-illuminati' ) );
	}

	$filename = sanitize_file_name( wp_basename( $path ) );
	$verified = validate_file( $real, $filename );
	if ( is_wp_error( $verified ) ) {
		return $verified;
	}
	if ( $attachment->post_mime_type !== $verified['mime'] ) {
		return document_error( 'type', __( 'The Media Library file type does not match this document. Upload it again.', 'ligatura-manuscripti-illuminati' ) );
	}

	$url = esc_url_raw( (string) wp_get_attachment_url( $attachment_id ), array( 'http', 'https' ) );
	if ( '' === $url ) {
		return document_error( 'unavailable', __( 'This document has no usable file link.', 'ligatura-manuscripti-illuminati' ) );
	}
	$title = trim( sanitize_text_field( $attachment->post_title ) );

	return array(
		'id'       => $attachment_id,
		'title'    => '' !== $title ? $title : $filename,
		'filename' => $filename,
		'type'     => strtoupper( $verified['extension'] ),
		'url'      => $url,
	);
}

/**
 * Read persisted IDs without making missing/invalid files disappear on a save.
 *
 * @return int[]
 */
function stored_ids( int $post_id ): array {
	$ids = get_post_meta( $post_id, META_KEY, true );
	if ( ! is_array( $ids ) ) {
		return array();
	}

	return array_values( array_unique( array_filter( $ids, static fn( mixed $id ): bool => is_int( $id ) && $id > 0 ) ) );
}

/**
 * Validate shape, ownership and content before changing any relationship.
 *
 * @return int[]|\WP_Error
 */
function validate_selection( mixed $ids, int $post_id ): array|\WP_Error {
	if ( ! is_array( $ids ) || array_values( $ids ) !== $ids || count( $ids ) > MAX_DOCUMENTS ) {
		return document_error( 'list', __( 'Choose a list of no more than 50 documents.', 'ligatura-manuscripti-illuminati' ) );
	}

	$existing = stored_ids( $post_id );
	$selected = array();
	foreach ( $ids as $id ) {
		if ( ! is_int( $id ) || $id < 1 ) {
			return document_error( 'list', __( 'The document selection is invalid. Reload the editor and try again.', 'ligatura-manuscripti-illuminati' ) );
		}
		if ( PlayerAccess\is_restricted_player() && ! in_array( $id, $existing, true ) && ! PlayerAccess\player_can_select_attachment( get_current_user_id(), $id ) ) {
			return document_error( 'forbidden', __( 'You may attach only documents that you uploaded, or retain documents already linked to this Character.', 'ligatura-manuscripti-illuminati' ), 403 );
		}
		$document = attachment_descriptor( $id );
		if ( is_wp_error( $document ) ) {
			return $document;
		}
		$selected[] = $id;
	}

	return array_values( array_unique( $selected ) );
}

/**
 * A public-to-authorized-visitors helper; callers never receive invalid links.
 *
 * @return array<int,array<string,int|string>>
 */
function get_documents( int $post_id ): array {
	if ( 'ligatura_character' !== get_post_type( $post_id ) || ! Privacy\can_read_post( $post_id ) || post_password_required( $post_id ) ) {
		return array();
	}

	$documents = array();
	foreach ( stored_ids( $post_id ) as $id ) {
		$document = attachment_descriptor( $id );
		if ( ! is_wp_error( $document ) ) {
			$documents[] = $document;
		}
	}
	return $documents;
}

/**
 * Relationship writes require an explicit field; omission always preserves it.
 */
function save_form( int $post_id, \WP_Post $post ): void {
	if ( 'ligatura_character' !== $post->post_type || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id )
		|| 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| ! array_key_exists( META_KEY, $_POST ) ) {
		return;
	}

	if ( ! isset( $_POST[ NONCE_NAME ] ) || ! is_string( $_POST[ NONCE_NAME ] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ NONCE_NAME ] ) ), 'ligatura_character_documents_' . $post_id )
		|| ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'The document save could not be authorized. Reload the editor and try again.', 'ligatura-manuscripti-illuminati' ), '', array( 'response' => 403 ) );
	}

	$ids = is_string( $_POST[ META_KEY ] ) ? json_decode( wp_unslash( $_POST[ META_KEY ] ) ) : null;
	$ids = validate_selection( $ids, $post_id );
	if ( is_wp_error( $ids ) ) {
		wp_die( esc_html( $ids->get_error_message() ), '', array( 'response' => $ids->get_error_data()['status'] ) );
	}
	$result = persist_selection( $post_id, $ids );
	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 500 ) );
	}
}

/**
 * Distinguish a failed database write from WordPress's unchanged-value result.
 *
 * @param int[] $ids Validated IDs.
 */
function persist_selection( int $post_id, array $ids ): bool|\WP_Error {
	if ( stored_ids( $post_id ) === $ids ) {
		return true;
	}
	update_post_meta( $post_id, META_KEY, $ids );
	return stored_ids( $post_id ) === $ids ? true : document_error( 'save_failed', __( 'The document list could not be saved. Try again.', 'ligatura-manuscripti-illuminati' ), 500 );
}

/**
 * Separate selection/upload endpoints keep core Media Library uploads unchanged.
 */
function register_rest_routes(): void {
	$base = '/characters/(?P<id>\d+)/documents';
	register_rest_route(
		'ligatura-manuscripti-illuminati/v1',
		$base,
		array(
			array( 'methods' => 'GET', 'callback' => __NAMESPACE__ . '\\rest_get_documents', 'permission_callback' => __NAMESPACE__ . '\\can_edit_documents' ),
			array( 'methods' => 'POST', 'callback' => __NAMESPACE__ . '\\rest_save_documents', 'permission_callback' => __NAMESPACE__ . '\\can_edit_documents' ),
		)
	);
	register_rest_route(
		'ligatura-manuscripti-illuminati/v1',
		$base . '/upload',
		array( 'methods' => 'POST', 'callback' => __NAMESPACE__ . '\\rest_upload_document', 'permission_callback' => __NAMESPACE__ . '\\can_edit_documents' )
	);
}

/**
 * Cookie-authenticated document requests use WordPress's REST nonce as well as caps.
 */
function can_edit_documents( \WP_REST_Request $request ): bool|\WP_Error {
	$post_id = absint( $request['id'] );
	if ( 'ligatura_character' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return document_error( 'forbidden', __( 'You are not allowed to edit documents for this Character.', 'ligatura-manuscripti-illuminati' ), 403 );
	}
	if ( 'GET' !== $request->get_method() && ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
		return document_error( 'nonce', __( 'The document request could not be verified. Reload the editor and try again.', 'ligatura-manuscripti-illuminati' ), 403 );
	}
	return true;
}

/**
 * Validate selected existing media before the browser adds it to the hidden list.
 */
function rest_get_documents( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
	$post_id = absint( $request['id'] );
	$ids     = stored_ids( $post_id );
	if ( $request->has_param( 'ids' ) ) {
		$raw = $request->get_param( 'ids' );
		if ( ! is_string( $raw ) || ( '' !== $raw && ! preg_match( '/^[1-9][0-9]*(?:,[1-9][0-9]*)*$/D', $raw ) ) ) {
			return document_error( 'list', __( 'The document selection is invalid.', 'ligatura-manuscripti-illuminati' ) );
		}
		$ids = '' === $raw ? array() : array_map( 'intval', explode( ',', $raw ) );
		$ids = validate_selection( $ids, $post_id );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}
	}

	$documents = array();
	foreach ( $ids as $id ) {
		$document = attachment_descriptor( $id );
		if ( ! is_wp_error( $document ) ) {
			$documents[] = $document;
		}
	}
	return new \WP_REST_Response( array( 'documents' => $documents ) );
}

/**
 * Explicit API updates use the same all-or-nothing validation as Character Save.
 */
function rest_save_documents( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
	if ( ! $request->has_param( 'ids' ) ) {
		return rest_get_documents( $request );
	}
	$post_id = absint( $request['id'] );
	$ids     = validate_selection( $request->get_param( 'ids' ), $post_id );
	if ( is_wp_error( $ids ) ) {
		return $ids;
	}
	$result = persist_selection( $post_id, $ids );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$documents = array_map( __NAMESPACE__ . '\\attachment_descriptor', $ids );
	return new \WP_REST_Response( array( 'documents' => $documents ) );
}

/**
 * Upload one native attachment; Character Save commits the relationship later.
 */
function rest_upload_document( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
	if ( ! current_user_can( 'upload_files' ) ) {
		return document_error( 'forbidden', __( 'You are not allowed to upload documents.', 'ligatura-manuscripti-illuminati' ), 403 );
	}

	$files = $request->get_file_params();
	$file  = $files['document'] ?? null;
	$limit = wp_max_upload_size();
	if ( ! is_array( $file ) || ! isset( $file['error'], $file['name'], $file['tmp_name'], $file['size'] )
		|| ! is_scalar( $file['error'] ) || ! is_scalar( $file['size'] ) || ! is_string( $file['name'] ) || ! is_string( $file['tmp_name'] ) ) {
		return document_error( 'upload', __( 'No document was received. The file may exceed the server upload limit. Select a smaller file and try again.', 'ligatura-manuscripti-illuminati' ) );
	}
	if ( in_array( (int) $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) || (int) $file['size'] > $limit ) {
		return document_error( 'too_large', sprintf( __( 'The document exceeds the upload limit of %s.', 'ligatura-manuscripti-illuminati' ), size_format( $limit ) ), 413 );
	}
	if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return document_error( 'upload', __( 'The document upload failed. Select the file and try again.', 'ligatura-manuscripti-illuminati' ) );
	}
	if ( filesize( $file['tmp_name'] ) > $limit ) {
		return document_error( 'too_large', sprintf( __( 'The document exceeds the upload limit of %s.', 'ligatura-manuscripti-illuminati' ), size_format( $limit ) ), 413 );
	}

	$verified = validate_file( $file['tmp_name'], $file['name'] );
	if ( is_wp_error( $verified ) ) {
		return $verified;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$attachment_id = media_handle_upload(
		'document',
		absint( $request['id'] ),
		array( 'post_author' => get_current_user_id() ),
		array( 'test_form' => false, 'mimes' => allowed_mimes() )
	);
	if ( is_wp_error( $attachment_id ) ) {
		return document_error( 'upload', __( 'WordPress could not store this document. Check the file type, upload size, and available storage, then try again.', 'ligatura-manuscripti-illuminati' ) );
	}

	$document = attachment_descriptor( $attachment_id );
	if ( is_wp_error( $document ) ) {
		// This is a newly created, unlinked failed upload, not an unlink operation.
		wp_delete_attachment( $attachment_id, true );
		return $document;
	}
	return new \WP_REST_Response( $document, 201 );
}

/**
 * Render the small document UI within the Character full-sheet meta box.
 */
function render_control( \WP_Post $post ): void {
	if ( 'ligatura_character' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
		return;
	}
	$ids = stored_ids( $post->ID );
	?>
	<div class="ligatura-character-documents" role="region" aria-label="<?php esc_attr_e( 'Character Documents', 'ligatura-manuscripti-illuminati' ); ?>" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>" data-rest-url="<?php echo esc_url( rest_url( 'ligatura-manuscripti-illuminati/v1/characters/' . $post->ID . '/documents' ) ); ?>" data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" data-max-bytes="<?php echo esc_attr( (string) wp_max_upload_size() ); ?>">
		<h3><?php esc_html_e( 'Character Documents', 'ligatura-manuscripti-illuminati' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Anyone with the file link can access these documents without logging in. Do not upload Storyguide-only or confidential material.', 'ligatura-manuscripti-illuminati' ); ?></p>
		<p class="description"><?php echo esc_html( sprintf( __( 'PDF, DOC, DOCX, ODT, RTF, or TXT. Up to 50 documents; maximum upload size: %s. Formats the server cannot safely identify are rejected. Save the Character to apply list changes; unlinking keeps the file in the Media Library.', 'ligatura-manuscripti-illuminati' ), size_format( wp_max_upload_size() ) ) ); ?></p>
		<?php wp_nonce_field( 'ligatura_character_documents_' . $post->ID, NONCE_NAME ); ?>
		<input type="hidden" name="<?php echo esc_attr( META_KEY ); ?>" value="<?php echo esc_attr( wp_json_encode( $ids ) ); ?>" data-document-ids>
		<ul data-document-list>
		<?php foreach ( $ids as $id ) : ?>
			<?php $document = attachment_descriptor( $id ); ?>
			<li class="ligatura-character-document" data-document-id="<?php echo esc_attr( (string) $id ); ?>">
				<?php if ( is_wp_error( $document ) ) : ?>
					<span><?php esc_html_e( 'Unavailable document — unlink it or upload it again.', 'ligatura-manuscripti-illuminati' ); ?></span>
				<?php else : ?>
					<a href="<?php echo esc_url( $document['url'] ); ?>"><?php echo esc_html( $document['title'] ); ?></a>
					<span class="ligatura-character-document__type"><?php echo esc_html( $document['type'] ); ?></span>
				<?php endif; ?>
				<button type="button" class="button-link" data-unlink-document><?php esc_html_e( 'Unlink', 'ligatura-manuscripti-illuminati' ); ?></button>
			</li>
		<?php endforeach; ?>
		</ul>
		<label for="ligatura-character-document-upload"><?php esc_html_e( 'Choose documents to upload', 'ligatura-manuscripti-illuminati' ); ?></label>
		<input id="ligatura-character-document-upload" type="file" data-document-upload accept=".pdf,.doc,.docx,.odt,.rtf,.txt" multiple<?php disabled( ! current_user_can( 'upload_files' ) ); ?>>
		<button type="button" class="button" data-upload-documents<?php disabled( ! current_user_can( 'upload_files' ) ); ?>><?php esc_html_e( 'Upload Document', 'ligatura-manuscripti-illuminati' ); ?></button>
		<button type="button" class="button" data-attach-documents<?php disabled( ! current_user_can( 'upload_files' ) ); ?>><?php esc_html_e( 'Attach Document', 'ligatura-manuscripti-illuminati' ); ?></button>
		<p data-document-status role="status" aria-live="polite"></p>
		<noscript><p><?php esc_html_e( 'Document upload and list changes require JavaScript. Existing documents are preserved when you save.', 'ligatura-manuscripti-illuminati' ); ?></p></noscript>
	</div>
	<?php
}
