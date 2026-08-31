<?php
/**
 * Text-only front-page Speculum update.
 *
 * @package PaginaeManuscriptiIlluminati
 */

$post_id = get_the_ID();

echo paginae_manuscripti_illuminati_render_speculum_teaser( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared partial escapes all dynamic output.
	$post_id,
	array(
		'show_entry_type' => true,
		'show_modified'   => true,
	)
);
