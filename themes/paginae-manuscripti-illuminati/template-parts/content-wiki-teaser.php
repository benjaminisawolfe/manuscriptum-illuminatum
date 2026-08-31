<?php
/**
 * Shared text-only Speculum teaser.
 *
 * @package PaginaeManuscriptiIlluminati
 */

$post_id         = absint( $args['post_id'] ?? 0 );
$show_entry_type = ! empty( $args['show_entry_type'] );
$show_modified   = ! empty( $args['show_modified'] );
$summary         = paginae_manuscripti_illuminati_meta( $post_id, 'ligatura_public_summary' );
$modified_date   = get_the_modified_date( get_option( 'date_format' ), $post_id );
$modified_iso    = get_the_modified_time( 'c', true, $post_id );
?>

<article class="manuscriptum-illuminatum-card manuscriptum-illuminatum-card--wiki-update manuscriptum-illuminatum-directory-teaser manuscriptum-illuminatum-speculum-teaser" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" data-modified="<?php echo esc_attr( $modified_iso ); ?>">
	<div class="manuscriptum-illuminatum-card__body">
		<?php if ( $show_entry_type ) : ?>
			<p class="manuscriptum-illuminatum-card__meta"><?php echo wp_kses_post( paginae_manuscripti_illuminati_term_line( $post_id, 'ligatura_entry_type' ) ); ?></p>
		<?php endif; ?>
		<h3><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></h3>
		<p class="manuscriptum-illuminatum-speculum-teaser__excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $summary ? $summary : get_the_excerpt( $post_id ) ), 30 ) ); ?></p>
		<?php if ( $show_modified ) : ?>
			<p class="manuscriptum-illuminatum-card__modified">
				<?php esc_html_e( 'Last edited:', 'paginae-manuscripti-illuminati' ); ?>
				<time datetime="<?php echo esc_attr( $modified_iso ); ?>"><?php echo esc_html( $modified_date ); ?></time>
			</p>
		<?php endif; ?>
	</div>
</article>
