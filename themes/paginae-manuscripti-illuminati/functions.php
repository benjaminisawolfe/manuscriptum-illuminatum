<?php
/**
 * Theme functions.
 *
 * @package PaginaeManuscriptiIlluminati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_template_directory() . '/includes/customizer.php';
require_once get_template_directory() . '/includes/breadcrumbs.php';

const PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_STRUCTURE_VERSION  = '2026-08-30-installability-v1';
const PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_STRUCTURE_OPTION   = 'paginae_manuscripti_illuminati_site_structure_version';
const PAGINAE_MANUSCRIPTI_ILLUMINATI_ANNALES_MARKER_META     = '_paginae_manuscripti_illuminati_annales_root';
const PAGINAE_MANUSCRIPTI_ILLUMINATI_ANNALES_ERROR_OPTION    = 'paginae_manuscripti_illuminati_annales_migration_error';
const PAGINAE_MANUSCRIPTI_ILLUMINATI_DEFAULT_PAGE_META       = '_paginae_manuscripti_illuminati_default_page';
const PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_SETUP_ERROR_OPTION = 'paginae_manuscripti_illuminati_site_setup_errors';

require_once get_template_directory() . '/includes/site-setup.php';

/**
 * Theme setup.
 */
function paginae_manuscripti_illuminati_theme_setup(): void {
	load_theme_textdomain( 'paginae-manuscripti-illuminati', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo' );
	add_theme_support( 'site-icon' );
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'paginae-manuscripti-illuminati' ),
		)
	);
}
add_action( 'after_setup_theme', 'paginae_manuscripti_illuminati_theme_setup' );

/**
 * Return the editable Page used as the ordinary Posts index.
 */
function paginae_manuscripti_illuminati_annales_page(): ?WP_Post {
	$posts_page_id = (int) get_option( 'page_for_posts' );
	$posts_page    = $posts_page_id ? get_post( $posts_page_id ) : null;

	if ( $posts_page instanceof WP_Post && 'page' === $posts_page->post_type && ( 'news' === $posts_page->post_name || get_post_meta( $posts_page->ID, PAGINAE_MANUSCRIPTI_ILLUMINATI_ANNALES_MARKER_META, true ) ) ) {
		return $posts_page;
	}

	$marked_pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
			'posts_per_page' => 1,
			'meta_key'       => PAGINAE_MANUSCRIPTI_ILLUMINATI_ANNALES_MARKER_META,
			'meta_value'     => '1',
			'no_found_rows'  => true,
		)
	);

	if ( isset( $marked_pages[0] ) && $marked_pages[0] instanceof WP_Post ) {
		return $marked_pages[0];
	}

	return null;
}

/**
 * Whether the Annales migration has a marked Posts Page at the canonical slug.
 */
function paginae_manuscripti_illuminati_annales_migration_succeeded(): bool {
	$page = paginae_manuscripti_illuminati_annales_page();

	return $page instanceof WP_Post
		&& 'news' === $page->post_name
		&& (int) get_option( 'page_for_posts' ) === $page->ID
		&& '1' === (string) get_post_meta( $page->ID, PAGINAE_MANUSCRIPTI_ILLUMINATI_ANNALES_MARKER_META, true );
}

/**
 * Return the visitor-facing Annales index URL.
 */
function paginae_manuscripti_illuminati_annales_url(): string {
	$page = paginae_manuscripti_illuminati_annales_page();

	if ( $page instanceof WP_Post ) {
		return get_permalink( $page );
	}

	return home_url( user_trailingslashit( 'news' ) );
}

/**
 * Migrate Annales to its marked /news/ Posts Page without claiming another Page.
 *
 * Existing Page title and content are deliberately preserved.
 *
 * @return bool|WP_Error True on success or a configuration error.
 */
