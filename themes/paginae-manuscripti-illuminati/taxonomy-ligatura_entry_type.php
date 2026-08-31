<?php
/**
 * Speculum Entry Type directory template.
 *
 * @package PaginaeManuscriptiIlluminati
 */

get_header();

$term    = get_queried_object();
$entries = $term instanceof WP_Term && function_exists( '\\LigaturaManuscriptiIlluminati\\SpeculumDirectory\\entries_for_type' )
	? \LigaturaManuscriptiIlluminati\SpeculumDirectory\entries_for_type( $term->slug )
	: array();
?>

<main id="primary" class="site-main manuscriptum-illuminatum-page">
	<?php paginae_manuscripti_illuminati_open_layout(); ?>
		<article class="manuscriptum-illuminatum-entry manuscriptum-illuminatum-entry--content-directory manuscriptum-illuminatum-entry--entry-type">
			<header class="manuscriptum-illuminatum-page-header">
				<p class="manuscriptum-illuminatum-kicker"><?php esc_html_e( 'Speculum Entry Type', 'paginae-manuscripti-illuminati' ); ?></p>
				<h1><?php echo esc_html( $term instanceof WP_Term ? $term->name : __( 'Speculum', 'paginae-manuscripti-illuminati' ) ); ?></h1>
			</header>

			<div class="manuscriptum-illuminatum-content">
				<p class="manuscriptum-illuminatum-entry-type__back"><a href="<?php echo esc_url( paginae_manuscripti_illuminati_content_directory_url( 'ligatura_wiki' ) ); ?>"><?php esc_html_e( 'Back to all Speculum entries', 'paginae-manuscripti-illuminati' ); ?></a></p>
				<?php if ( $entries && function_exists( '\\LigaturaManuscriptiIlluminati\\SpeculumDirectory\\render_entry_list' ) ) : ?>
					<div class="manuscriptum-illuminatum-entry-type-directory">
						<?php echo \LigaturaManuscriptiIlluminati\SpeculumDirectory\render_entry_list( $entries ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plugin helper escapes links and titles. ?>
					</div>
				<?php else : ?>
					<p><?php esc_html_e( 'No readable Speculum entries are assigned to this Entry Type.', 'paginae-manuscripti-illuminati' ); ?></p>
				<?php endif; ?>
			</div>
		</article>
	<?php paginae_manuscripti_illuminati_close_layout(); ?>
</main>

<?php
get_footer();
