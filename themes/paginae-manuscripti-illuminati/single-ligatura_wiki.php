<?php
/**
 * Wiki single template.
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
			$post_id        = get_the_ID();
			$entry_types    = paginae_manuscripti_illuminati_term_line( $post_id, 'ligatura_entry_type' );
			$in_world_date  = paginae_manuscripti_illuminati_meta( $post_id, 'ligatura_in_world_date' );
			$saga_topics    = paginae_manuscripti_illuminati_term_line( $post_id, 'ligatura_saga_topic', '<span aria-hidden="true">, </span>' );
			$public_summary = paginae_manuscripti_illuminati_meta( $post_id, 'ligatura_public_summary' );
			$metadata       = array_filter(
				array(
					__( 'Entry Type', 'paginae-manuscripti-illuminati' )    => $entry_types,
					__( 'In-World Date', 'paginae-manuscripti-illuminati' ) => $in_world_date ? esc_html( $in_world_date ) : '',
					__( 'Saga Topics', 'paginae-manuscripti-illuminati' )   => $saga_topics,
				)
			);
			?>
			<article <?php post_class( 'manuscriptum-illuminatum-entry manuscriptum-illuminatum-entry--wiki' ); ?>>
				<header class="manuscriptum-illuminatum-page-header">
					<p class="manuscriptum-illuminatum-kicker"><?php echo wp_kses_post( paginae_manuscripti_illuminati_term_line( $post_id, 'ligatura_entry_type' ) ); ?></p>
					<h1><?php the_title(); ?></h1>
				</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="manuscriptum-illuminatum-featured-image">
					<?php the_post_thumbnail( 'ligatura_banner_large' ); ?>
				</figure>
			<?php endif; ?>

			<?php if ( $metadata ) : ?>
				<section class="manuscriptum-illuminatum-section manuscriptum-illuminatum-wiki-metadata">
					<dl class="manuscriptum-illuminatum-meta-list manuscriptum-illuminatum-meta-list--plain">
						<?php foreach ( $metadata as $label => $value ) : ?>
							<div>
								<dt><?php echo esc_html( $label ); ?></dt>
								<dd><?php echo wp_kses_post( $value ); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				</section>
			<?php endif; ?>

			<?php if ( $public_summary ) : ?>
				<section class="manuscriptum-illuminatum-section manuscriptum-illuminatum-summary">
					<?php echo wp_kses_post( wpautop( $public_summary ) ); ?>
				</section>
			<?php elseif ( has_excerpt() ) : ?>
				<section class="manuscriptum-illuminatum-section manuscriptum-illuminatum-summary">
					<p><?php echo esc_html( get_the_excerpt() ); ?></p>
				</section>
			<?php endif; ?>

			<div class="manuscriptum-illuminatum-content manuscriptum-illuminatum-wiki-content">
				<?php the_content(); ?>
			</div>

			<?php paginae_manuscripti_illuminati_related_entries( $post_id ); ?>
			<?php get_template_part( 'template-parts/private-storyguide-notes' ); ?>
			</article>
			<?php
		endwhile;
		?>
	<?php paginae_manuscripti_illuminati_close_layout(); ?>
</main>

<?php
get_footer();