function paginae_manuscripti_illuminati_migrate_annales(): bool|WP_Error {
	$page = paginae_manuscripti_illuminati_annales_page();
	$news = get_page_by_path( 'news', OBJECT, 'page' );

	if ( $news instanceof WP_Post && ( ! $page instanceof WP_Post || $news->ID !== $page->ID ) ) {
		return new WP_Error(
			'paginae_manuscripti_illuminati_annales_slug_collision',
			__( 'The slug "news" is already in use by another Page. Resolve that conflict before enabling the Annales /news/ permalink.', 'paginae-manuscripti-illuminati' ),
			array( 'status' => 409 )
		);
	}

	if ( ! $page ) {
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Annales', 'paginae-manuscripti-illuminati' ),
				'post_name'    => 'news',
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		$page = get_post( $page_id );
	}

	if ( ! $page instanceof WP_Post ) {
		return new WP_Error( 'paginae_manuscripti_illuminati_annales_page_missing', __( 'The Annales root Page could not be found or created.', 'paginae-manuscripti-illuminati' ) );
	}

	if ( 'news' !== $page->post_name ) {
		$updated = wp_update_post(
			array(
				'ID'        => $page->ID,
				'post_name' => 'news',
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$page = get_post( $page->ID );
	}

	if ( ! $page instanceof WP_Post || 'news' !== $page->post_name ) {
		return new WP_Error( 'paginae_manuscripti_illuminati_annales_slug_failed', __( 'WordPress could not assign the canonical "news" slug to the Annales Page.', 'paginae-manuscripti-illuminati' ) );
	}

	update_post_meta( $page->ID, PAGINAE_MANUSCRIPTI_ILLUMINATI_ANNALES_MARKER_META, '1' );
	update_post_meta( $page->ID, PAGINAE_MANUSCRIPTI_ILLUMINATI_DEFAULT_PAGE_META, 'annales' );
	update_option( 'page_for_posts', $page->ID );

	paginae_manuscripti_illuminati_register_news_permastruct();
	flush_rewrite_rules( false );
	delete_option( PAGINAE_MANUSCRIPTI_ILLUMINATI_ANNALES_ERROR_OPTION );

	return true;
}

/**
 * Display an actionable error while an Annales migration collision is unresolved.
 */
function paginae_manuscripti_illuminati_annales_migration_notice(): void {
	$message = (string) get_option( PAGINAE_MANUSCRIPTI_ILLUMINATI_ANNALES_ERROR_OPTION, '' );

	if ( '' !== $message && current_user_can( 'manage_options' ) ) {
		printf( '<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>', esc_html__( 'Annales migration blocked.', 'paginae-manuscripti-illuminati' ), esc_html( $message ) );
	}
}
add_action( 'admin_notices', 'paginae_manuscripti_illuminati_annales_migration_notice' );

/**
 * Register the native Annales Post permastruct, including standard endpoints.
 */
function paginae_manuscripti_illuminati_register_news_permastruct(): void {
	if ( ! paginae_manuscripti_illuminati_annales_migration_succeeded() ) {
		return;
	}

	global $wp_rewrite;

	if ( ! $wp_rewrite instanceof WP_Rewrite ) {
		return;
	}

	$original_root    = $wp_rewrite->root;
	$wp_rewrite->root = '';

	add_permastruct(
		'paginae_manuscripti_illuminati_annales',
		'news/%postname%',
		array(
			'with_front' => false,
			'ep_mask'    => EP_PERMALINK,
			'paged'      => true,
			'feed'       => true,
			'forcomments' => true,
			'walk_dirs'  => false,
			'endpoints'  => true,
		)
	);

	$wp_rewrite->root = $original_root;
}
add_action( 'init', 'paginae_manuscripti_illuminati_register_news_permastruct', 12 );

/**
 * Expose the explicit administrator migration operation for diagnostics/retry.
 */
function paginae_manuscripti_illuminati_register_annales_rest_route(): void {
	$status_callback = static function () {
		global $wp_rewrite;

		$rules      = $wp_rewrite instanceof WP_Rewrite ? $wp_rewrite->wp_rewrite_rules() : array();
		$news_rules = array();

		foreach ( is_array( $rules ) ? $rules : array() as $regex => $query ) {
			if ( str_starts_with( $regex, 'news/' ) ) {
				$news_rules[ $regex ] = $query;
			}
		}

		return rest_ensure_response(
			array(
				'ready'       => paginae_manuscripti_illuminati_annales_migration_succeeded(),
				'permastruct' => $wp_rewrite instanceof WP_Rewrite ? ( $wp_rewrite->extra_permastructs['paginae_manuscripti_illuminati_annales'] ?? null ) : null,
				'rules'       => $news_rules,
			)
		);
	};

	register_rest_route(
		'paginae-manuscripti-illuminati/v1',
		'/annales/migrate',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
			'callback'            => static function () {
				$result = paginae_manuscripti_illuminati_migrate_annales();

				return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'migrated' => true ) );
			},
		)
	);

	register_rest_route(
		'paginae-manuscripti-illuminati/v1',
		'/annales/status',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
			'callback'            => $status_callback,
		)
	);
}
add_action( 'rest_api_init', 'paginae_manuscripti_illuminati_register_annales_rest_route' );

/**
 * Generate canonical ordinary Post URLs beneath /news/.
 */
function paginae_manuscripti_illuminati_news_post_link( string $link, WP_Post $post ): string {
	if ( 'post' !== $post->post_type || ! paginae_manuscripti_illuminati_annales_migration_succeeded() ) {
		return $link;
	}

	$slug = '' !== $post->post_name ? $post->post_name : sanitize_title( $post->post_title );

	return home_url( user_trailingslashit( 'news/' . ( $slug ?: $post->ID ) ) );
}
add_filter( 'post_link', 'paginae_manuscripti_illuminati_news_post_link', 10, 2 );

/**
 * Preserve native feed, embed, and other endpoints below canonical Annales URLs.
 */
function paginae_manuscripti_illuminati_preserve_annales_endpoints(): void {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! paginae_manuscripti_illuminati_annales_migration_succeeded() ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path        = trim( (string) wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );
	$home_path   = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

	if ( '' !== $home_path && str_starts_with( $path, $home_path . '/' ) ) {
		$path = substr( $path, strlen( $home_path ) + 1 );
	}

	$segments = array_values( array_filter( explode( '/', $path ), 'strlen' ) );

	if ( 2 >= count( $segments ) || 'news' !== sanitize_title( rawurldecode( $segments[0] ) ) ) {
		return;
	}

	$post = get_page_by_path( sanitize_title( rawurldecode( $segments[1] ) ), OBJECT, 'post' );

	if ( $post instanceof WP_Post && current_user_can( 'read_post', $post->ID ) ) {
		remove_action( 'template_redirect', 'redirect_canonical' );
	}
}
add_action( 'template_redirect', 'paginae_manuscripti_illuminati_preserve_annales_endpoints', 1 );

/**
 * Register widget areas.
 */
