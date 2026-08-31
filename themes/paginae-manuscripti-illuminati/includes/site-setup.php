<?php
/**
 * Idempotent default Page and primary-menu setup.
 *
 * @package PaginaeManuscriptiIlluminati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the Pages expected by the coordinated theme/plugin product.
 *
 * @return array<string,array<string,mixed>>
 */
function paginae_manuscripti_illuminati_default_page_definitions(): array {
	return array(
		'home'             => array(
			'title' => __( 'Home', 'paginae-manuscripti-illuminati' ),
			'slug'  => 'home',
		),
		'annales'          => array(
			'title' => __( 'Annales', 'paginae-manuscripti-illuminati' ),
			'slug'  => 'news',
		),
		'speculum'         => array(
			'title' => __( 'Speculum', 'paginae-manuscripti-illuminati' ),
			'slug'  => 'wiki',
		),
		'personae'         => array(
			'title' => __( 'Personae', 'paginae-manuscripti-illuminati' ),
			'slug'  => 'characters',
		),
		'commentarii'      => array(
			'title' => __( 'Commentarii', 'paginae-manuscripti-illuminati' ),
			'slug'  => 'journals',
		),
		'covenant-records' => array(
			'title' => __( 'Covenant Records', 'paginae-manuscripti-illuminati' ),
			'slug'  => 'covenant-records',
		),
	);
}

/**
 * Return a non-trashed Page marked as one of the default Pages.
 */
function paginae_manuscripti_illuminati_marked_default_page( string $key ): ?WP_Post {
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future', 'trash' ),
			'posts_per_page' => 2,
			'meta_key'       => PAGINAE_MANUSCRIPTI_ILLUMINATI_DEFAULT_PAGE_META,
			'meta_value'     => $key,
			'no_found_rows'  => true,
		)
	);

	return 1 === count( $pages ) && $pages[0] instanceof WP_Post ? $pages[0] : null;
}

/**
 * Find one unambiguous, root-level Page by an expected title.
 *
 * @param string[] $titles Accepted titles.
 * @return WP_Post|WP_Error|null
 */
function paginae_manuscripti_illuminati_default_page_by_title( array $titles ): WP_Post|WP_Error|null {
	$matches = array();

	foreach ( array_unique( array_filter( $titles ) ) as $title ) {
		$query = new WP_Query(
			array(
				'post_type'              => 'page',
				'post_status'            => array( 'publish', 'private', 'draft', 'pending', 'future', 'trash' ),
				'posts_per_page'         => 2,
				'post_parent'            => 0,
				'title'                  => $title,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $query->posts as $post_id ) {
			$matches[ (int) $post_id ] = (int) $post_id;
		}
	}

	if ( 1 < count( $matches ) ) {
		return new WP_Error( 'paginae_manuscripti_illuminati_default_page_ambiguous', __( 'More than one existing Page matches the expected title.', 'paginae-manuscripti-illuminati' ) );
	}

	if ( 1 === count( $matches ) ) {
		$page = get_post( reset( $matches ) );

		return $page instanceof WP_Post ? $page : null;
	}

	return null;
}

/**
 * Find a safe existing Page candidate for one default section.
 *
 * @param string              $key        Definition key.
 * @param array<string,mixed> $definition Page definition.
 * @return WP_Post|WP_Error|null
 */
function paginae_manuscripti_illuminati_find_default_page( string $key, array $definition ): WP_Post|WP_Error|null {
	if ( 'home' === $key && 'page' === get_option( 'show_on_front' ) ) {
		$front_page = get_post( (int) get_option( 'page_on_front' ) );

		if ( $front_page instanceof WP_Post && 'page' === $front_page->post_type && 'trash' !== $front_page->post_status ) {
			return $front_page;
		}
	}

	if ( 'annales' === $key ) {
		$annales = paginae_manuscripti_illuminati_annales_page();

		if ( $annales instanceof WP_Post ) {
			return $annales;
		}
	}

	$marked = paginae_manuscripti_illuminati_marked_default_page( $key );

	if ( $marked instanceof WP_Post ) {
		return $marked;
	}

	$canonical = get_page_by_path( (string) $definition['slug'], OBJECT, 'page' );

	if ( $canonical instanceof WP_Post && 0 === (int) $canonical->post_parent && 'trash' !== $canonical->post_status ) {
		return $canonical;
	}

	return paginae_manuscripti_illuminati_default_page_by_title( array( (string) $definition['title'] ) );
}

/**
 * Detect attachment collisions that can make WordPress suffix a Page slug.
 */
function paginae_manuscripti_illuminati_default_page_slug_conflict( string $slug, int $expected_page_id = 0 ): ?WP_Post {
	$posts = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => array( 'page', 'attachment' ),
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future', 'inherit' ),
			'posts_per_page' => 10,
			'no_found_rows'  => true,
		)
	);

	foreach ( $posts as $post ) {
		if ( $post instanceof WP_Post && $post->ID !== $expected_page_id && ( 'attachment' === $post->post_type || 0 === (int) $post->post_parent ) ) {
			return $post;
		}
	}

	return null;
}

