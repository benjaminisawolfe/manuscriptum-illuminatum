<?php
/**
 * Front page template.
 *
 * @package PaginaeManuscriptiIlluminati
 */

get_header();

$card_count      = 4;
$row_count       = absint( get_theme_mod( 'paginae_manuscripti_illuminati_front_diary_count', 3 ) );
$annales_query   = paginae_manuscripti_illuminati_recent_query( 'post', $row_count );
$journal_posts   = paginae_manuscripti_illuminati_latest_journal_posts( $row_count );
$covenant_query  = paginae_manuscripti_illuminati_recently_modified_query( 'ligatura_covenant', $row_count );
$character_query = paginae_manuscripti_illuminati_recently_modified_query( 'ligatura_character', $card_count );
$wiki_query      = paginae_manuscripti_illuminati_recently_modified_query( 'ligatura_wiki', $card_count );
?>

<main id="primary" class="site-main manuscriptum-illuminatum-page manuscriptum-illuminatum-page--front">
	<?php paginae_manuscripti_illuminati_open_layout(); ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<header class="manuscriptum-illuminatum-page-header">
				<h1><?php the_title(); ?></h1>
			</header>
			<?php
			if ( trim( get_the_content() ) ) :
				?>
				<section class="manuscriptum-illuminatum-section manuscriptum-illuminatum-front-content">
					<div class="manuscriptum-illuminatum-content">
						<?php the_content(); ?>
					</div>
				</section>
				<?php
			endif;
		endwhile;
		?>

		<?php if ( $row_count > 0 && $annales_query->have_posts() ) : ?>
			<section class="manuscriptum-illuminatum-band manuscriptum-illuminatum-front-annales">
				<header class="manuscriptum-illuminatum-section-header">
					<h2><?php esc_html_e( 'Latest Annales', 'paginae-manuscripti-illuminati' ); ?></h2>
					<a href="<?php echo esc_url( paginae_manuscripti_illuminati_annales_url() ); ?>"><?php esc_html_e( 'All Annales', 'paginae-manuscripti-illuminati' ); ?></a>
				</header>
				<div class="manuscriptum-illuminatum-journal-list manuscriptum-illuminatum-chronicle-list">
					<?php
					while ( $annales_query->have_posts() ) :
						$annales_query->the_post();
						echo paginae_manuscripti_illuminati_render_annal_row( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared partial escapes row data.
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $row_count > 0 && $journal_posts ) : ?>
			<section class="manuscriptum-illuminatum-band manuscriptum-illuminatum-band--ruled">
				<header class="manuscriptum-illuminatum-section-header">
					<h2><?php esc_html_e( 'Latest Commentarii', 'paginae-manuscripti-illuminati' ); ?></h2>
					<?php if ( post_type_exists( 'ligatura_diary' ) ) : ?>
						<a href="<?php echo esc_url( paginae_manuscripti_illuminati_content_directory_url( 'ligatura_diary' ) ); ?>"><?php esc_html_e( 'All Commentarii', 'paginae-manuscripti-illuminati' ); ?></a>
					<?php endif; ?>
				</header>
				<div class="manuscriptum-illuminatum-journal-list manuscriptum-illuminatum-chronicle-list">
					<?php
					foreach ( $journal_posts as $journal_post ) :
						echo paginae_manuscripti_illuminati_render_journal_row( $journal_post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared partial escapes row data.
					endforeach;
					?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $row_count > 0 && $covenant_query->have_posts() ) : ?>
			<section class="manuscriptum-illuminatum-band">
				<header class="manuscriptum-illuminatum-section-header">
					<h2><?php esc_html_e( 'Latest Covenant News', 'paginae-manuscripti-illuminati' ); ?></h2>
					<?php if ( post_type_exists( 'ligatura_covenant' ) ) : ?>
						<a href="<?php echo esc_url( paginae_manuscripti_illuminati_content_directory_url( 'ligatura_covenant' ) ); ?>"><?php esc_html_e( 'All Covenant News', 'paginae-manuscripti-illuminati' ); ?></a>
					<?php endif; ?>
				</header>
				<div class="manuscriptum-illuminatum-journal-list manuscriptum-illuminatum-chronicle-list">
					<?php
					while ( $covenant_query->have_posts() ) :
						$covenant_query->the_post();
						echo paginae_manuscripti_illuminati_render_covenant_news_row( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared partial escapes row data.
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $character_query->have_posts() ) : ?>
			<section class="manuscriptum-illuminatum-band manuscriptum-illuminatum-band--ruled manuscriptum-illuminatum-front-personae">
				<header class="manuscriptum-illuminatum-section-header">
					<h2><?php esc_html_e( 'Latest Personae', 'paginae-manuscripti-illuminati' ); ?></h2>
					<?php if ( post_type_exists( 'ligatura_character' ) ) : ?>
						<a href="<?php echo esc_url( paginae_manuscripti_illuminati_content_directory_url( 'ligatura_character' ) ); ?>"><?php esc_html_e( 'All Personae', 'paginae-manuscripti-illuminati' ); ?></a>
					<?php endif; ?>
				</header>
				<div class="manuscriptum-illuminatum-card-grid manuscriptum-illuminatum-card-grid--compact">
					<?php
					while ( $character_query->have_posts() ) :
						$character_query->the_post();
						get_template_part( 'template-parts/content', 'character-card', array( 'show_fallback' => false ) );
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $wiki_query->have_posts() ) : ?>
			<section class="manuscriptum-illuminatum-band manuscriptum-illuminatum-front-wiki">
				<header class="manuscriptum-illuminatum-section-header">
					<h2><?php esc_html_e( 'Latest Specula', 'paginae-manuscripti-illuminati' ); ?></h2>
					<?php if ( post_type_exists( 'ligatura_wiki' ) ) : ?>
						<a href="<?php echo esc_url( paginae_manuscripti_illuminati_content_directory_url( 'ligatura_wiki' ) ); ?>"><?php esc_html_e( 'All Specula', 'paginae-manuscripti-illuminati' ); ?></a>
					<?php endif; ?>
				</header>
				<div class="manuscriptum-illuminatum-card-grid">
					<?php
					while ( $wiki_query->have_posts() ) :
						$wiki_query->the_post();
						get_template_part( 'template-parts/content', 'wiki-update' );
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>
	<?php paginae_manuscripti_illuminati_close_layout(); ?>
</main>

<?php
get_footer();
