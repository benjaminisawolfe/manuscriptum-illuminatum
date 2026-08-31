<?php
/**
 * Theme customization settings.
 *
 * @package PaginaeManuscriptiIlluminati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Customizer controls.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 */
function paginae_manuscripti_illuminati_customize_register( WP_Customize_Manager $wp_customize ): void {
	$wp_customize->add_panel(
		'paginae_manuscripti_illuminati_panel',
		array(
			'title'       => __( 'Paginae Manuscripti Illuminati', 'paginae-manuscripti-illuminati' ),
			'description' => __( 'Presentation settings for the covenant chronicle.', 'paginae-manuscripti-illuminati' ),
			'priority'    => 30,
		)
	);

	$wp_customize->add_section(
		'paginae_manuscripti_illuminati_layout',
		array(
			'title' => __( 'Layout', 'paginae-manuscripti-illuminati' ),
			'panel' => 'paginae_manuscripti_illuminati_panel',
		)
	);

	$wp_customize->add_setting(
		'paginae_manuscripti_illuminati_layout_mode',
		array(
			'default'           => 'right_sidebar',
			'sanitize_callback' => 'paginae_manuscripti_illuminati_sanitize_layout_mode',
		)
	);

	$wp_customize->add_control(
		'paginae_manuscripti_illuminati_layout_mode',
		array(
			'label'   => __( 'Default Sidebar Layout', 'paginae-manuscripti-illuminati' ),
			'section' => 'paginae_manuscripti_illuminati_layout',
			'type'    => 'select',
			'choices' => array(
				'no_sidebar'    => __( 'No sidebar', 'paginae-manuscripti-illuminati' ),
				'left_sidebar'  => __( 'Left sidebar only', 'paginae-manuscripti-illuminati' ),
				'right_sidebar' => __( 'Right sidebar only', 'paginae-manuscripti-illuminati' ),
				'both_sidebars' => __( 'Both sidebars', 'paginae-manuscripti-illuminati' ),
			),
		)
	);

	$wp_customize->add_setting(
		'paginae_manuscripti_illuminati_content_width',
		array(
			'default'           => 1120,
			'sanitize_callback' => 'absint',
		)
	);

	$wp_customize->add_control(
		'paginae_manuscripti_illuminati_content_width',
		array(
			'label'       => __( 'Maximum Content Width', 'paginae-manuscripti-illuminati' ),
			'description' => __( 'Measured in pixels. Recommended range: 960 to 1320.', 'paginae-manuscripti-illuminati' ),
			'section'     => 'paginae_manuscripti_illuminati_layout',
			'type'        => 'number',
			'input_attrs' => array(
				'min'  => 880,
				'max'  => 1440,
				'step' => 20,
			),
		)
	);

	$wp_customize->add_setting(
		'paginae_manuscripti_illuminati_sticky_header',
		array(
			'default'           => true,
			'sanitize_callback' => 'paginae_manuscripti_illuminati_sanitize_checkbox',
		)
	);

	$wp_customize->add_control(
		'paginae_manuscripti_illuminati_sticky_header',
		array(
			'label'   => __( 'Use sticky header', 'paginae-manuscripti-illuminati' ),
			'section' => 'paginae_manuscripti_illuminati_layout',
			'type'    => 'checkbox',
		)
	);

	$wp_customize->add_section(
		'paginae_manuscripti_illuminati_identity',
		array(
			'title' => __( 'Header Identity', 'paginae-manuscripti-illuminati' ),
			'panel' => 'paginae_manuscripti_illuminati_panel',
		)
	);

	$wp_customize->add_setting(
		'paginae_manuscripti_illuminati_site_mark',
		array(
			'default'           => 'GC',
			'sanitize_callback' => 'paginae_manuscripti_illuminati_sanitize_short_text',
		)
	);

	$wp_customize->add_control(
		'paginae_manuscripti_illuminati_site_mark',
		array(
			'label'       => __( 'Header Seal Text', 'paginae-manuscripti-illuminati' ),
			'description' => __( 'Short text shown in the header seal.', 'paginae-manuscripti-illuminati' ),
			'section'     => 'paginae_manuscripti_illuminati_identity',
			'type'        => 'text',
		)
	);

	$wp_customize->add_section(
		'paginae_manuscripti_illuminati_typography',
		array(
			'title' => __( 'Typography', 'paginae-manuscripti-illuminati' ),
			'panel' => 'paginae_manuscripti_illuminati_panel',
		)
	);

	$font_choices = array();

	foreach ( paginae_manuscripti_illuminati_font_choices() as $font_name => $font ) {
		$font_choices[ $font_name ] = $font['label'];
	}

	$font_settings = array(
		'paginae_manuscripti_illuminati_font_body'       => array( __( 'Body Font', 'paginae-manuscripti-illuminati' ), 'EB Garamond' ),
		'paginae_manuscripti_illuminati_font_headings'   => array( __( 'Heading Font', 'paginae-manuscripti-illuminati' ), 'Cinzel' ),
		'paginae_manuscripti_illuminati_font_navigation' => array( __( 'Navigation and UI Font', 'paginae-manuscripti-illuminati' ), 'Marcellus' ),
		'paginae_manuscripti_illuminati_font_accent'     => array( __( 'Accent Font', 'paginae-manuscripti-illuminati' ), 'Uncial Antiqua' ),
	);

	foreach ( $font_settings as $setting_id => $setting ) {
		$wp_customize->add_setting(
			$setting_id,
			array(
				'default'           => $setting[1],
				'sanitize_callback' => 'paginae_manuscripti_illuminati_sanitize_font_choice',
			)
		);

		$wp_customize->add_control(
			$setting_id,
			array(
				'label'   => $setting[0],
				'section' => 'paginae_manuscripti_illuminati_typography',
				'type'    => 'select',
				'choices' => $font_choices,
			)
		);
	}

	$wp_customize->add_section(
		'paginae_manuscripti_illuminati_appearance',
		array(
			'title' => __( 'Colours and Texture', 'paginae-manuscripti-illuminati' ),
			'panel' => 'paginae_manuscripti_illuminati_panel',
		)
	);

	$color_settings = array(
		'paginae_manuscripti_illuminati_color_ink'       => array( __( 'Ink', 'paginae-manuscripti-illuminati' ), '#20170f' ),
		'paginae_manuscripti_illuminati_color_parchment' => array( __( 'Parchment', 'paginae-manuscripti-illuminati' ), '#f7efd9' ),
		'paginae_manuscripti_illuminati_color_rubric'    => array( __( 'Rubric Red', 'paginae-manuscripti-illuminati' ), '#8f211b' ),
		'paginae_manuscripti_illuminati_color_gold'      => array( __( 'Illumination Gold', 'paginae-manuscripti-illuminati' ), '#b8851d' ),
		'paginae_manuscripti_illuminati_color_blue'      => array( __( 'Hermetic Blue', 'paginae-manuscripti-illuminati' ), '#24446b' ),
		'paginae_manuscripti_illuminati_color_green'     => array( __( 'Verdigris Green', 'paginae-manuscripti-illuminati' ), '#445c3a' ),
	);

	foreach ( $color_settings as $setting_id => $setting ) {
		$wp_customize->add_setting(
			$setting_id,
			array(
				'default'           => $setting[1],
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				$setting_id,
				array(
					'label'   => $setting[0],
					'section' => 'paginae_manuscripti_illuminati_appearance',
				)
			)
		);
	}

	$wp_customize->add_setting(
		'paginae_manuscripti_illuminati_show_texture',
		array(
			'default'           => true,
			'sanitize_callback' => 'paginae_manuscripti_illuminati_sanitize_checkbox',
		)
	);

	$wp_customize->add_control(
		'paginae_manuscripti_illuminati_show_texture',
		array(
			'label'   => __( 'Use parchment texture', 'paginae-manuscripti-illuminati' ),
			'section' => 'paginae_manuscripti_illuminati_appearance',
			'type'    => 'checkbox',
		)
	);

	$wp_customize->add_section(
		'paginae_manuscripti_illuminati_wiki',
		array(
			'title' => __( 'Speculum Entries', 'paginae-manuscripti-illuminati' ),
			'panel' => 'paginae_manuscripti_illuminati_panel',
		)
	);

	$wp_customize->add_setting(
		'paginae_manuscripti_illuminati_enable_wiki_toc',
		array(
			'default'           => true,
			'sanitize_callback' => 'paginae_manuscripti_illuminati_sanitize_checkbox',
		)
	);

	$wp_customize->add_control(
		'paginae_manuscripti_illuminati_enable_wiki_toc',
		array(
			'label'   => __( 'Show automatic table of contents on Speculum entries', 'paginae-manuscripti-illuminati' ),
			'section' => 'paginae_manuscripti_illuminati_wiki',
			'type'    => 'checkbox',
		)
	);

	$wp_customize->add_setting(
		'paginae_manuscripti_illuminati_wiki_toc_depth',
		array(
			'default'           => 4,
			'sanitize_callback' => 'paginae_manuscripti_illuminati_sanitize_toc_depth',
		)
	);

	$wp_customize->add_control(
		'paginae_manuscripti_illuminati_wiki_toc_depth',
		array(
			'label'   => __( 'Table of Contents Depth', 'paginae-manuscripti-illuminati' ),
			'section' => 'paginae_manuscripti_illuminati_wiki',
			'type'    => 'select',
			'choices' => array(
				2 => __( 'H2 only', 'paginae-manuscripti-illuminati' ),
				3 => __( 'H2 and H3', 'paginae-manuscripti-illuminati' ),
				4 => __( 'H2, H3, and H4', 'paginae-manuscripti-illuminati' ),
			),
		)
	);

	$wp_customize->add_section(
		'paginae_manuscripti_illuminati_front_page',
		array(
			'title' => __( 'Front Page Sections', 'paginae-manuscripti-illuminati' ),
			'panel' => 'paginae_manuscripti_illuminati_panel',
		)
	);

	$front_settings = array(
		'paginae_manuscripti_illuminati_front_diary_count' => array( __( 'Full-width latest rows to show', 'paginae-manuscripti-illuminati' ), 3 ),
	);

	foreach ( $front_settings as $setting_id => $setting ) {
		$wp_customize->add_setting(
			$setting_id,
			array(
				'default'           => $setting[1],
				'sanitize_callback' => 'absint',
			)
		);

		$wp_customize->add_control(
			$setting_id,
			array(
				'label'       => $setting[0],
				'section'     => 'paginae_manuscripti_illuminati_front_page',
				'type'        => 'number',
				'input_attrs' => array(
					'min'  => 0,
					'max'  => 12,
					'step' => 1,
				),
			)
		);
	}

	$wp_customize->add_section(
		'paginae_manuscripti_illuminati_footer',
		array(
			'title' => __( 'Footer', 'paginae-manuscripti-illuminati' ),
			'panel' => 'paginae_manuscripti_illuminati_panel',
		)
	);

	$wp_customize->add_setting(
		'paginae_manuscripti_illuminati_footer_copyright_text',
		array(
			'default'           => '© {year} Benjamin Wolfe',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	$wp_customize->add_control(
		'paginae_manuscripti_illuminati_footer_copyright_text',
		array(
			'label'       => __( 'Footer copyright text', 'paginae-manuscripti-illuminati' ),
			'description' => __( 'Plain text displayed in the compact footer. Use {year} for the current year, or leave empty to hide it.', 'paginae-manuscripti-illuminati' ),
			'section'     => 'paginae_manuscripti_illuminati_footer',
			'type'        => 'text',
		)
	);
}
add_action( 'customize_register', 'paginae_manuscripti_illuminati_customize_register' );

/**
 * Sanitize layout mode.
 *
 * @param string $value Raw value.
 */
function paginae_manuscripti_illuminati_sanitize_layout_mode( string $value ): string {
	$allowed = array( 'no_sidebar', 'left_sidebar', 'right_sidebar', 'both_sidebars' );

	return in_array( $value, $allowed, true ) ? $value : 'right_sidebar';
}

/**
 * Sanitize checkbox values.
 *
 * @param mixed $value Raw value.
 */
function paginae_manuscripti_illuminati_sanitize_checkbox( mixed $value ): bool {
	return (bool) $value;
}

/**
 * Sanitize font choices.
 *
 * @param mixed $value Raw value.
 */
function paginae_manuscripti_illuminati_sanitize_font_choice( mixed $value ): string {
	$fonts = paginae_manuscripti_illuminati_font_choices();
	$value = is_string( $value ) ? $value : '';

	return isset( $fonts[ $value ] ) ? $value : 'EB Garamond';
}

/**
 * Sanitize TOC depth.
 *
 * @param mixed $value Raw value.
 */
function paginae_manuscripti_illuminati_sanitize_toc_depth( mixed $value ): int {
	return min( 4, max( 2, absint( $value ) ) );
}

/**
 * Sanitize short identity text.
 *
 * @param string $value Raw value.
 */
function paginae_manuscripti_illuminati_sanitize_short_text( string $value ): string {
	return substr( sanitize_text_field( $value ), 0, 5 );
}