/**
 * Create or adopt one required Page without changing authored body content.
 *
 * @param string              $key        Definition key.
 * @param array<string,mixed> $definition Page definition.
 * @return WP_Post|WP_Error
 */
function paginae_manuscripti_illuminati_ensure_default_page( string $key, array $definition ): WP_Post|WP_Error {
	$page = paginae_manuscripti_illuminati_find_default_page( $key, $definition );

	if ( is_wp_error( $page ) ) {
		return $page;
	}

	$slug          = (string) $definition['slug'];
	$requires_slug = 'home' !== $key || ! ( $page instanceof WP_Post && 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $page->ID );

	if ( ! $page instanceof WP_Post ) {
		$conflict = paginae_manuscripti_illuminati_default_page_slug_conflict( $slug );

		if ( $conflict instanceof WP_Post ) {
			return new WP_Error(
				'paginae_manuscripti_illuminati_default_page_slug_collision',
				sprintf(
					/* translators: 1: Page title, 2: required slug. */
					__( '%1$s could not be created because the slug "%2$s" is already occupied. Resolve the conflict and run setup again.', 'paginae-manuscripti-illuminati' ),
					(string) $definition['title'],
					$slug
				)
			);
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => (string) $definition['title'],
				'post_name'    => $slug,
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
		return new WP_Error( 'paginae_manuscripti_illuminati_default_page_missing', __( 'The required Page could not be found or created.', 'paginae-manuscripti-illuminati' ) );
	}

	if ( 'trash' === $page->post_status ) {
		$previous_status = (string) get_post_meta( $page->ID, '_wp_trash_meta_status', true );
		$restored = wp_untrash_post( $page->ID );

		if ( ! $restored ) {
			return new WP_Error(
				'paginae_manuscripti_illuminati_default_page_restore_failed',
				sprintf(
					/* translators: %s: Page title. */
					__( 'The trashed %s Page could not be restored. Its content was left in Trash.', 'paginae-manuscripti-illuminati' ),
					(string) $definition['title']
				)
			);
		}

		$page = get_post( $page->ID );

		if ( 'publish' === $previous_status && $page instanceof WP_Post && 'publish' !== $page->post_status ) {
			$published = wp_update_post(
				array(
					'ID'          => $page->ID,
					'post_status' => 'publish',
				),
				true
			);

			if ( is_wp_error( $published ) ) {
				return $published;
			}

			$page = get_post( $page->ID );
		}
	}

	if ( 'publish' !== $page->post_status ) {
		return new WP_Error(
			'paginae_manuscripti_illuminati_default_page_not_published',
			sprintf(
				/* translators: %s: Page title. */
				__( 'The existing %s Page is not published. Its status was left unchanged; publish it and run setup again.', 'paginae-manuscripti-illuminati' ),
				(string) $definition['title']
			)
		);
	}

	if ( $requires_slug && ( $slug !== $page->post_name || 0 !== (int) $page->post_parent ) ) {
		$conflict = paginae_manuscripti_illuminati_default_page_slug_conflict( $slug, $page->ID );

		if ( $conflict instanceof WP_Post ) {
			return new WP_Error(
				'paginae_manuscripti_illuminati_default_page_slug_collision',
				sprintf(
					/* translators: 1: Page title, 2: required slug. */
					__( '%1$s could not use the required slug "%2$s" because it is already occupied. No existing content was changed.', 'paginae-manuscripti-illuminati' ),
					(string) $definition['title'],
					$slug
				)
			);
		}

		$updated = wp_update_post(
			array(
				'ID'          => $page->ID,
				'post_name'   => $slug,
				'post_parent' => 0,
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$page = get_post( $page->ID );
	}

	if ( ! $page instanceof WP_Post || ( $requires_slug && ( $slug !== $page->post_name || 0 !== (int) $page->post_parent ) ) ) {
		return new WP_Error(
			'paginae_manuscripti_illuminati_default_page_slug_failed',
			sprintf(
				/* translators: %s: required slug. */
				__( 'WordPress could not assign the required "%s" Page slug. No alternate public URL was accepted.', 'paginae-manuscripti-illuminati' ),
				$slug
			)
		);
	}

	update_post_meta( $page->ID, PAGINAE_MANUSCRIPTI_ILLUMINATI_DEFAULT_PAGE_META, $key );

	if ( 'annales' === $key ) {
		update_post_meta( $page->ID, PAGINAE_MANUSCRIPTI_ILLUMINATI_ANNALES_MARKER_META, '1' );
	}

	return $page;
}

/**
 * Create and assign the default menu only before this location has been initialized.
 *
 * @param array<string,WP_Post> $pages Provisioned Pages.
 * @return true|WP_Error
 */
function paginae_manuscripti_illuminati_ensure_default_primary_menu( array $pages ): bool|WP_Error {
	$locations = get_nav_menu_locations();

	if ( absint( $locations['primary'] ?? 0 ) ) {
		update_option( 'paginae_manuscripti_illuminati_primary_menu_initialized', 1, false );

		return true;
	}

	if ( get_option( 'paginae_manuscripti_illuminati_primary_menu_initialized' ) ) {
		return true;
	}

	$menu_id   = absint( get_option( 'paginae_manuscripti_illuminati_default_menu_id' ) );
	$menu      = $menu_id ? wp_get_nav_menu_object( $menu_id ) : false;
	$menu_name = __( 'Manuscriptum Illuminatum Primary Menu', 'paginae-manuscripti-illuminati' );

	if ( ! $menu ) {
		$existing_named_menu = wp_get_nav_menu_object( $menu_name );

		if ( $existing_named_menu ) {
			return new WP_Error(
				'paginae_manuscripti_illuminati_default_menu_name_collision',
				__( 'A menu named “Manuscriptum Illuminatum Primary Menu” already exists but was not created by setup. Assign it manually or rename it before retrying setup.', 'paginae-manuscripti-illuminati' )
			);
		}

		$menu_id = wp_create_nav_menu( $menu_name );

		if ( is_wp_error( $menu_id ) ) {
			return $menu_id;
		}

		update_option( 'paginae_manuscripti_illuminati_default_menu_id', (int) $menu_id, false );
	}

	$items        = wp_get_nav_menu_items( $menu_id );
	$object_ids   = array();
	$ordered_keys = array( 'home', 'annales', 'speculum', 'personae', 'commentarii', 'covenant-records' );
	$definitions  = paginae_manuscripti_illuminati_default_page_definitions();

	foreach ( is_array( $items ) ? $items : array() as $item ) {
		if ( 'page' === $item->object ) {
			$object_ids[] = (int) $item->object_id;
		}
	}

	foreach ( $ordered_keys as $key ) {
		if ( empty( $pages[ $key ] ) || in_array( $pages[ $key ]->ID, $object_ids, true ) ) {
			continue;
		}

		$item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-object-id' => $pages[ $key ]->ID,
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-title'     => (string) $definitions[ $key ]['title'],
			)
		);

		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}
	}

	$locations['primary'] = (int) $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
	update_option( 'paginae_manuscripti_illuminati_primary_menu_initialized', 1, false );

	return true;
}

