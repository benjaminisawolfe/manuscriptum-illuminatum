<?php
/**
 * Right sidebar.
 *
 * @package PaginaeManuscriptiIlluminati
 */

if ( ! is_active_sidebar( 'sidebar-right' ) ) {
	return;
}
?>

<aside class="manuscriptum-illuminatum-sidebar manuscriptum-illuminatum-sidebar--right" aria-label="<?php esc_attr_e( 'Right sidebar', 'paginae-manuscripti-illuminati' ); ?>">
	<?php dynamic_sidebar( 'sidebar-right' ); ?>
</aside>
