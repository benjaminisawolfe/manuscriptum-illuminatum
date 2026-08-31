<?php
/**
 * Archive template.
 *
 * @package PaginaeManuscriptiIlluminati
 */

get_header();
?>

<main id="primary" class="site-main manuscriptum-illuminatum-page">
	<?php paginae_manuscripti_illuminati_open_layout(); ?>
		<header class="manuscriptum-illuminatum-page-header">
			<p class="manuscriptum-illuminatum-kicker"><?php esc_html_e( 'Archive', 'paginae-manuscripti-illuminati' ); ?></p>
			<h1><?php the_archive_title(); ?></h1>
			<?php the_archive_description( '<div class="manuscriptum-illuminatum-archive-description">', '</div>' ); ?>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="manuscriptum-illuminatum-card-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					paginae_manuscripti_illuminati_get_card_template();
				endwhile;
				?>
			</div>
			<?php the_posts_pagination(); ?>
		<?php endif; ?>
	<?php paginae_manuscripti_illuminati_close_layout(); ?>
</main>

<?php
get_footer();
