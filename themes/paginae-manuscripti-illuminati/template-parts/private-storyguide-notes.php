<?php
/**
 * Private Storyguide notes template part.
 *
 * @package PaginaeManuscriptiIlluminati
 */

if ( function_exists( 'ligatura_render_storyguide_notes_panel' ) ) {
	echo ligatura_render_storyguide_notes_panel( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
