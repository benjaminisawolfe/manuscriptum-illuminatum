<?php
/**
 * Footer template.
 *
 * @package PaginaeManuscriptiIlluminati
 */

?>
<footer class="site-footer">
	<div class="site-footer__inner">
		<?php $copyright_text = paginae_manuscripti_illuminati_footer_copyright_text(); ?>
		<?php if ( '' !== $copyright_text ) : ?>
			<p><?php echo esc_html( $copyright_text ); ?></p>
		<?php endif; ?>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
