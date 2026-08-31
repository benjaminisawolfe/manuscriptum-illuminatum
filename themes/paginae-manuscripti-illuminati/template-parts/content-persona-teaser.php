<?php
/**
 * Compact Persona teaser for the Character Type-grouped directory.
 *
 * @package PaginaeManuscriptiIlluminati
 */

$post_id     = absint( $args['post_id'] ?? 0 );
$description = paginae_manuscripti_illuminati_meta( $post_id, 'ligatura_brief_description' );
$house       = paginae_manuscripti_illuminati_meta( $post_id, 'ligatura_house' );
$image_id    = get_post_thumbnail_id( $post_id );
$image_alt   = '';

if ( '' === trim( $description ) ) {
	$description = (string) get_post_field( 'post_excerpt', $post_id );

	if ( '' === trim( $description ) ) {
		$description = wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) );
	}
}

if ( $image_id ) {
	$image_alt = trim( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) );

	if ( '' === $image_alt ) {
		$image_alt = get_the_title( $post_id );
	}
}

if ( '' === trim( $house ) ) {
	$house_terms = get_the_terms( $post_id, 'ligatura_hermetic_house' );

	if ( $house_terms && ! is_wp_error( $house_terms ) ) {
		usort( $house_terms, static fn( WP_Term $left, WP_Term $right ): int => strnatcasecmp( $left->name, $right->name ) );
		$house = implode( ' / ', wp_list_pluck( $house_terms, 'name' ) );
	}
}
?>

<article class="manuscriptum-illuminatum-card manuscriptum-illuminatum-card--character manuscriptum-illuminatum-directory-teaser manuscriptum-illuminatum-persona-teaser<?php echo $image_id ? ' manuscriptum-illuminatum-persona-teaser--with-image' : ' manuscriptum-illuminatum-persona-teaser--without-image'; ?>">
	<?php if ( $image_id ) : ?>
		<figure class="manuscriptum-illuminatum-persona-teaser__media">
			<?php
			echo wp_get_attachment_image(
				$image_id,
				'ligatura_character_portrait',
				false,
				array(
					'alt'     => $image_alt,
					'class'   => 'manuscriptum-illuminatum-persona-teaser__image',
					'loading' => 'lazy',
					'sizes'   => '(max-width: 480px) 88px, 112px',
				)
			);
			?>
		</figure>
	<?php endif; ?>
	<div class="manuscriptum-illuminatum-card__body manuscriptum-illuminatum-persona-teaser__body">
		<?php if ( '' !== trim( $house ) ) : ?>
			<p class="manuscriptum-illuminatum-card__meta manuscriptum-illuminatum-persona-teaser__meta"><?php echo esc_html( $house ); ?></p>
		<?php endif; ?>
		<h3><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></h3>
		<p><?php echo esc_html( wp_trim_words( $description, 28 ) ); ?></p>
	</div>
</article>