function paginae_manuscripti_illuminati_widgets_init(): void {
	register_sidebar(
		array(
			'name'          => __( 'Left Sidebar', 'paginae-manuscripti-illuminati' ),
			'id'            => 'sidebar-left',
			'description'   => __( 'Left marginalia column. Appears when the selected layout includes a left sidebar.', 'paginae-manuscripti-illuminati' ),
			'before_widget' => '<section id="%1$s" class="widget manuscriptum-illuminatum-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title manuscriptum-illuminatum-widget__title">',
			'after_title'   => '</h2>',
		)
	);

	register_sidebar(
		array(
			'name'          => __( 'Right Sidebar', 'paginae-manuscripti-illuminati' ),
			'id'            => 'sidebar-right',
			'description'   => __( 'Right marginalia column. Appears when the selected layout includes a right sidebar.', 'paginae-manuscripti-illuminati' ),
			'before_widget' => '<section id="%1$s" class="widget manuscriptum-illuminatum-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title manuscriptum-illuminatum-widget__title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'paginae_manuscripti_illuminati_widgets_init' );

/**
 * Enqueue styles and scripts.
 */
function paginae_manuscripti_illuminati_theme_assets(): void {
	$fonts_url = paginae_manuscripti_illuminati_google_fonts_url();

	wp_enqueue_style(
		'paginae-manuscripti-illuminati-style',
		get_stylesheet_uri(),
		array(),
		wp_get_theme()->get( 'Version' )
	);

	if ( $fonts_url ) {
		wp_enqueue_style(
			'manuscriptum-illuminatum-google-fonts',
			$fonts_url,
			array(),
			null
		);
	}

	wp_enqueue_style(
		'paginae-manuscripti-illuminati-main',
		get_template_directory_uri() . '/assets/css/main.css',
		$fonts_url ? array( 'paginae-manuscripti-illuminati-style', 'manuscriptum-illuminatum-google-fonts' ) : array( 'paginae-manuscripti-illuminati-style' ),
		wp_get_theme()->get( 'Version' )
	);

	wp_enqueue_style(
		'paginae-manuscripti-illuminati-print',
		get_template_directory_uri() . '/assets/css/print.css',
		array( 'paginae-manuscripti-illuminati-main' ),
		wp_get_theme()->get( 'Version' ),
		'print'
	);

	wp_enqueue_script(
		'paginae-manuscripti-illuminati-main',
		get_template_directory_uri() . '/assets/js/main.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);

	wp_add_inline_style( 'paginae-manuscripti-illuminati-main', paginae_manuscripti_illuminati_custom_properties() );
}
add_action( 'wp_enqueue_scripts', 'paginae_manuscripti_illuminati_theme_assets' );

/**
 * Determine whether the current Saga Topic request explicitly targets Speculum entries.
 */
function paginae_manuscripti_illuminati_is_speculum_saga_topic_query(): bool {
	if ( ! is_tax( 'ligatura_saga_topic' ) ) {
		return false;
	}

	$post_type = get_query_var( 'post_type' );

	return 'ligatura_wiki' === $post_type || array( 'ligatura_wiki' ) === $post_type;
}

/**
 * Keep generic Saga Topic archives mixed while preserving the Speculum collection template.
 */
function paginae_manuscripti_illuminati_scope_saga_topic_template( string $template ): string {
	if ( ! is_tax( 'ligatura_saga_topic' ) || paginae_manuscripti_illuminati_is_speculum_saga_topic_query() ) {
		return $template;
	}

	$archive_template = locate_template( array( 'archive.php' ), false, false );

	return $archive_template ?: $template;
}
add_filter( 'taxonomy_template', 'paginae_manuscripti_illuminati_scope_saga_topic_template', 20 );

/**
 * Font choices supported by the theme.
 *
 * @return array<string,array<string,string>>
 */
function paginae_manuscripti_illuminati_font_choices(): array {
	return array(
		'EB Garamond'       => array(
			'label'  => 'EB Garamond',
			'stack'  => '"EB Garamond", Georgia, serif',
			'google' => 'EB Garamond:wght@400;500;600;700',
		),
		'Cardo'             => array(
			'label'  => 'Cardo',
			'stack'  => '"Cardo", Georgia, serif',
			'google' => 'Cardo:wght@400;700',
		),
		'Lora'              => array(
			'label'  => 'Lora',
			'stack'  => '"Lora", Georgia, serif',
			'google' => 'Lora:wght@400;500;600;700',
		),
		'Cormorant Garamond' => array(
			'label'  => 'Cormorant Garamond',
			'stack'  => '"Cormorant Garamond", Georgia, serif',
			'google' => 'Cormorant Garamond:wght@400;500;600;700',
		),
		'Cinzel'            => array(
			'label'  => 'Cinzel',
			'stack'  => '"Cinzel", Georgia, serif',
			'google' => 'Cinzel:wght@400;500;600;700',
		),
		'Marcellus'         => array(
			'label'  => 'Marcellus',
			'stack'  => '"Marcellus", Georgia, serif',
			'google' => 'Marcellus',
		),
		'Almendra'          => array(
			'label'  => 'Almendra',
			'stack'  => '"Almendra", Georgia, serif',
			'google' => 'Almendra:wght@400;700',
		),
		'Uncial Antiqua'    => array(
			'label'  => 'Uncial Antiqua',
			'stack'  => '"Uncial Antiqua", Georgia, serif',
			'google' => 'Uncial Antiqua',
		),
		'MedievalSharp'     => array(
			'label'  => 'MedievalSharp',
			'stack'  => '"MedievalSharp", Georgia, serif',
			'google' => 'MedievalSharp',
		),
	);
}

/**
 * Get a sanitized selected font.
 *
 * @param string $setting Theme mod setting.
 * @param string $default Default font name.
 */
function paginae_manuscripti_illuminati_selected_font( string $setting, string $default ): string {
	$fonts = paginae_manuscripti_illuminati_font_choices();
	$value = get_theme_mod( $setting, $default );
	$value = is_string( $value ) ? $value : $default;

	return isset( $fonts[ $value ] ) ? $value : $default;
}

/**
 * Build a Google Fonts URL for only the selected families.
 */
function paginae_manuscripti_illuminati_google_fonts_url(): string {
	$fonts    = paginae_manuscripti_illuminati_font_choices();
	$selected = array_unique(
		array(
			paginae_manuscripti_illuminati_selected_font( 'paginae_manuscripti_illuminati_font_body', 'EB Garamond' ),
			paginae_manuscripti_illuminati_selected_font( 'paginae_manuscripti_illuminati_font_headings', 'Cinzel' ),
			paginae_manuscripti_illuminati_selected_font( 'paginae_manuscripti_illuminati_font_navigation', 'Marcellus' ),
			paginae_manuscripti_illuminati_selected_font( 'paginae_manuscripti_illuminati_font_accent', 'Uncial Antiqua' ),
		)
	);
	$families = array();

	foreach ( $selected as $font_name ) {
		if ( isset( $fonts[ $font_name ]['google'] ) ) {
			$families[] = rawurlencode( $fonts[ $font_name ]['google'] );
		}
	}

	if ( empty( $families ) ) {
		return '';
	}

	return 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $families ) . '&display=swap';
}

/**
 * Return a CSS font stack for a selected font setting.
 *
 * @param string $setting Theme mod setting.
 * @param string $default Default font name.
 */
function paginae_manuscripti_illuminati_font_stack( string $setting, string $default ): string {
	$fonts     = paginae_manuscripti_illuminati_font_choices();
	$font_name = paginae_manuscripti_illuminati_selected_font( $setting, $default );

	return $fonts[ $font_name ]['stack'];
}

/**
 * Build dynamic CSS from Customizer settings.
 */
function paginae_manuscripti_illuminati_custom_properties(): string {
	$content_width = min( 1440, max( 880, absint( get_theme_mod( 'paginae_manuscripti_illuminati_content_width', 1120 ) ) ) );
	$texture       = get_theme_mod( 'paginae_manuscripti_illuminati_show_texture', true )
		? 'url("' . esc_url_raw( get_template_directory_uri() . '/assets/images/parchment-texture.svg' ) . '")'
		: 'none';

	$properties = array(
		'--manuscriptum-illuminatum-ink'            => sanitize_hex_color( get_theme_mod( 'paginae_manuscripti_illuminati_color_ink', '#20170f' ) ) ?: '#20170f',
		'--manuscriptum-illuminatum-parchment'      => sanitize_hex_color( get_theme_mod( 'paginae_manuscripti_illuminati_color_parchment', '#f7efd9' ) ) ?: '#f7efd9',
		'--manuscriptum-illuminatum-red'            => sanitize_hex_color( get_theme_mod( 'paginae_manuscripti_illuminati_color_rubric', '#8f211b' ) ) ?: '#8f211b',
		'--manuscriptum-illuminatum-gold'           => sanitize_hex_color( get_theme_mod( 'paginae_manuscripti_illuminati_color_gold', '#b8851d' ) ) ?: '#b8851d',
		'--manuscriptum-illuminatum-blue'           => sanitize_hex_color( get_theme_mod( 'paginae_manuscripti_illuminati_color_blue', '#24446b' ) ) ?: '#24446b',
		'--manuscriptum-illuminatum-green'          => sanitize_hex_color( get_theme_mod( 'paginae_manuscripti_illuminati_color_green', '#445c3a' ) ) ?: '#445c3a',
		'--manuscriptum-illuminatum-content'        => $content_width . 'px',
		'--manuscriptum-illuminatum-texture-image'  => $texture,
		'--font-body'          => paginae_manuscripti_illuminati_font_stack( 'paginae_manuscripti_illuminati_font_body', 'EB Garamond' ),
		'--font-headings'      => paginae_manuscripti_illuminati_font_stack( 'paginae_manuscripti_illuminati_font_headings', 'Cinzel' ),
		'--font-navigation'    => paginae_manuscripti_illuminati_font_stack( 'paginae_manuscripti_illuminati_font_navigation', 'Marcellus' ),
		'--font-accent'        => paginae_manuscripti_illuminati_font_stack( 'paginae_manuscripti_illuminati_font_accent', 'Uncial Antiqua' ),
	);

	$css = ':root{';

	foreach ( $properties as $name => $value ) {
		$css .= $name . ':' . $value . ';';
	}

	$css .= '}';

	return $css;
}

/**
 * Return the editable footer copyright text.
 */
function paginae_manuscripti_illuminati_footer_copyright_text(): string {
	$text = get_theme_mod( 'paginae_manuscripti_illuminati_footer_copyright_text', null );

	if ( null === $text || false === $text ) {
		$text = '© {year} Benjamin Wolfe';
	}

	$text = is_string( $text ) ? trim( sanitize_text_field( $text ) ) : '';

	return str_replace( '{year}', wp_date( 'Y' ), $text );
}

/**
 * Add layout and Customizer classes to the body.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function paginae_manuscripti_illuminati_body_classes( array $classes ): array {
	$classes[] = 'manuscriptum-illuminatum-layout-selected-' . paginae_manuscripti_illuminati_layout_mode();

	if ( ! get_theme_mod( 'paginae_manuscripti_illuminati_sticky_header', true ) ) {
		$classes[] = 'manuscriptum-illuminatum-header-static';
	}

	if ( ! get_theme_mod( 'paginae_manuscripti_illuminati_show_texture', true ) ) {
		$classes[] = 'manuscriptum-illuminatum-no-texture';
	}

	return $classes;
}
add_filter( 'body_class', 'paginae_manuscripti_illuminati_body_classes' );

/**
 * Return selected layout mode.
 */
function paginae_manuscripti_illuminati_layout_mode(): string {
	$mode = get_theme_mod( 'paginae_manuscripti_illuminati_layout_mode', 'right_sidebar' );

	return paginae_manuscripti_illuminati_sanitize_layout_mode( is_string( $mode ) ? $mode : 'right_sidebar' );
}

/**
 * Determine whether a sidebar should render.
 *
 * @param string $side left|right.
 */
function paginae_manuscripti_illuminati_has_sidebar( string $side ): bool {
	$mode = paginae_manuscripti_illuminati_layout_mode();

	if ( 'left' === $side && ! in_array( $mode, array( 'left_sidebar', 'both_sidebars' ), true ) ) {
		return false;
	}

	if ( 'right' === $side && ! in_array( $mode, array( 'right_sidebar', 'both_sidebars' ), true ) ) {
		return false;
	}

	return is_active_sidebar( 'sidebar-' . $side );
}

/**
 * Return layout class based on active sidebars.
 */
function paginae_manuscripti_illuminati_layout_class(): string {
	$left  = paginae_manuscripti_illuminati_has_sidebar( 'left' );
	$right = paginae_manuscripti_illuminati_has_sidebar( 'right' );

	if ( $left && $right ) {
		return 'manuscriptum-illuminatum-layout--both';
	}

	if ( $left ) {
		return 'manuscriptum-illuminatum-layout--left';
	}

	if ( $right ) {
		return 'manuscriptum-illuminatum-layout--right';
	}

	return 'manuscriptum-illuminatum-layout--none';
}

/**
 * Open the content/sidebar layout shell.
 */
function paginae_manuscripti_illuminati_open_layout(): void {
	echo '<div class="manuscriptum-illuminatum-layout ' . esc_attr( paginae_manuscripti_illuminati_layout_class() ) . '">';

	if ( paginae_manuscripti_illuminati_has_sidebar( 'left' ) ) {
		get_sidebar( 'left' );
	}

	echo '<div class="manuscriptum-illuminatum-layout__content">';
	paginae_manuscripti_illuminati_render_breadcrumbs();
}

/**
 * Close the content/sidebar layout shell.
 */
function paginae_manuscripti_illuminati_close_layout(): void {
	echo '</div>';

	if ( paginae_manuscripti_illuminati_has_sidebar( 'right' ) ) {
		get_sidebar( 'right' );
	}

	echo '</div>';
}

/**
 * Return the front-end directory URL for campaign content.
 */
function paginae_manuscripti_illuminati_content_directory_url( string $post_type ): string {
	if ( function_exists( 'ligatura_get_campaign_content_directory_url' ) ) {
		$url = ligatura_get_campaign_content_directory_url( $post_type );

		if ( $url ) {
			return $url;
		}
	}

	$fallbacks = array(
		'ligatura_wiki'      => 'wiki',
		'ligatura_character' => 'characters',
		'ligatura_diary'     => 'journals',
		'ligatura_covenant'  => 'covenant-records',
	);

	return isset( $fallbacks[ $post_type ] ) ? home_url( user_trailingslashit( $fallbacks[ $post_type ] ) ) : home_url( '/' );
}

/**
 * Get a single post meta value.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 */
function paginae_manuscripti_illuminati_meta( int $post_id, string $key ): string {
	return (string) get_post_meta( $post_id, $key, true );
}

/**
 * Return a privacy-safe author nickname without falling back to real names.
 */
function paginae_manuscripti_illuminati_author_nickname( int $author_id ): string {
	if ( $author_id < 1 ) {
		return '';
	}

	if ( function_exists( 'ligatura_get_author_nickname' ) ) {
		return ligatura_get_author_nickname( $author_id );
	}

	$nickname = get_user_meta( $author_id, 'nickname', true );
	$nickname = is_scalar( $nickname ) ? trim( sanitize_text_field( (string) $nickname ) ) : '';

	return $nickname;
}

/**
 * Return a post author's privacy-safe nickname.
 */
function paginae_manuscripti_illuminati_post_author_nickname( int $post_id ): string {
	if ( function_exists( 'ligatura_get_post_author_nickname' ) ) {
		return ligatura_get_post_author_nickname( $post_id );
	}

	return paginae_manuscripti_illuminati_author_nickname( (int) get_post_field( 'post_author', $post_id ) );
}

/**
 * Return a standard post meta line with nickname-based attribution.
 */
function paginae_manuscripti_illuminati_post_meta_line( int $post_id ): string {
	$parts  = array();
	$author = paginae_manuscripti_illuminati_post_author_nickname( $post_id );

	if ( $author ) {
		$parts[] = sprintf(
			/* translators: %s: author nickname. */
			__( 'By %s', 'paginae-manuscripti-illuminati' ),
			$author
		);
	}

	$parts[] = get_the_date( '', $post_id );

	return implode( ' / ', array_filter( $parts ) );
}

/**
 * Return the visitor-facing last-updated line for a Covenant Record.
 */
function paginae_manuscripti_illuminati_covenant_news_meta_line( int $post_id ): string {
	return sprintf(
		/* translators: %s: real-world last-modified date. */
		__( 'Last updated: %s', 'paginae-manuscripti-illuminati' ),
		get_the_modified_date( '', $post_id )
	);
}

/**
 * Return the normalized Saga Date for a Journal.
 */
function paginae_manuscripti_illuminati_journal_saga_date( int $post_id ): string {
	return function_exists( 'ligatura_get_journal_saga_date' ) ? ligatura_get_journal_saga_date( $post_id ) : '';
}

/**
 * Return the visitor-facing Saga Date for a Journal.
 */
function paginae_manuscripti_illuminati_format_journal_saga_date( int $post_id ): string {
	return function_exists( 'ligatura_format_journal_saga_date' ) ? ligatura_format_journal_saga_date( $post_id ) : '';
}

/**
 * Return a Journal's canonical in-world Character Author ID.
 */
function paginae_manuscripti_illuminati_journal_character_id( int $post_id ): int {
	return function_exists( 'ligatura_get_journal_character_id' ) ? ligatura_get_journal_character_id( $post_id ) : absint( get_post_meta( $post_id, 'ligatura_diary_character', true ) );
}

/**
 * Return the visible in-world Character Author name.
 */
function paginae_manuscripti_illuminati_journal_character_name( int $post_id ): string {
	if ( function_exists( 'ligatura_get_journal_character_name' ) ) {
		return ligatura_get_journal_character_name( $post_id );
	}

	$character_id = paginae_manuscripti_illuminati_journal_character_id( $post_id );

	return $character_id > 0 ? get_the_title( $character_id ) : '';
}

/**
 * Return the Character Author's Campaign Image attachment ID.
 */
function paginae_manuscripti_illuminati_journal_character_image_id( int $post_id ): int {
	if ( function_exists( 'ligatura_get_journal_character_image_id' ) ) {
		return ligatura_get_journal_character_image_id( $post_id );
	}

	$character_id = paginae_manuscripti_illuminati_journal_character_id( $post_id );

	return $character_id > 0 ? get_post_thumbnail_id( $character_id ) : 0;
}

/**
 * Return a Journal meta line without falling back to WordPress publication date.
 */
function paginae_manuscripti_illuminati_journal_meta_line( int $post_id ): string {
	$parts = array( paginae_manuscripti_illuminati_journal_character_name( $post_id ) );
	$date  = paginae_manuscripti_illuminati_format_journal_saga_date( $post_id );

	if ( $date ) {
		$parts[] = sprintf(
			/* translators: %s: formatted Saga Date. */
			__( 'Saga Date: %s', 'paginae-manuscripti-illuminati' ),
			$date
		);
	}

	return implode( ' / ', array_filter( $parts ) );
}

/**
 * Return the latest Journal posts selected by Saga Date, newest-to-oldest.
 *
 * @return \WP_Post[]
 */
function paginae_manuscripti_illuminati_latest_journal_posts( int $count ): array {
	if ( $count < 1 ) {
		return array();
	}

	if ( function_exists( 'ligatura_get_latest_journal_posts' ) ) {
		return ligatura_get_latest_journal_posts( $count );
	}

	$query = new WP_Query(
		array(
			'post_type'           => 'ligatura_diary',
			'post_status'         => 'publish',
			'posts_per_page'      => $count,
			'ignore_sticky_posts' => true,
		)
	);

	return $query->posts;
}

/**
 * Render the shared Journal row partial for any Journal listing.
 */
function paginae_manuscripti_illuminati_render_journal_row( int $post_id ): string {
	$journal = get_post( $post_id );

	if ( ! $journal instanceof WP_Post || 'ligatura_diary' !== $journal->post_type || ! current_user_can( 'read_post', $post_id ) ) {
		return '';
	}

	global $post;

	$previous_post = $post ?? null;
	$post          = $journal;
	setup_postdata( $post );

	ob_start();
	get_template_part( 'template-parts/content', 'journal-row' );
	$output = (string) ob_get_clean();

	if ( $previous_post instanceof WP_Post ) {
		$post = $previous_post;
		setup_postdata( $post );
	} else {
		wp_reset_postdata();
	}

	return $output;
}

/**
 * Render an ordinary Post as a shared full-width Annales row.
 */
function paginae_manuscripti_illuminati_render_annal_row( int $post_id, int $heading_level = 3 ): string {
	return paginae_manuscripti_illuminati_render_chronicle_row(
		$post_id,
		'post',
		array(
			'class'         => 'manuscriptum-illuminatum-annal-row',
			'meta'          => paginae_manuscripti_illuminati_post_meta_line( $post_id ),
			'show_avatar'   => true,
			'heading_level' => $heading_level,
			'data'          => array( 'post-date' => get_post_time( 'c', true, $post_id ) ),
		)
	);
}

/**
 * Render a Covenant Record as a shared full-width news row.
 */
function paginae_manuscripti_illuminati_render_covenant_news_row( int $post_id, int $heading_level = 3 ): string {
	return paginae_manuscripti_illuminati_render_chronicle_row(
		$post_id,
		'ligatura_covenant',
		array(
			'class'         => 'manuscriptum-illuminatum-covenant-news-row',
			'meta'          => paginae_manuscripti_illuminati_covenant_news_meta_line( $post_id ),
			'heading_level' => $heading_level,
			'data'          => array( 'modified' => get_post_modified_time( 'c', true, $post_id ) ),
		)
	);
}

/**
 * Render a Covenant Record for its Saga Date-ordered directory.
 */
function paginae_manuscripti_illuminati_render_covenant_record_row( int $post_id, int $heading_level = 3 ): string {
	$date    = function_exists( 'ligatura_format_covenant_saga_date' ) ? ligatura_format_covenant_saga_date( $post_id ) : '';
	$summary = (string) get_post_meta( $post_id, 'ligatura_public_summary', true );

	return paginae_manuscripti_illuminati_render_chronicle_row(
		$post_id,
		'ligatura_covenant',
		array(
			'class'         => 'manuscriptum-illuminatum-covenant-record-row',
			'meta'          => $date ? sprintf(
				/* translators: %s: formatted Saga Date. */
				__( 'Saga Date: %s', 'paginae-manuscripti-illuminati' ),
				$date
			) : '',
			'excerpt'       => $summary,
			'heading_level' => $heading_level,
			'data'          => array( 'saga-date' => function_exists( 'ligatura_get_covenant_saga_date' ) ? ligatura_get_covenant_saga_date( $post_id ) : '' ),
		)
	);
}

/**
 * Render the shared full-width chronicle row partial.
 *
 * @param array<string,mixed> $args Row presentation data.
 */
function paginae_manuscripti_illuminati_render_chronicle_row( int $post_id, string $post_type, array $args ): string {
	$entry = get_post( $post_id );

	if ( ! $entry instanceof WP_Post || $post_type !== $entry->post_type || ! current_user_can( 'read_post', $post_id ) ) {
		return '';
	}

	$args['post_id'] = $post_id;

	ob_start();
	get_template_part( 'template-parts/content', 'chronicle-row', $args );

	return (string) ob_get_clean();
}

/**
 * Return a genuine author avatar reported by WordPress/avatar plugins.
 *
 * @return array<string,mixed>
 */
function paginae_manuscripti_illuminati_author_avatar_data( int $author_id, int $size = 72 ): array {
	$data = get_avatar_data(
		$author_id,
		array(
			'size'          => $size,
			'default'       => '404',
			'force_default' => false,
		)
	);

	if ( empty( $data['found_avatar'] ) || empty( $data['url'] ) ) {
		return array();
	}

	$host = wp_parse_url( (string) $data['url'], PHP_URL_HOST );

	if ( is_string( $host ) && str_contains( $host, 'gravatar.com' ) ) {
		return array();
	}

	return $data;
}

/**
 * Render a featured image or fallback illustration.
 *
 * @param int    $post_id       Post ID.
 * @param string $size          Image size.
 * @param string $fallback_file Fallback asset file.
 * @param string $class_name    Image class.
 * @param bool   $show_fallback Whether to render the fallback illustration.
 */
function paginae_manuscripti_illuminati_image( int $post_id, string $size = 'large', string $fallback_file = 'default-wiki.svg', string $class_name = 'manuscriptum-illuminatum-image', bool $show_fallback = true ): void {
	if ( has_post_thumbnail( $post_id ) ) {
		$image_id  = get_post_thumbnail_id( $post_id );
		$image_alt = trim( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) );

		echo get_the_post_thumbnail(
			$post_id,
			$size,
			array(
				'class'   => $class_name,
				'loading' => 'lazy',
				'alt'     => $image_alt ?: get_the_title( $post_id ),
			)
		);
		return;
	}

	if ( ! $show_fallback ) {
		return;
	}

	printf(
		'<img class="%1$s" src="%2$s" alt="" loading="lazy" />',
		esc_attr( $class_name ),
		esc_url( get_template_directory_uri() . '/assets/images/' . $fallback_file )
	);
}

/**
 * Print taxonomy terms for a post.
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy name.
 * @param string $separator Escaped separator markup.
 */
function paginae_manuscripti_illuminati_term_line( int $post_id, string $taxonomy, string $separator = '<span aria-hidden="true"> / </span>' ): string {
	$terms = get_the_terms( $post_id, $taxonomy );

	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}

	usort(
		$terms,
		static function ( WP_Term $left, WP_Term $right ): int {
			return strnatcasecmp( $left->name, $right->name );
		}
	);

	$links = array();

	foreach ( $terms as $term ) {
		$links[] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( get_term_link( $term ) ),
			esc_html( $term->name )
		);
	}

	return implode( $separator, $links );
}

