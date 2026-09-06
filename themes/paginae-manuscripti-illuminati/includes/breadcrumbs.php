<?php
/**
 * Sitewide breadcrumb helpers.
 *
 * @package PaginaeManuscriptiIlluminati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the visitor-facing root label for campaign post types.
 *
 * @return array<string,string>
 */
function paginae_manuscripti_illuminati_breadcrumb_campaign_roots(): array {
	return array(
		'ligatura_wiki'      => __( 'Speculum', 'paginae-manuscripti-illuminati' ),
		'ligatura_character' => __( 'Personae', 'paginae-manuscripti-illuminati' ),
		'ligatura_diary'     => __( 'Commentarii', 'paginae-manuscripti-illuminati' ),
		'ligatura_covenant'  => __( 'Covenant Records', 'paginae-manuscripti-illuminati' ),
	);
}

/**
 * Create a breadcrumb item.
 *
 * @return array{label:string,url:string}
 */
function paginae_manuscripti_illuminati_breadcrumb_item( string $label, string $url = '' ): array {
	return array(
		'label' => trim( wp_strip_all_tags( $label ) ),
		'url'   => $url,
	);
}

/**
 * Add the configured campaign directory to a breadcrumb trail.
 *
 * @param array<int,array{label:string,url:string}> $items     Breadcrumb items.
 * @param string                                     $post_type Campaign post type.
 */
function paginae_manuscripti_illuminati_breadcrumb_add_campaign_root( array &$items, string $post_type ): void {
	$roots = paginae_manuscripti_illuminati_breadcrumb_campaign_roots();

	if ( isset( $roots[ $post_type ] ) ) {
		$items[] = paginae_manuscripti_illuminati_breadcrumb_item(
			$roots[ $post_type ],
			paginae_manuscripti_illuminati_content_directory_url( $post_type )
		);
	}
}

/**
 * Build the breadcrumb trail for the current public request.
 *
 * The final item deliberately has no URL: it represents the current page.
 *
 * @return array<int,array{label:string,url:string}>
 */
