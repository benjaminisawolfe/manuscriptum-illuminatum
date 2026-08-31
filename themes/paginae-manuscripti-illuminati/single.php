<?php
/**
 * Single post fallback template.
 *
 * @package PaginaeManuscriptiIlluminati
 */

get_header();
?>

<main id="primary" class="site-main manuscriptum-illuminatum-page">
	<?php paginae_manuscripti_illuminati_open_layout(); ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'manuscriptum-illuminatum-entry' ); ?>>
				<header class="manuscriptum-illuminatum-page-header">
					<p class="manuscriptum-illuminatum-kicker"><?php echo esc_html( paginae_manuscripti_illuminati_post_meta_line( get_the_ID() ) ); ?></p>
					<h1><?php the_title(); ?></h1>
				</header>
				<div class="manuscriptum-illuminatum-content">
					<?php the_content(); ?>
				</div>
			</article>
			<?php
		endwhile;
		?>
	<?php paginae_manuscripti_illuminati_close_layout(); ?>
</main>

<?php
get_footer();