/**
 * Return taxonomy links scoped to Covenant Records rather than Speculum.
 */
function paginae_manuscripti_illuminati_covenant_term_line( int $post_id, string $taxonomy ): string {
	$terms = get_the_terms( $post_id, $taxonomy );

	if ( ! is_array( $terms ) ) {
		return '';
	}

	usort( $terms, static fn( WP_Term $left, WP_Term $right ): int => strnatcasecmp( $left->name, $right->name ) );
	$namespace = 'ligatura_entry_type' === $taxonomy ? 'type' : 'topics';
	$links     = array();

	foreach ( $terms as $term ) {
		$links[] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( home_url( user_trailingslashit( 'covenant-records/' . $namespace . '/' . $term->slug ) ) ),
			esc_html( $term->name )
		);
	}

	return implode( '<span aria-hidden="true">, </span>', $links );
}

/**
 * Return readable related entities for one stored relationship group.
 *
 * @return WP_Post[]
 */
function paginae_manuscripti_illuminati_covenant_related_posts( int $post_id, string $meta_key, string $post_type ): array {
	$value   = (string) get_post_meta( $post_id, $meta_key, true );
	$related = array();

	foreach ( preg_split( '/[,\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY ) ?: array() as $raw_id ) {
		$related_id = absint( $raw_id );
		$entity     = $related_id ? get_post( $related_id ) : null;

		if ( $entity instanceof WP_Post && $post_type === $entity->post_type && current_user_can( 'read_post', $entity->ID ) ) {
			$related[ $entity->ID ] = $entity;
		}
	}

	uasort(
		$related,
		static function ( WP_Post $left, WP_Post $right ): int {
			$left_title  = get_the_title( $left );
			$right_title = get_the_title( $right );
			$left_key    = (string) preg_replace( '/^the\s+/iu', '', trim( wp_strip_all_tags( $left_title ) ) );
			$right_key   = (string) preg_replace( '/^the\s+/iu', '', trim( wp_strip_all_tags( $right_title ) ) );
			$result      = strnatcasecmp( $left_key, $right_key );

			return 0 !== $result ? $result : strnatcasecmp( $left_title, $right_title );
		}
	);

	return array_values( $related );
}

/**
 * Render one non-empty Covenant Record relationship group.
 */
function paginae_manuscripti_illuminati_covenant_related_group( int $post_id, string $meta_key, string $post_type, string $label, string $group ): void {
	$related = paginae_manuscripti_illuminati_covenant_related_posts( $post_id, $meta_key, $post_type );

	if ( ! $related ) {
		return;
	}

	echo '<section class="manuscriptum-illuminatum-section manuscriptum-illuminatum-related-section" data-related-group="' . esc_attr( $group ) . '">';
	echo '<h2>' . esc_html( $label ) . '</h2>';
	echo '<ul class="manuscriptum-illuminatum-related-list">';

	foreach ( $related as $entity ) {
		echo '<li><a href="' . esc_url( get_permalink( $entity ) ) . '">' . esc_html( get_the_title( $entity ) ) . '</a></li>';
	}

	echo '</ul>';
	echo '</section>';
}

/**
 * Render the shared text-only Speculum teaser with context-specific metadata.
 *
 * @param int                 $post_id Speculum entry ID.
 * @param array<string,mixed> $options Teaser display options.
 */
function paginae_manuscripti_illuminati_render_speculum_teaser( int $post_id, array $options = array() ): string {
	if ( 'ligatura_wiki' !== get_post_type( $post_id ) || ! current_user_can( 'read_post', $post_id ) ) {
		return '';
	}

	$options = wp_parse_args(
		$options,
		array(
			'show_entry_type' => false,
			'show_modified'   => false,
		)
	);

	ob_start();
	get_template_part(
		'template-parts/content',
		'wiki-teaser',
		array(
			'post_id'         => $post_id,
			'show_entry_type' => (bool) $options['show_entry_type'],
			'show_modified'   => (bool) $options['show_modified'],
		)
	);

	return (string) ob_get_clean();
}

/**
 * Render a compact Persona teaser for the grouped Personae directory.
 */
function paginae_manuscripti_illuminati_render_persona_teaser( int $post_id ): string {
	if ( 'ligatura_character' !== get_post_type( $post_id ) || ! current_user_can( 'read_post', $post_id ) ) {
		return '';
	}

	ob_start();
	get_template_part(
		'template-parts/content',
		'persona-teaser',
		array( 'post_id' => $post_id )
	);

	return (string) ob_get_clean();
}

/**
 * Print a definition list from non-empty meta fields.
 *
 * @param int                  $post_id Post ID.
 * @param array<string,string> $fields  Label => meta key.
 */
function paginae_manuscripti_illuminati_meta_list( int $post_id, array $fields ): void {
	$items = array();

	foreach ( $fields as $label => $key ) {
		$value = paginae_manuscripti_illuminati_meta( $post_id, $key );

		if ( '' !== trim( $value ) ) {
			$items[] = array( $label, $value );
		}
	}

	if ( empty( $items ) ) {
		return;
	}

	echo '<dl class="manuscriptum-illuminatum-meta-list">';

	foreach ( $items as $item ) {
		echo '<div>';
		echo '<dt>' . esc_html( $item[0] ) . '</dt>';
		echo '<dd>' . esc_html( $item[1] ) . '</dd>';
		echo '</div>';
	}

	echo '</dl>';
}

/**
 * Print related entries if the core plugin helper is available.
 *
 * @param int $post_id Post ID.
 */
function paginae_manuscripti_illuminati_related_entries( int $post_id ): void {
	if ( ! function_exists( 'ligatura_get_related_entries' ) ) {
		return;
	}

	$entries = ligatura_get_related_entries( $post_id );

	if ( empty( $entries ) ) {
		return;
	}

	echo '<section class="manuscriptum-illuminatum-section manuscriptum-illuminatum-related-section">';
	echo '<h2>' . esc_html__( 'Related Entries', 'paginae-manuscripti-illuminati' ) . '</h2>';
	echo '<ul class="manuscriptum-illuminatum-related-list">';

	foreach ( $entries as $entry_id ) {
		if ( ! current_user_can( 'read_post', $entry_id ) ) {
			continue;
		}

		echo '<li><a href="' . esc_url( get_permalink( $entry_id ) ) . '">' . esc_html( get_the_title( $entry_id ) ) . '</a></li>';
	}

	echo '</ul>';
	echo '</section>';
}

/**
 * Render a card template for the current post type.
 */
function paginae_manuscripti_illuminati_get_card_template(): void {
	$post_type = get_post_type();

	if ( 'ligatura_character' === $post_type ) {
		get_template_part( 'template-parts/content', 'character-card' );
		return;
	}

	if ( 'ligatura_wiki' === $post_type ) {
		get_template_part( 'template-parts/content', 'wiki-card' );
		return;
	}

	if ( 'ligatura_diary' === $post_type ) {
		get_template_part( 'template-parts/content', 'diary-card' );
		return;
	}

	if ( 'ligatura_covenant' === $post_type ) {
		get_template_part( 'template-parts/content', 'covenant-card' );
		return;
	}

	get_template_part( 'template-parts/content', 'post-card' );
}

/**
 * Build a front-page query.
 *
 * @param string $post_type Post type.
 * @param int    $count     Number of posts.
 */
function paginae_manuscripti_illuminati_recent_query( string $post_type, int $count = 3 ): WP_Query {
	if ( $count < 1 ) {
		return new WP_Query(
			array(
				'post_type'      => $post_type,
				'post__in'       => array( 0 ),
				'posts_per_page' => 0,
			)
		);
	}

	return new WP_Query(
		array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => $count,
			'ignore_sticky_posts' => true,
			'orderby'             => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
		)
	);
}

