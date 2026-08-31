<?php
/**
 * Covenant card.
 *
 * @package PaginaeManuscriptiIlluminati
 */

$post_id = get_the_ID();
?>

<article <?php post_class( 'manuscriptum-illuminatum-card manuscriptum-illuminatum-card--covenant' ); ?>>
	<a class="manuscriptum-illuminatum-card__image" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s', 'paginae-manuscripti-illuminati' ), get_the_title() ) ); ?>">
		<?php paginae_manuscripti_illuminati_image( $post_id, 'ligatura_entry_card', 'default-wiki.svg', 'manuscriptum-illuminatum-card__img' ); ?>
	</a>
	<div class="manuscriptum-illuminatum-card__body">
		<p class="manuscriptum-illuminatum-card__meta"><?php esc_html_e( 'Covenant Record', 'paginae-manuscripti-illuminati' ); ?></p>
		<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30 ) ); ?></p>
	</div>
</article>
