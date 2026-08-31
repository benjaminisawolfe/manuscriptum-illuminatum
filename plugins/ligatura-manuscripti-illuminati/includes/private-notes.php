<?php
/**
 * Storyguide private note helpers.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\PrivateNotes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META_KEY = 'ligatura_storyguide_notes';

/**
 * Determine whether a user can view Storyguide-only notes.
 *
 * @param int|null $user_id User ID. Defaults to current user.
 */
function can_view_storyguide_notes( ?int $user_id = null ): bool {
	$user_id = $user_id ?: get_current_user_id();

	if ( ! $user_id ) {
		return false;
	}

	return user_can( $user_id, 'ligatura_manage_storyguide_notes' ) || user_can( $user_id, 'manage_options' );
}

/**
 * Get Storyguide notes only when the viewer has permission.
 *
 * @param int $post_id Wiki entry ID.
 */
function get_storyguide_notes( int $post_id ): string {
	if ( 'ligatura_wiki' !== get_post_type( $post_id ) || ! can_view_storyguide_notes() ) {
		return '';
	}

	return (string) get_post_meta( $post_id, META_KEY, true );
}

/**
 * Render the private note panel for authorized viewers.
 *
 * @param int $post_id Wiki entry ID.
 */
function render_storyguide_notes_panel( int $post_id ): string {
	$notes = get_storyguide_notes( $post_id );

	if ( '' === trim( $notes ) ) {
		return '';
	}

	ob_start();
	?>
	<section class="manuscriptum-illuminatum-storyguide-notes" aria-label="<?php esc_attr_e( 'Storyguide Notes', 'ligatura-manuscripti-illuminati' ); ?>">
		<header class="manuscriptum-illuminatum-storyguide-notes__header">
			<h2><?php esc_html_e( 'Storyguide Notes', 'ligatura-manuscripti-illuminati' ); ?></h2>
			<p><?php esc_html_e( 'Private - not visible to players.', 'ligatura-manuscripti-illuminati' ); ?></p>
		</header>
		<div class="manuscriptum-illuminatum-storyguide-notes__body">
			<?php echo wp_kses_post( wpautop( $notes ) ); ?>
		</div>
	</section>
	<?php

	return (string) ob_get_clean();
}

/**
 * Defensive filter for excerpts.
 *
 * @param string $excerpt Excerpt text.
 * @param object $post    Post object.
 */
function strip_private_notes_from_excerpt( string $excerpt, object $post ): string {
	if ( isset( $post->post_type ) && 'ligatura_wiki' === $post->post_type ) {
		return $excerpt;
	}

	return $excerpt;
}