/**
 * Provision default Pages, initial reading settings, and the initial menu.
 *
 * @return array<string,mixed>
 */
function paginae_manuscripti_illuminati_run_site_setup(): array {
	$definitions          = paginae_manuscripti_illuminati_default_page_definitions();
	$pages                = array();
	$errors               = array();
	$reading_unconfigured = 'posts' === get_option( 'show_on_front' )
		&& 0 === (int) get_option( 'page_on_front' )
		&& 0 === (int) get_option( 'page_for_posts' );

	foreach ( $definitions as $key => $definition ) {
		$page = paginae_manuscripti_illuminati_ensure_default_page( $key, $definition );

		if ( is_wp_error( $page ) ) {
			$errors[ $key ] = $page->get_error_message();
			continue;
		}

		$pages[ $key ] = $page;
	}

	if ( $reading_unconfigured && isset( $pages['home'], $pages['annales'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $pages['home']->ID );
		update_option( 'page_for_posts', $pages['annales']->ID );
	}

	if ( count( $pages ) === count( $definitions ) ) {
		$menu_result = paginae_manuscripti_illuminati_ensure_default_primary_menu( $pages );

		if ( is_wp_error( $menu_result ) ) {
			$errors['primary-menu'] = $menu_result->get_error_message();
		}
	}

	if ( empty( $errors ) ) {
		update_option( PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_STRUCTURE_OPTION, PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_STRUCTURE_VERSION, false );
		delete_option( PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_SETUP_ERROR_OPTION );
	} else {
		delete_option( PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_STRUCTURE_OPTION );
		update_option( PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_SETUP_ERROR_OPTION, $errors, false );
	}

	paginae_manuscripti_illuminati_register_news_permastruct();
	flush_rewrite_rules( false );

	return array(
		'pages'  => $pages,
		'errors' => $errors,
	);
}

/**
 * Whether a required Page has disappeared since the versioned setup completed.
 */
function paginae_manuscripti_illuminati_site_setup_needs_repair(): bool {
	foreach ( paginae_manuscripti_illuminati_default_page_definitions() as $key => $definition ) {
		$page = paginae_manuscripti_illuminati_marked_default_page( $key );

		if ( ! $page instanceof WP_Post ) {
			if ( 'home' === $key && 'page' === get_option( 'show_on_front' ) ) {
				$page = get_post( (int) get_option( 'page_on_front' ) );
			} else {
				$page = get_page_by_path( (string) $definition['slug'], OBJECT, 'page' );
			}
		}

		if ( ! $page instanceof WP_Post || 'publish' !== $page->post_status ) {
			return true;
		}
	}

	return false;
}

/**
 * Run upgrades and missing-Page repair only in an administrative context.
 */
function paginae_manuscripti_illuminati_maybe_run_site_setup(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$errors = get_option( PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_SETUP_ERROR_OPTION, array() );

	if ( PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_STRUCTURE_VERSION === get_option( PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_STRUCTURE_OPTION ) && ! paginae_manuscripti_illuminati_site_setup_needs_repair() && empty( $errors ) ) {
		return;
	}

	paginae_manuscripti_illuminati_run_site_setup();
}
add_action( 'admin_init', 'paginae_manuscripti_illuminati_maybe_run_site_setup' );
add_action( 'after_switch_theme', 'paginae_manuscripti_illuminati_run_site_setup' );

/**
 * Show actionable setup conflicts to administrators.
 */
function paginae_manuscripti_illuminati_site_setup_notice(): void {
	$errors = get_option( PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_SETUP_ERROR_OPTION, array() );

	if ( ! current_user_can( 'manage_options' ) || ! is_array( $errors ) || empty( $errors ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Manuscriptum Illuminatum setup needs attention.', 'paginae-manuscripti-illuminati' ) . '</strong></p><ul>';

	foreach ( $errors as $section => $message ) {
		echo '<li><strong>' . esc_html( (string) $section ) . ':</strong> ' . esc_html( (string) $message ) . '</li>';
	}

	echo '</ul></div>';
}
add_action( 'admin_notices', 'paginae_manuscripti_illuminati_site_setup_notice' );

/**
 * Return setup status for authenticated administration and regression checks.
 */
function paginae_manuscripti_illuminati_site_setup_status(): array {
	$pages = array();

	foreach ( paginae_manuscripti_illuminati_default_page_definitions() as $key => $definition ) {
		$page          = paginae_manuscripti_illuminati_find_default_page( $key, $definition );
		$pages[ $key ] = $page instanceof WP_Post
			? array(
				'id'     => $page->ID,
				'title'  => $page->post_title,
				'slug'   => $page->post_name,
				'status' => $page->post_status,
			)
			: null;
	}

	$locations = get_nav_menu_locations();
	$errors    = get_option( PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_SETUP_ERROR_OPTION, array() );

	return array(
		'ready'          => PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_STRUCTURE_VERSION === get_option( PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_STRUCTURE_OPTION ) && ! paginae_manuscripti_illuminati_site_setup_needs_repair() && empty( $errors ),
		'version'        => (string) get_option( PAGINAE_MANUSCRIPTI_ILLUMINATI_SITE_STRUCTURE_OPTION, '' ),
		'pages'          => $pages,
		'errors'         => $errors,
		'primaryMenuId'  => absint( $locations['primary'] ?? 0 ),
		'showOnFront'    => (string) get_option( 'show_on_front' ),
		'pageOnFront'    => (int) get_option( 'page_on_front' ),
		'pageForPosts'   => (int) get_option( 'page_for_posts' ),
	);
}

/**
 * Register capability-protected setup status and retry endpoints.
 */
function paginae_manuscripti_illuminati_register_site_setup_rest_routes(): void {
	$permission = static fn(): bool => current_user_can( 'manage_options' );

	register_rest_route(
		'paginae-manuscripti-illuminati/v1',
		'/site-setup/status',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => $permission,
			'callback'            => static fn() => rest_ensure_response( paginae_manuscripti_illuminati_site_setup_status() ),
		)
	);

	register_rest_route(
		'paginae-manuscripti-illuminati/v1',
		'/site-setup/run',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => $permission,
			'callback'            => static function () {
				paginae_manuscripti_illuminati_run_site_setup();

				return rest_ensure_response( paginae_manuscripti_illuminati_site_setup_status() );
			},
		)
	);
}
add_action( 'rest_api_init', 'paginae_manuscripti_illuminati_register_site_setup_rest_routes' );
