<?php
/**
 * Speculum Saga Topic collection template.
 *
 * @package PaginaeManuscriptiIlluminati
 */

get_header();

$term    = get_queried_object();
$entries = $term instanceof WP_Term && function_exists( '\\LigaturaManuscriptiIlluminati\\SpeculumDirectory\\entries_for_topic' )
	? \LigaturaManuscriptiIlluminati\SpeculumDirectory\entries_for_topic( $term->slug )
	: array();
?>

<main id="primary" class="site-main manuscriptum-illuminatum-page">
	<?php paginae_manuscripti_illuminati_open_layout(); ?>
		<article class="manuscriptum-illuminatum-entry manuscriptum-illuminatum-entry--content-directory manuscriptum-illuminatum-entry--saga-topic">
			<header class="manuscriptum-illuminatum-page-header">
				<p class="manuscriptum-illuminatum-kicker"><?php esc_html_e( 'Speculum Saga Topic', 'paginae-manuscripti-illuminati' ); ?></p>
				<h1><?php echo esc_html( $term instanceof WP_Term ? $term->name : __( 'Saga Topic', 'paginae-manuscripti-illuminati' ) ); ?></h1>
			</header>

			<div class="manuscriptum-illuminatum-content">
				<nav class="manuscriptum-illuminatum-entry-type__back" aria-label="<?php esc_attr_e( 'Speculum collection', 'paginae-manuscripti-illuminati' ); ?>">
					<a href="<?php echo esc_url( paginae_manuscripti_illuminati_content_directory_url( 'ligatura_wiki' ) ); ?>"><?php esc_html_e( 'Back to all Speculum entries', 'paginae-manuscripti-illuminati' ); ?></a>
				</nav>
				<?php if ( $entries && function_exists( '\\LigaturaManuscriptiIlluminati\\SpeculumDirectory\\render_entry_teasers' ) ) : ?>
					<div class="manuscriptum-illuminatum-saga-topic-directory">
						<?php echo \LigaturaManuscriptiIlluminati\SpeculumDirectory\render_entry_teasers( $entries ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared helper checks capabilities and renders the escaped theme teaser partial. ?>
					</div>
				<?php else : ?>
					<p><?php esc_html_e( 'No readable Speculum entries are assigned to this Saga Topic.', 'paginae-manuscripti-illuminati' ); ?></p>
				<?php endif; ?>
			</div>
		</article>
	<?php paginae_manuscripti_illuminati_close_layout(); ?>
</main>

<?php
get_footer();
