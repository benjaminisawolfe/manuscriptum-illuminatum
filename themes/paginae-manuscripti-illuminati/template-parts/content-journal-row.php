<?php
/**
 * Full-width Journal preview row.
 *
 * @package PaginaeManuscriptiIlluminati
 */

$post_id = get_the_ID();

get_template_part(
	'template-parts/content',
	'chronicle-row',
	array(
		'post_id'       => $post_id,
		'class'         => 'manuscriptum-illuminatum-journal-row--commentarium',
		'meta'          => paginae_manuscripti_illuminati_journal_meta_line( $post_id ),
		'portrait_id'   => paginae_manuscripti_illuminati_journal_character_image_id( $post_id ),
		'portrait_name' => paginae_manuscripti_illuminati_journal_character_name( $post_id ),
		'heading_level' => 3,
		'data'          => array( 'saga-date' => paginae_manuscripti_illuminati_journal_saga_date( $post_id ) ),
	)
);
