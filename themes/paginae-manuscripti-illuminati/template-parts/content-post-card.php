<?php
/**
 * Generic post card.
 *
 * @package PaginaeManuscriptiIlluminati
 */

?>

<article <?php post_class( 'manuscriptum-illuminatum-card manuscriptum-illuminatum-card--post' ); ?>>
	<div class="manuscriptum-illuminatum-card__body">
		<p class="manuscriptum-illuminatum-card__meta"><?php echo esc_html( paginae_manuscripti_illuminati_post_meta_line( get_the_ID() ) ); ?></p>
		<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30 ) ); ?></p>
	</div>
</article>
