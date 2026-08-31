<?php
/**
 * Journal archive template.
 *
 * @package PaginaeManuscriptiIlluminati
 */

get_header();
?>

<main id="primary" class="site-main manuscriptum-illuminatum-page">
	<?php paginae_manuscripti_illuminati_open_layout(); ?>
		<header class="manuscriptum-illuminatum-page-header">
			<p class="manuscriptum-illuminatum-kicker"><?php esc_html_e( 'Journals', 'paginae-manuscripti-illuminati' ); ?></p>
			<h1><?php esc_html_e( 'Commentarii', 'paginae-manuscripti-illuminati' ); ?></h1>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="manuscriptum-illuminatum-card-grid manuscriptum-illuminatum-card-grid--compact">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content', 'diary-card' );
				endwhile;
				?>
			</div>
			<?php the_posts_pagination(); ?>
		<?php endif; ?>
	<?php paginae_manuscripti_illuminati_close_layout(); ?>
</main>

<?php
get_footer();
