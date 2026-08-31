<?php
/**
 * Editable Annales Posts Page.
 *
 * @package PaginaeManuscriptiIlluminati
 */

get_header();

$annales_page = paginae_manuscripti_illuminati_annales_page();
$page_title   = $annales_page ? get_the_title( $annales_page ) : __( 'Annales', 'paginae-manuscripti-illuminati' );
$page_content = $annales_page ? trim( (string) $annales_page->post_content ) : '';
?>

<main id="primary" class="site-main manuscriptum-illuminatum-page manuscriptum-illuminatum-page--annales">
	<?php paginae_manuscripti_illuminati_open_layout(); ?>
		<header class="manuscriptum-illuminatum-page-header">
			<h1><?php echo esc_html( $page_title ); ?></h1>
		</header>

		<?php if ( '' !== $page_content ) : ?>
			<section class="manuscriptum-illuminatum-section manuscriptum-illuminatum-annales-introduction">
				<div class="manuscriptum-illuminatum-content">
					<?php echo apply_filters( 'the_content', $page_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Editor content uses the standard content filter. ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<section class="manuscriptum-illuminatum-content-index manuscriptum-illuminatum-annales-directory" aria-label="<?php esc_attr_e( 'Annales entries', 'paginae-manuscripti-illuminati' ); ?>">
				<div class="manuscriptum-illuminatum-journal-list manuscriptum-illuminatum-chronicle-list">
					<?php
					while ( have_posts() ) :
						the_post();
						echo paginae_manuscripti_illuminati_render_annal_row( get_the_ID(), 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared partial escapes row data.
					endwhile;
					?>
				</div>

				<?php the_posts_pagination(); ?>
			</section>
		<?php else : ?>
			<section class="manuscriptum-illuminatum-section">
				<h2><?php esc_html_e( 'No Annales yet', 'paginae-manuscripti-illuminati' ); ?></h2>
				<p><?php esc_html_e( 'The out-of-character chronicle has not begun.', 'paginae-manuscripti-illuminati' ); ?></p>
			</section>
		<?php endif; ?>
	<?php paginae_manuscripti_illuminati_close_layout(); ?>
</main>

<?php
get_footer();
