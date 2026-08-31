<?php
/**
 * Journal single template.
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
			$author_name    = paginae_manuscripti_illuminati_journal_character_name( $post_id );
			$author_image   = paginae_manuscripti_illuminati_journal_character_image_id( $post_id );
			$saga_date      = paginae_manuscripti_illuminati_format_journal_saga_date( $post_id );
			$campaign_image = get_post_thumbnail_id( $post_id );
			$topic_links     = paginae_manuscripti_illuminati_term_line( $post_id, 'ligatura_saga_topic' );
			$tag_links      = paginae_manuscripti_illuminati_term_line( $post_id, 'post_tag' );
			?>
			<article <?php post_class( 'manuscriptum-illuminatum-entry manuscriptum-illuminatum-entry--diary' ); ?> data-saga-date="<?php echo esc_attr( paginae_manuscripti_illuminati_journal_saga_date( $post_id ) ); ?>">
				<header class="manuscriptum-illuminatum-page-header manuscriptum-illuminatum-journal-header">
					<div class="manuscriptum-illuminatum-journal-header__text">
						<h1><?php the_title(); ?></h1>
						<?php if ( $author_name ) : ?>
							<p class="manuscriptum-illuminatum-kicker manuscriptum-illuminatum-journal-header__author"><?php echo esc_html( $author_name ); ?></p>
						<?php endif; ?>
						<?php if ( $saga_date ) : ?>
							<p class="manuscriptum-illuminatum-kicker manuscriptum-illuminatum-journal-header__date"><?php echo esc_html( $saga_date ); ?></p>
						<?php endif; ?>
					</div>
					<?php if ( $author_image ) : ?>
						<?php
						echo wp_get_attachment_image(
							$author_image,
							'ligatura_character_portrait',
							false,
							array(
								'class'   => 'manuscriptum-illuminatum-journal-header__portrait',
								'alt'     => $author_name ? sprintf( __( 'Portrait of %s', 'paginae-manuscripti-illuminati' ), $author_name ) : __( 'Character Author portrait', 'paginae-manuscripti-illuminati' ),
								'loading' => 'lazy',
								'sizes'   => '(max-width: 480px) 72px, 96px',
							)
						);
						?>
					<?php endif; ?>
				</header>

			<div class="manuscriptum-illuminatum-content manuscriptum-illuminatum-journal-content">
				<?php if ( $campaign_image ) : ?>
					<?php
					$image_alt = trim( (string) get_post_meta( $campaign_image, '_wp_attachment_image_alt', true ) );

					if ( '' === $image_alt ) {
						$image_alt = sprintf(
							/* translators: %s: Journal title. */
							__( 'Campaign image for %s', 'paginae-manuscripti-illuminati' ),
							get_the_title( $post_id )
						);
					}
					?>
					<figure class="manuscriptum-illuminatum-journal-content__image">
						<?php
						echo wp_get_attachment_image(
							$campaign_image,
							'ligatura_banner_large',
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

			<?php if ( $topic_links || $tag_links ) : ?>
				<footer class="manuscriptum-illuminatum-journal-footer">
					<?php if ( $topic_links ) : ?>
						<p class="manuscriptum-illuminatum-journal-footer__line manuscriptum-illuminatum-journal-footer__topics">
							<strong><?php esc_html_e( 'Topics:', 'paginae-manuscripti-illuminati' ); ?></strong>
							<?php echo $topic_links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes term URLs and names. ?>
						</p>
					<?php endif; ?>
					<?php if ( $tag_links ) : ?>
						<p class="manuscriptum-illuminatum-journal-footer__line manuscriptum-illuminatum-journal-footer__tags">
							<strong><?php esc_html_e( 'Tags:', 'paginae-manuscripti-illuminati' ); ?></strong>
							<?php echo $tag_links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes term URLs and names. ?>
						</p>
					<?php endif; ?>
				</footer>
			<?php endif; ?>

			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>
			</article>
			<?php
		endwhile;
		?>
	<?php paginae_manuscripti_illuminati_close_layout(); ?>
</main>

<?php
get_footer();
