<?php
/**
 * 404 template.
 *
 * @package PaginaeManuscriptiIlluminati
 */

get_header();
?>

<main id="primary" class="site-main manuscriptum-illuminatum-page">
	<?php paginae_manuscripti_illuminati_open_layout(); ?>
		<section class="manuscriptum-illuminatum-page-header">
			<p class="manuscriptum-illuminatum-kicker"><?php esc_html_e( 'Lost Folio', 'paginae-manuscripti-illuminati' ); ?></p>
			<h1><?php esc_html_e( 'This page could not be found', 'paginae-manuscripti-illuminati' ); ?></h1>
			<p><?php esc_html_e( 'The requested chronicle page is missing or unavailable.', 'paginae-manuscripti-illuminati' ); ?></p>
			<?php get_search_form(); ?>
		</section>
	<?php paginae_manuscripti_illuminati_close_layout(); ?>
</main>

<?php
get_footer();
