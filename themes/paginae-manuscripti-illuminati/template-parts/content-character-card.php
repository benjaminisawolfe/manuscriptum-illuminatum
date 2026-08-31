<?php
/**
 * Character card.
 *
 * @package PaginaeManuscriptiIlluminati
 */

$post_id       = get_the_ID();
$description   = paginae_manuscripti_illuminati_meta( $post_id, 'ligatura_brief_description' );
$show_fallback = ! isset( $args['show_fallback'] ) || (bool) $args['show_fallback'];
$has_image     = has_post_thumbnail( $post_id );
?>

<article <?php post_class( 'manuscriptum-illuminatum-card manuscriptum-illuminatum-card--character' . ( $has_image ? ' manuscriptum-illuminatum-card--with-image' : ' manuscriptum-illuminatum-card--without-image' ) ); ?> data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" data-modified="<?php echo esc_attr( get_post_modified_time( 'c', true, $post_id ) ); ?>">
	<?php if ( $has_image || $show_fallback ) : ?>
		<a class="manuscriptum-illuminatum-card__image" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s', 'paginae-manuscripti-illuminati' ), get_the_title() ) ); ?>">
			<?php paginae_manuscripti_illuminati_image( $post_id, 'ligatura_entry_card', 'default-character.svg', 'manuscriptum-illuminatum-card__img', $show_fallback ); ?>
		</a>
	<?php endif; ?>
	<div class="manuscriptum-illuminatum-card__body">
		<p class="manuscriptum-illuminatum-card__meta">
			<?php
			echo wp_kses_post( paginae_manuscripti_illuminati_term_line( $post_id, 'ligatura_character_type' ) );
			if ( paginae_manuscripti_illuminati_meta( $post_id, 'ligatura_house' ) ) {
				echo '<span aria-hidden="true"> / </span>' . esc_html( paginae_manuscripti_illuminati_meta( $post_id, 'ligatura_house' ) );
			}
			?>
		</p>
		<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p><?php echo esc_html( wp_trim_words( $description ? $description : get_the_excerpt(), 28 ) ); ?></p>
	</div>
</article>
