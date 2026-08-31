<?php
/**
 * Main index template.
 *
 * @package PaginaeManuscriptiIlluminati
 */

get_header();
?>

<main id="primary" class="site-main manuscriptum-illuminatum-page">
	<?php paginae_manuscripti_illuminati_open_layout(); ?>
		<header class="manuscriptum-illuminatum-page-header">
			<p class="manuscriptum-illuminatum-kicker"><?php esc_html_e( 'Chronicle', 'paginae-manuscripti-illuminati' ); ?></p>
			<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
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
		<?php else : ?>
			<section class="manuscriptum-illuminatum-section">
				<h2><?php esc_html_e( 'No entries yet', 'paginae-manuscripti-illuminati' ); ?></h2>
				<p><?php esc_html_e( 'The chronicle has not begun.', 'paginae-manuscripti-illuminati' ); ?></p>
			</section>
		<?php endif; ?>
	<?php paginae_manuscripti_illuminati_close_layout(); ?>
</main>

<?php
get_footer();
