<?php
/**
 * Left sidebar.
 *
 * @package PaginaeManuscriptiIlluminati
 */

if ( ! is_active_sidebar( 'sidebar-left' ) ) {
	return;
}
?>

<aside class="manuscriptum-illuminatum-sidebar manuscriptum-illuminatum-sidebar--left" aria-label="<?php esc_attr_e( 'Left sidebar', 'paginae-manuscripti-illuminati' ); ?>">
	<?php dynamic_sidebar( 'sidebar-left' ); ?>
</aside>
