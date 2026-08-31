<?php
/**
 * Covenant single template.
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
			$entry_types    = paginae_manuscripti_illuminati_covenant_term_line( $post_id, 'ligatura_entry_type' );
			$saga_topics    = paginae_manuscripti_illuminati_covenant_term_line( $post_id, 'ligatura_saga_topic' );
			$saga_date      = function_exists( 'ligatura_format_covenant_saga_date' ) ? ligatura_format_covenant_saga_date( $post_id ) : '';
			$public_summary = paginae_manuscripti_illuminati_meta( $post_id, 'ligatura_public_summary' );
			$campaign_image = get_post_thumbnail_id( $post_id );
			$metadata       = array_filter(
				array(
					__( 'Entry Type', 'paginae-manuscripti-illuminati' )  => $entry_types,
					__( 'Saga Date', 'paginae-manuscripti-illuminati' )   => $saga_date ? esc_html( $saga_date ) : '',
					__( 'Saga Topics', 'paginae-manuscripti-illuminati' ) => $saga_topics,
				)
			);
			?>
			<article <?php post_class( 'manuscriptum-illuminatum-entry manuscriptum-illuminatum-entry--covenant' ); ?>>
				<header class="manuscriptum-illuminatum-page-header">
					<p class="manuscriptum-illuminatum-kicker"><?php esc_html_e( 'Covenant Record', 'paginae-manuscripti-illuminati' ); ?></p>
					<h1><?php the_title(); ?></h1>
				</header>

			<?php if ( $metadata ) : ?>
				<section class="manuscriptum-illuminatum-section manuscriptum-illuminatum-covenant-metadata">
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

			<?php if ( '' !== trim( $public_summary ) ) : ?>
				<section class="manuscriptum-illuminatum-section manuscriptum-illuminatum-summary">
					<?php echo wp_kses_post( wpautop( $public_summary ) ); ?>
				</section>
			<?php endif; ?>

			<div class="manuscriptum-illuminatum-content manuscriptum-illuminatum-covenant-content">
				<?php if ( $campaign_image ) : ?>
					<?php
					$image_alt = trim( (string) get_post_meta( $campaign_image, '_wp_attachment_image_alt', true ) );

					if ( '' === $image_alt ) {
						$image_alt = sprintf(
							/* translators: %s: Covenant Record title. */
							__( 'Campaign image for %s', 'paginae-manuscripti-illuminati' ),
							get_the_title( $post_id )
						);
					}
					?>
					<figure class="manuscriptum-illuminatum-article-campaign-image">
						<?php
						echo wp_get_attachment_image(
							$campaign_image,
							'ligatura_wiki_illustration',
							false,
							array(
								'alt'      => $image_alt,
								'loading'  => 'eager',
								'decoding' => 'async',
							)
						);
						?>
					</figure>
				<?php endif; ?>
				<?php the_content(); ?>
			</div>

			<div class="manuscriptum-illuminatum-covenant-relationships">
				<?php paginae_manuscripti_illuminati_covenant_related_group( $post_id, 'ligatura_related_characters', 'ligatura_character', __( 'Related Personae', 'paginae-manuscripti-illuminati' ), 'personae' ); ?>
				<?php paginae_manuscripti_illuminati_covenant_related_group( $post_id, 'ligatura_related_places', 'ligatura_wiki', __( 'Related Places', 'paginae-manuscripti-illuminati' ), 'places' ); ?>
				<?php paginae_manuscripti_illuminati_covenant_related_group( $post_id, 'ligatura_related_entries', 'ligatura_wiki', __( 'Related Entries', 'paginae-manuscripti-illuminati' ), 'entries' ); ?>
			</div>
			</article>
			<?php
		endwhile;
		?>
	<?php paginae_manuscripti_illuminati_close_layout(); ?>
</main>

<?php
get_footer();
