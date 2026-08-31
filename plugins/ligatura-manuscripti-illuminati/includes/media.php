<?php
/**
 * Media configuration.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register useful campaign image sizes.
 */
function register_image_sizes(): void {
	add_image_size( 'ligatura_banner_large', 1920, 720, false );
	add_image_size( 'ligatura_entry_card', 640, 420, true );
	add_image_size( 'ligatura_character_portrait', 520, 720, false );
	add_image_size( 'ligatura_wiki_illustration', 1280, 900, false );
}