/**
 * Keep the native Annales query on real-world publication chronology.
 */
function paginae_manuscripti_illuminati_order_annales_query( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_home() ) {
		return;
	}

	$query->set( 'post_type', 'post' );
	$query->set( 'post_status', 'publish' );
	$query->set( 'ignore_sticky_posts', true );
	$query->set(
		'orderby',
		array(
			'date' => 'DESC',
			'ID'   => 'DESC',
		)
	);
}
add_action( 'pre_get_posts', 'paginae_manuscripti_illuminati_order_annales_query' );

/**
 * Build a front-page query ordered by last modification time.
 *
 * @param string $post_type Post type.
 * @param int    $count     Number of posts.
 */
function paginae_manuscripti_illuminati_recently_modified_query( string $post_type, int $count = 3 ): WP_Query {
	if ( $count < 1 ) {
		return paginae_manuscripti_illuminati_recent_query( $post_type, 0 );
	}

	return new WP_Query(
		array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => $count,
			'ignore_sticky_posts' => true,
			'orderby'             => array(
				'modified' => 'DESC',
				'ID'       => 'DESC',
			),
		)
	);
}

/**
 * Add an automatic table of contents to wiki entries.
 *
 * @param string $content Post content.
 */
function paginae_manuscripti_illuminati_add_wiki_toc( string $content ): string {
	if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $content;
	}

	if ( ! is_singular( 'ligatura_wiki' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	if ( ! get_theme_mod( 'paginae_manuscripti_illuminati_enable_wiki_toc', true ) ) {
		return $content;
	}

	if ( ! class_exists( 'DOMDocument' ) || '' === trim( $content ) ) {
		return $content;
	}

	$depth    = min( 4, max( 2, absint( get_theme_mod( 'paginae_manuscripti_illuminati_wiki_toc_depth', 4 ) ) ) );
	$previous = libxml_use_internal_errors( true );
	$document = new DOMDocument();
	$loaded   = $document->loadHTML(
		'<?xml encoding="utf-8" ?><div id="manuscriptum-illuminatum-toc-root">' . $content . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( ! $loaded ) {
		return $content;
	}

	$xpath    = new DOMXPath( $document );
	$headings = $xpath->query( '//*[@id="manuscriptum-illuminatum-toc-root"]//*[self::h2 or self::h3 or self::h4]' );

	if ( ! $headings ) {
		return $content;
	}

	$items    = array();
	$used_ids = array();
	$index    = 1;

	foreach ( $headings as $heading ) {
		if ( ! $heading instanceof DOMElement ) {
			continue;
		}

		$level = absint( substr( $heading->nodeName, 1 ) );

		if ( $level < 2 || $level > $depth ) {
			continue;
		}

		$text = preg_replace( '/\s+/', ' ', $heading->textContent );
		$text = is_string( $text ) ? trim( $text ) : '';

		if ( '' === $text ) {
			continue;
		}

		$id = $heading->getAttribute( 'id' );
		$id = '' !== $id ? sanitize_title( $id ) : sanitize_title( $text );
		$id = $id ?: 'wiki-section-' . $index;
		$id = paginae_manuscripti_illuminati_unique_toc_id( $id, $used_ids );
		$heading->setAttribute( 'id', $id );

		$items[] = array(
			'id'    => $id,
			'level' => $level,
			'text'  => $text,
		);
		$index++;
	}

	if ( count( $items ) < 2 ) {
		return $content;
	}

	$root = $document->getElementById( 'manuscriptum-illuminatum-toc-root' );

	if ( ! $root ) {
		$roots = $xpath->query( '//*[@id="manuscriptum-illuminatum-toc-root"]' );
		$root  = $roots && $roots->length ? $roots->item( 0 ) : null;
	}

	if ( ! $root instanceof DOMElement ) {
		return $content;
	}

	$updated_content = '';

	foreach ( $root->childNodes as $child ) {
		$updated_content .= $document->saveHTML( $child );
	}

	return paginae_manuscripti_illuminati_render_wiki_toc( $items ) . $updated_content;
}
add_filter( 'the_content', 'paginae_manuscripti_illuminati_add_wiki_toc', 12 );

/**
 * Make heading IDs unique within a TOC.
 *
 * @param string   $base_id  Desired ID.
 * @param string[] $used_ids Existing IDs.
 */
function paginae_manuscripti_illuminati_unique_toc_id( string $base_id, array &$used_ids ): string {
	$id      = $base_id;
	$counter = 2;

	while ( in_array( $id, $used_ids, true ) ) {
		$id = $base_id . '-' . $counter;
		$counter++;
	}

	$used_ids[] = $id;

	return $id;
}

/**
 * Render the wiki table of contents.
 *
 * @param array<int,array{id:string,level:int,text:string}> $items TOC items.
 */
function paginae_manuscripti_illuminati_render_wiki_toc( array $items ): string {
	ob_start();
	?>
	<nav class="manuscriptum-illuminatum-wiki-toc" aria-label="<?php esc_attr_e( 'Table of contents', 'paginae-manuscripti-illuminati' ); ?>">
		<h2><?php esc_html_e( 'Contents', 'paginae-manuscripti-illuminati' ); ?></h2>
		<ol>
			<?php foreach ( $items as $item ) : ?>
				<li class="manuscriptum-illuminatum-wiki-toc__item manuscriptum-illuminatum-wiki-toc__item--level-<?php echo esc_attr( (string) $item['level'] ); ?>">
					<a href="#<?php echo esc_attr( $item['id'] ); ?>"><?php echo esc_html( $item['text'] ); ?></a>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>
	<?php

	return (string) ob_get_clean();
}
