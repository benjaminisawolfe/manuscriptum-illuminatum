<?php
/**
 * Shared full-width row for chronological Home and index sections.
 *
 * @package PaginaeManuscriptiIlluminati
 */

$post_id       = absint( $args['post_id'] ?? 0 );
$heading_level = 2 === absint( $args['heading_level'] ?? 3 ) ? 2 : 3;
$heading_tag   = 'h' . $heading_level;
$extra_class   = sanitize_html_class( (string) ( $args['class'] ?? '' ) );
$show_avatar   = ! empty( $args['show_avatar'] );
$author_id     = (int) get_post_field( 'post_author', $post_id );
$author_name   = paginae_manuscripti_illuminati_author_nickname( $author_id );
$avatar        = $show_avatar ? paginae_manuscripti_illuminati_author_avatar_data( $author_id, 72 ) : array();
$portrait_id   = absint( $args['portrait_id'] ?? 0 );
$portrait_name = sanitize_text_field( (string) ( $args['portrait_name'] ?? '' ) );
$data          = is_array( $args['data'] ?? null ) ? $args['data'] : array();
$excerpt       = trim( wp_strip_all_tags( (string) ( $args['excerpt'] ?? '' ) ) );

if ( '' === $excerpt ) {
	$excerpt = wp_strip_all_tags( get_the_excerpt( $post_id ) );
}
?>

<article <?php post_class( trim( 'manuscriptum-illuminatum-card manuscriptum-illuminatum-journal-row manuscriptum-illuminatum-chronicle-row ' . $extra_class ), $post_id ); ?> data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"<?php foreach ( $data as $name => $value ) : ?> data-<?php echo esc_attr( sanitize_key( (string) $name ) ); ?>="<?php echo esc_attr( (string) $value ); ?>"<?php endforeach; ?>>
	<div class="manuscriptum-illuminatum-journal-row__text manuscriptum-illuminatum-chronicle-row__text">
		<<?php echo esc_html( $heading_tag ); ?>><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></<?php echo esc_html( $heading_tag ); ?>>
		<?php if ( ! empty( $args['meta'] ) ) : ?>
			<p class="manuscriptum-illuminatum-card__meta"><?php echo esc_html( (string) $args['meta'] ); ?></p>
		<?php endif; ?>
		<p><?php echo esc_html( wp_trim_words( $excerpt, 36 ) ); ?></p>
	</div>
	<?php if ( $portrait_id ) : ?>
		<?php
		echo wp_get_attachment_image(
			$portrait_id,
			'ligatura_character_portrait',
			false,
			array(
				'class'   => 'manuscriptum-illuminatum-journal-row__portrait manuscriptum-illuminatum-chronicle-row__portrait',
				'alt'     => $portrait_name ? sprintf( __( 'Portrait of %s', 'paginae-manuscripti-illuminati' ), $portrait_name ) : __( 'Character Author portrait', 'paginae-manuscripti-illuminati' ),
				'loading' => 'lazy',
				'sizes'   => '72px',
			)
		);
		?>
	<?php elseif ( $avatar ) : ?>
		<img
			class="manuscriptum-illuminatum-journal-row__portrait manuscriptum-illuminatum-chronicle-row__portrait"
			src="<?php echo esc_url( (string) $avatar['url'] ); ?>"
			alt="<?php echo esc_attr( $author_name ? sprintf( __( 'Portrait of %s', 'paginae-manuscripti-illuminati' ), $author_name ) : __( 'Author portrait', 'paginae-manuscripti-illuminati' ) ); ?>"
			width="<?php echo esc_attr( (string) ( $avatar['width'] ?? 72 ) ); ?>"
			height="<?php echo esc_attr( (string) ( $avatar['height'] ?? 72 ) ); ?>"
			loading="lazy"
		/>
	<?php endif; ?>
</article>
