<?php
/**
 * Journal card.
 *
 * @package PaginaeManuscriptiIlluminati
 */

?>

<article <?php post_class( 'manuscriptum-illuminatum-card manuscriptum-illuminatum-card--diary' ); ?> data-saga-date="<?php echo esc_attr( paginae_manuscripti_illuminati_journal_saga_date( get_the_ID() ) ); ?>">
	<div class="manuscriptum-illuminatum-card__body">
		<p class="manuscriptum-illuminatum-card__meta"><?php echo esc_html( paginae_manuscripti_illuminati_journal_meta_line( get_the_ID() ) ); ?></p>
		<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 32 ) ); ?></p>
	</div>
</article>
