<?php
/**
 * Header template.
 *
 * @package PaginaeManuscriptiIlluminati
 */

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'paginae-manuscripti-illuminati' ); ?></a>
<header class="site-header">
	<div class="site-header__inner">
		<?php if ( has_custom_logo() ) : ?>
			<div class="site-brand site-brand--logo">
				<?php the_custom_logo(); ?>
			</div>
		<?php else : ?>
			<a class="site-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<span class="site-brand__mark" aria-hidden="true"><?php echo esc_html( get_theme_mod( 'paginae_manuscripti_illuminati_site_mark', 'MI' ) ); ?></span>
				<span class="site-brand__text">
					<strong><?php bloginfo( 'name' ); ?></strong>
					<small><?php bloginfo( 'description' ); ?></small>
				</span>
			</a>
		<?php endif; ?>
		<nav class="site-nav" aria-label="<?php esc_attr_e( 'Primary navigation', 'paginae-manuscripti-illuminati' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'site-nav__menu',
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>
	</div>
</header>
