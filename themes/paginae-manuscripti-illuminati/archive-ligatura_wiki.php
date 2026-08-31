<?php
/**
 * Speculum archive template.
 *
 * @package PaginaeManuscriptiIlluminati
 */

get_header();
?>

<main id="primary" class="site-main manuscriptum-illuminatum-page">
	<?php paginae_manuscripti_illuminati_open_layout(); ?>
		<header class="manuscriptum-illuminatum-page-header">
			<p class="manuscriptum-illuminatum-kicker"><?php esc_html_e( 'Campaign Lore', 'paginae-manuscripti-illuminati' ); ?></p>
			<h1><?php esc_html_e( 'Speculum', 'paginae-manuscripti-illuminati' ); ?></h1>
		</header>

		<?php
		$terms = get_terms(
			array(
				'taxonomy'   => 'ligatura_entry_type',
				'hide_empty' => true,
			)
		);
		if ( ! is_wp_error( $terms ) && $terms ) :
			?>
			<nav class="manuscriptum-illuminatum-term-nav" aria-label="<?php esc_attr_e( 'Entry type filters', 'paginae-manuscripti-illuminati' ); ?>">
				<?php foreach ( $terms as $term ) : ?>
					<a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<div class="manuscriptum-illuminatum-card-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content', 'wiki-card' );
				endwhile;
				?>
			</div>
			<?php the_posts_pagination(); ?>
		<?php endif; ?>
	<?php paginae_manuscripti_illuminati_close_layout(); ?>
</main>

<?php
get_footer();
