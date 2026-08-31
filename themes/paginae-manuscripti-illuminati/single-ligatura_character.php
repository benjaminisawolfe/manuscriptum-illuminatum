<?php
/**
 * Character single template.
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
			$description    = paginae_manuscripti_illuminati_meta( $post_id, 'ligatura_brief_description' );
			$campaign_image = get_post_thumbnail_id( $post_id );
			$image_alt      = $campaign_image ? trim( (string) get_post_meta( $campaign_image, '_wp_attachment_image_alt', true ) ) : '';

			if ( $campaign_image && '' === $image_alt ) {
				$image_alt = get_the_title( $post_id );
			}
			?>
			<article <?php post_class( 'manuscriptum-illuminatum-entry manuscriptum-illuminatum-entry--character' ); ?>>
				<header class="manuscriptum-illuminatum-persona-header<?php echo $campaign_image ? ' manuscriptum-illuminatum-persona-header--with-image' : ''; ?>">
					<div class="manuscriptum-illuminatum-persona-header__text">
						<h1><?php the_title(); ?></h1>
						<?php if ( $description ) : ?>
							<p><?php echo esc_html( $description ); ?></p>
						<?php endif; ?>
					</div>
					<?php if ( $campaign_image ) : ?>
						<figure class="manuscriptum-illuminatum-persona-header__media">
							<?php
							echo wp_get_attachment_image(
								$campaign_image,
								'ligatura_character_portrait',
								false,
								array(
									'alt'     => $image_alt,
									'class'   => 'manuscriptum-illuminatum-persona-header__image',
									'loading' => 'eager',
									'sizes'   => '(max-width: 480px) 88px, 112px',
								)
							);
							?>
						</figure>
					<?php endif; ?>
				</header>

			<section class="manuscriptum-illuminatum-persona-summary" aria-label="<?php esc_attr_e( 'Character summary', 'paginae-manuscripti-illuminati' ); ?>">
				<?php
				paginae_manuscripti_illuminati_meta_list(
					$post_id,
					array(
						__( 'House', 'paginae-manuscripti-illuminati' )          => 'ligatura_house',
						__( 'Tradition', 'paginae-manuscripti-illuminati' )      => 'ligatura_tradition',
						__( 'Player', 'paginae-manuscripti-illuminati' )         => 'ligatura_player',
						__( 'Apparent Age', 'paginae-manuscripti-illuminati' )   => 'ligatura_apparent_age',
						__( 'Origin', 'paginae-manuscripti-illuminati' )         => 'ligatura_origin',
						__( 'Occupation', 'paginae-manuscripti-illuminati' )     => 'ligatura_occupation',
					)
				);
				?>
			</section>

			<div class="manuscriptum-illuminatum-content">
				<?php the_content(); ?>
			</div>

			<?php
			$sheet_sections = array(
				__( 'Characteristics', 'paginae-manuscripti-illuminati' )      => 'ligatura_characteristics',
				__( 'Personality Traits', 'paginae-manuscripti-illuminati' )  => 'ligatura_personality_traits',
				__( 'Virtues', 'paginae-manuscripti-illuminati' )             => 'ligatura_virtues',
				__( 'Flaws', 'paginae-manuscripti-illuminati' )               => 'ligatura_flaws',
				__( 'Abilities', 'paginae-manuscripti-illuminati' )           => 'ligatura_abilities',
				__( 'Hermetic Arts', 'paginae-manuscripti-illuminati' )       => 'ligatura_arts',
				__( 'Spells', 'paginae-manuscripti-illuminati' )              => 'ligatura_spells',
				__( 'Equipment', 'paginae-manuscripti-illuminati' )           => 'ligatura_equipment',
				__( 'Wounds', 'paginae-manuscripti-illuminati' )              => 'ligatura_wounds',
				__( 'Warping', 'paginae-manuscripti-illuminati' )             => 'ligatura_warping',
				__( 'Confidence', 'paginae-manuscripti-illuminati' )          => 'ligatura_confidence',
				__( 'Reputations', 'paginae-manuscripti-illuminati' )         => 'ligatura_reputations',
			);
			?>
			<section class="manuscriptum-illuminatum-section manuscriptum-illuminatum-character-fields">
				<?php foreach ( $sheet_sections as $label => $key ) : ?>
					<?php if ( paginae_manuscripti_illuminati_meta( $post_id, $key ) ) : ?>
						<div class="manuscriptum-illuminatum-field-block">
							<h2><?php echo esc_html( $label ); ?></h2>
							<p><?php echo nl2br( esc_html( paginae_manuscripti_illuminati_meta( $post_id, $key ) ) ); ?></p>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</section>

			<?php if ( paginae_manuscripti_illuminati_meta( $post_id, 'ligatura_notes_public' ) ) : ?>
				<section class="manuscriptum-illuminatum-section manuscriptum-illuminatum-character-notes">
					<h2><?php esc_html_e( 'Notes', 'paginae-manuscripti-illuminati' ); ?></h2>
					<div class="manuscriptum-illuminatum-content manuscriptum-illuminatum-character-notes__body">
						<?php echo wp_kses_post( wpautop( paginae_manuscripti_illuminati_meta( $post_id, 'ligatura_notes_public' ) ) ); ?>
					</div>
				</section>
			<?php endif; ?>
			</article>
			<?php
		endwhile;
		?>
	<?php paginae_manuscripti_illuminati_close_layout(); ?>
</main>

<?php
get_footer();