function paginae_manuscripti_illuminati_breadcrumb_items(): array {
	if ( is_front_page() ) {
		return array();
	}

	$items = array(
		paginae_manuscripti_illuminati_breadcrumb_item( __( 'Home', 'paginae-manuscripti-illuminati' ), home_url( '/' ) ),
	);
	$roots = paginae_manuscripti_illuminati_breadcrumb_campaign_roots();

	if ( is_404() ) {
		$items[] = paginae_manuscripti_illuminati_breadcrumb_item( __( 'Page Not Found', 'paginae-manuscripti-illuminati' ) );
		return $items;
	}

	if ( is_search() ) {
		$items[] = paginae_manuscripti_illuminati_breadcrumb_item( __( 'Search Results', 'paginae-manuscripti-illuminati' ) );
		return $items;
	}

	$journal_persona_slug = (string) get_query_var( 'ligatura_journal_persona' );

	if ( '' !== $journal_persona_slug ) {
		$persona = get_page_by_path( sanitize_title( $journal_persona_slug ), OBJECT, 'ligatura_character' );
		paginae_manuscripti_illuminati_breadcrumb_add_campaign_root( $items, 'ligatura_diary' );

		if ( $persona instanceof WP_Post && paginae_manuscripti_illuminati_can_read_entry( $persona->ID ) ) {
			$items[] = paginae_manuscripti_illuminati_breadcrumb_item( get_the_title( $persona ) );
		}

		return $items;
	}

	$covenant_scopes = array(
		'ligatura_covenant_entry_type' => 'ligatura_entry_type',
		'ligatura_covenant_topic'      => 'ligatura_saga_topic',
	);

	foreach ( $covenant_scopes as $query_var => $taxonomy ) {
		$slug = sanitize_title( (string) get_query_var( $query_var ) );

		if ( '' === $slug ) {
			continue;
		}

		$term = get_term_by( 'slug', $slug, $taxonomy );
		paginae_manuscripti_illuminati_breadcrumb_add_campaign_root( $items, 'ligatura_covenant' );

		if ( $term instanceof WP_Term ) {
			$items[] = paginae_manuscripti_illuminati_breadcrumb_item( $term->name );
		}

		return $items;
	}

	if ( is_page() ) {
		$page = get_queried_object();

		if ( $page instanceof WP_Post ) {
			$ancestor_ids = array_reverse( get_post_ancestors( $page ) );

			foreach ( $ancestor_ids as $ancestor_id ) {
				$items[] = paginae_manuscripti_illuminati_breadcrumb_item(
					get_the_title( $ancestor_id ),
					get_permalink( $ancestor_id )
				);
			}

			$items[] = paginae_manuscripti_illuminati_breadcrumb_item( get_the_title( $page ) );
		}

		return $items;
	}

	if ( is_home() ) {
		$posts_page_id = (int) get_option( 'page_for_posts' );
		$items[]       = paginae_manuscripti_illuminati_breadcrumb_item(
			$posts_page_id ? get_the_title( $posts_page_id ) : __( 'Posts', 'paginae-manuscripti-illuminati' )
		);
		return $items;
	}

	if ( is_singular() ) {
		$post = get_queried_object();

		if ( $post instanceof WP_Post ) {
			$post_type = get_post_type( $post );

			if ( isset( $roots[ $post_type ] ) ) {
				paginae_manuscripti_illuminati_breadcrumb_add_campaign_root( $items, $post_type );

				if ( 'ligatura_wiki' === $post_type ) {
					$entry_types = get_the_terms( $post, 'ligatura_entry_type' );

					if ( is_array( $entry_types ) && 1 === count( $entry_types ) ) {
						$entry_type_url = get_term_link( $entry_types[0] );

						if ( ! is_wp_error( $entry_type_url ) ) {
							$items[] = paginae_manuscripti_illuminati_breadcrumb_item( $entry_types[0]->name, $entry_type_url );
						}
					}
				}
			} elseif ( 'post' === $post_type ) {
				$posts_page_id = (int) get_option( 'page_for_posts' );

				if ( $posts_page_id ) {
					$items[] = paginae_manuscripti_illuminati_breadcrumb_item(
						get_the_title( $posts_page_id ),
						get_permalink( $posts_page_id )
					);
				}
			} else {
				$post_type_object = get_post_type_object( $post_type );

				if ( $post_type_object && $post_type_object->has_archive ) {
					$archive_url = get_post_type_archive_link( $post_type );

					if ( $archive_url ) {
						$items[] = paginae_manuscripti_illuminati_breadcrumb_item( $post_type_object->labels->name, $archive_url );
					}
				}
			}

			$items[] = paginae_manuscripti_illuminati_breadcrumb_item( get_the_title( $post ) );
		}

		return $items;
	}

	if ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {
			if ( 'ligatura_entry_type' === $term->taxonomy ) {
				paginae_manuscripti_illuminati_breadcrumb_add_campaign_root( $items, 'ligatura_wiki' );
			} elseif ( 'ligatura_saga_topic' === $term->taxonomy && paginae_manuscripti_illuminati_is_speculum_saga_topic_query() ) {
				paginae_manuscripti_illuminati_breadcrumb_add_campaign_root( $items, 'ligatura_wiki' );
			}

			$items[] = paginae_manuscripti_illuminati_breadcrumb_item( $term->name );
		}

		return $items;
	}

	if ( is_post_type_archive() ) {
		$post_type = get_query_var( 'post_type' );
		$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;

		if ( is_string( $post_type ) && isset( $roots[ $post_type ] ) ) {
			$items[] = paginae_manuscripti_illuminati_breadcrumb_item( $roots[ $post_type ] );
		} elseif ( is_string( $post_type ) ) {
			$post_type_object = get_post_type_object( $post_type );
			$items[]          = paginae_manuscripti_illuminati_breadcrumb_item(
				$post_type_object ? $post_type_object->labels->name : post_type_archive_title( '', false )
			);
		}

		return $items;
	}

	if ( is_archive() ) {
		$items[] = paginae_manuscripti_illuminati_breadcrumb_item( get_the_archive_title() );
		return $items;
	}

	$title = wp_get_document_title();

	if ( $title ) {
		$items[] = paginae_manuscripti_illuminati_breadcrumb_item( $title );
	}

	return $items;
}

/**
 * Render the sitewide breadcrumb navigation.
 */
function paginae_manuscripti_illuminati_render_breadcrumbs(): void {
	$items = array_values(
		array_filter(
			paginae_manuscripti_illuminati_breadcrumb_items(),
			static fn( array $item ): bool => '' !== $item['label']
		)
	);

	if ( count( $items ) < 2 ) {
		return;
	}
	?>
	<nav class="manuscriptum-illuminatum-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'paginae-manuscripti-illuminati' ); ?>">
		<ol class="manuscriptum-illuminatum-breadcrumb__list">
			<?php foreach ( $items as $index => $item ) : ?>
				<?php $is_current = count( $items ) - 1 === $index; ?>
				<li class="manuscriptum-illuminatum-breadcrumb__item">
					<?php if ( 0 < $index ) : ?>
						<span class="manuscriptum-illuminatum-breadcrumb__separator" aria-hidden="true">›</span>
					<?php endif; ?>
					<?php if ( ! $is_current && $item['url'] ) : ?>
						<a class="manuscriptum-illuminatum-breadcrumb__label" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
					<?php else : ?>
						<span class="manuscriptum-illuminatum-breadcrumb__label"<?php echo $is_current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $item['label'] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>
	<?php
}
