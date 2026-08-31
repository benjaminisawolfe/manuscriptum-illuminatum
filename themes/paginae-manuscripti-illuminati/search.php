<?php
/**
 * Search template.
 *
 * @package PaginaeManuscriptiIlluminati
 */

get_header();
?>

<main id="primary" class="site-main manuscriptum-illuminatum-page">
	<?php paginae_manuscripti_illuminati_open_layout(); ?>
		<header class="manuscriptum-illuminatum-page-header">
			<p class="manuscriptum-illuminatum-kicker"><?php esc_html_e( 'Search', 'paginae-manuscripti-illuminati' ); ?></p>
			<h1>
				<?php
				printf(
					/* translators: %s: search query. */
					esc_html__( 'Results for "%s"', 'paginae-manuscripti-illuminati' ),
					esc_html( get_search_query() )
				);
				?>
			</h1>
			<?php get_search_form(); ?>
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
				<h2><?php esc_html_e( 'Nothing found', 'paginae-manuscripti-illuminati' ); ?></h2>
				<p><?php esc_html_e( 'Try a different search term.', 'paginae-manuscripti-illuminati' ); ?></p>
			</section>
		<?php endif; ?>
	<?php paginae_manuscripti_illuminati_close_layout(); ?>
</main>

<?php
get_footer();
