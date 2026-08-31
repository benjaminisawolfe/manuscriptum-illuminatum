<?php
/**
 * Sanitization helpers.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\Sanitization;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize plain text meta.
 *
 * @param mixed $value Raw value.
 */
function text( mixed $value ): string {
	return sanitize_text_field( wp_unslash( (string) $value ) );
}

/**
 * Sanitize multiline text meta.
 *
 * @param mixed $value Raw value.
 */
function textarea( mixed $value ): string {
	return sanitize_textarea_field( wp_unslash( (string) $value ) );
}

/**
 * Sanitize post-like rich text.
 *
 * @param mixed $value Raw value.
 */
function rich_text( mixed $value ): string {
	return wp_kses_post( wp_unslash( (string) $value ) );
}

/**
 * Sanitize an integer field.
 *
 * @param mixed $value Raw value.
 */
function integer( mixed $value ): int {
	return absint( $value );
}

/**
 * Sanitize comma-separated post IDs.
 *
 * @param mixed $value Raw value.
 */
function id_list( mixed $value ): string {
	$raw_ids = is_array( $value )
		? wp_unslash( $value )
		: preg_split( '/[,\s]+/', wp_unslash( (string) $value ) );
	$ids     = array();

	if ( is_array( $raw_ids ) ) {
		foreach ( $raw_ids as $raw_id ) {
			$id = absint( $raw_id );

			if ( $id > 0 ) {
				$ids[] = (string) $id;
			}
		}
	}

	return implode( ',', array_unique( $ids ) );
}
