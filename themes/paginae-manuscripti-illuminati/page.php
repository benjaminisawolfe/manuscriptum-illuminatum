<?php
/**
 * Page template.
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
			$is_content_directory = function_exists( 'ligatura_get_campaign_content_post_type_for_page' )
				&& '' !== ligatura_get_campaign_content_post_type_for_page( get_the_ID() );
			$entry_class          = $is_content_directory ? 'manuscriptum-illuminatum-entry manuscriptum-illuminatum-entry--content-directory' : 'manuscriptum-illuminatum-entry';
			?>
			<article <?php post_class( $entry_class ); ?>>
				<header class="manuscriptum-illuminatum-page-header">
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
