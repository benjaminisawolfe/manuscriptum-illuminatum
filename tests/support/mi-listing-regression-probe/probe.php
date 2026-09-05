<?php
/**
 * Plugin Name: Listing Regression Probe (temporary test fixture)
 * Description: Administrator-only, request-scoped query tests. Never distribute with the application.
 * Version: 1.0
 */

namespace ManuscriptumListingProbe;

use LigaturaManuscriptiIlluminati\SpeculumDirectory;
use LigaturaManuscriptiIlluminati\PersonaeDirectory;
use LigaturaManuscriptiIlluminati\JournalDirectory;
use LigaturaManuscriptiIlluminati\CovenantDirectory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_footer', static function (): void {
	if ( current_user_can( 'manage_options' ) ) {
		echo '<span hidden data-listing-probe-nonce="' . esc_attr( wp_create_nonce( 'listing_regression_probe' ) ) . '"></span>';
	}
} );

// No nopriv action: anonymous requests cannot invoke this fixture.
add_action( 'wp_ajax_listing_regression_probe', static function (): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Administrator required.', 403 );
	}
	check_ajax_referer( 'listing_regression_probe' );
	$mode = sanitize_key( $_POST['mode'] ?? '' );
	if ( ! in_array( $mode, array( 'authenticated', 'public_guest', 'private_guest' ), true ) ) {
		wp_send_json_error( 'Invalid test mode.', 400 );
	}

	$administrator = get_current_user_id();
	$marker        = 'ListingProbe' . wp_generate_password( 12, false, false );
	$post_ids      = array();
	$fixtures      = array();
	$checks        = array();
	$errors        = array();
	$privacy       = static fn(): bool => 'public_guest' !== $mode;
	$types         = array( 'post', 'ligatura_wiki', 'ligatura_character', 'ligatura_diary', 'ligatura_covenant' );
	$term          = wp_insert_term( $marker, 'ligatura_character_type' );

	try {
		if ( is_wp_error( $term ) ) {
			throw new \RuntimeException( 'Could not create fixture Character Type.' );
		}
		foreach ( $types as $type ) {
			foreach ( array( 'publish', 'publish', 'publish', 'draft', 'private', 'password' ) as $index => $status ) {
				$title = $marker . ' ' . $type . ' ' . $index;
				$args  = array(
					'post_type' => $type, 'post_status' => 'password' === $status ? 'publish' : $status,
					'post_title' => $title, 'post_content' => $title, 'post_excerpt' => $title,
					'post_author' => $administrator,
					'post_password' => 'password' === $status ? wp_generate_password( 32 ) : '',
					'meta_input' => array( 'ligatura_diary_saga_date' => '9998-01-01', 'ligatura_covenant_saga_date' => '9998-01-01', 'ligatura_storyguide_notes' => $marker . 'SecretNotes' ),
				);
				if ( 'ligatura_character' === $type ) {
					$args['tax_input'] = array( 'ligatura_character_type' => array( $term['term_id'] ) );
				}
				$id = wp_insert_post( $args, true );
				if ( is_wp_error( $id ) ) {
					throw new \RuntimeException( 'Could not create a fixture post.' );
				}
				$post_ids[] = $id;
				$fixtures[ $type ][] = array( 'id' => $id, 'title' => $title, 'status' => $status );
			}
		}

		// Simulate visibility only inside this already authenticated admin request.
		// No option, configuration, login gate, route, or other request is changed.
		add_filter( 'ligatura_manuscripti_illuminati_require_login', $privacy, PHP_INT_MAX );
		if ( 'authenticated' !== $mode ) {
			wp_set_current_user( 0 );
		}
		$visible = 'private_guest' !== $mode;
		$renderers = array(
			'post' => 'paginae_manuscripti_illuminati_render_annal_row',
			'ligatura_wiki' => 'paginae_manuscripti_illuminati_render_speculum_teaser',
			'ligatura_character' => 'paginae_manuscripti_illuminati_render_persona_teaser',
			'ligatura_diary' => 'paginae_manuscripti_illuminati_render_journal_row',
			'ligatura_covenant' => 'paginae_manuscripti_illuminati_render_covenant_record_row',
		);
		foreach ( $fixtures as $type => $entries ) {
			foreach ( $entries as $entry ) {
				$html = call_user_func( $renderers[ $type ], $entry['id'] );
				$expected = 'authenticated' === $mode || ( $visible && 'publish' === $entry['status'] );
				$checks[ $type . ':' . $entry['id'] . ':visibility' ] = $expected === str_contains( $html, $entry['title'] );
			}
		}
		$directories = array(
			'ligatura_wiki' => SpeculumDirectory\render_search_results( SpeculumDirectory\query_entries( SpeculumDirectory\sanitize_filters( array( 'speculum_q' => $marker ) ) ) ),
			'ligatura_character' => PersonaeDirectory\render_search_results( PersonaeDirectory\query_characters( PersonaeDirectory\sanitize_filters( array( 'personae_q' => $marker ) ) ) ),
			'ligatura_diary' => JournalDirectory\render_rows( JournalDirectory\query_journals( JournalDirectory\sanitize_filters( array( 'journal_q' => $marker ) ) )->posts ),
			'ligatura_covenant' => CovenantDirectory\render_rows( CovenantDirectory\query_records( CovenantDirectory\sanitize_filters( array( 'covenant_q' => $marker ) ) )->posts ),
		);
		foreach ( $directories as $type => $html ) {
			foreach ( $fixtures[ $type ] as $entry ) {
				$expected = $visible && 'draft' !== $entry['status'] && ( 'authenticated' === $mode || 'publish' === $entry['status'] );
				$checks[ $type . ':' . $entry['id'] . ':query' ] = $expected === str_contains( $html, $entry['title'] );
			}
			$checks[ $type . ':notes-not-listed' ] = ! str_contains( $html, $marker . 'SecretNotes' );
		}
		$groups = array( 'ligatura_wiki' => SpeculumDirectory\render_grouped_directory(), 'ligatura_character' => PersonaeDirectory\render_grouped_directory() );
		foreach ( $groups as $type => $html ) {
			$checks[ $type . ':unfiltered' ] = $visible === str_contains( $html, $fixtures[ $type ][0]['title'] );
		}
		foreach ( $types as $type ) {
			if ( 'post' === $type ) {
				$latest_posts = paginae_manuscripti_illuminati_recent_query( $type, 4 )->posts;
			} elseif ( 'ligatura_diary' === $type ) {
				$latest_posts = paginae_manuscripti_illuminati_latest_journal_posts( 4 );
			} else {
				$latest_posts = paginae_manuscripti_illuminati_recently_modified_query( $type, 4 )->posts;
			}
			$html = '';
			foreach ( $latest_posts as $post ) {
				if ( 'ligatura_character' === $type ) {
					$GLOBALS['post'] = $post;
					setup_postdata( $post );
					ob_start();
					get_template_part( 'template-parts/content', 'character-card', array( 'show_fallback' => false ) );
					$html .= ob_get_clean();
					wp_reset_postdata();
				} else {
					$html .= call_user_func( $renderers[ $type ], $post->ID );
				}
			}
			// Personae cards rely on the normal page login gate, tested in privacy.spec.js.
			if ( 'private_guest' !== $mode || 'ligatura_character' !== $type ) {
				$checks[ $type . ':latest' ] = $visible === str_contains( $html, $marker );
			}
			foreach ( $fixtures[ $type ] as $entry ) {
				if ( in_array( $entry['status'], array( 'private', 'draft' ), true ) ) {
					$checks[ $type . ':' . $entry['id'] . ':latest-exclusion' ] = ! str_contains( $html, $entry['title'] );
				}
			}
		}
	} catch ( \Throwable $error ) {
		$errors[] = $error->getMessage();
	} finally {
		wp_set_current_user( $administrator );
		remove_filter( 'ligatura_manuscripti_illuminati_require_login', $privacy, PHP_INT_MAX );
		foreach ( $post_ids as $id ) {
			if ( ! wp_delete_post( $id, true ) ) {
				$errors[] = 'Fixture cleanup failed for post ' . $id;
			}
		}
		if ( ! is_wp_error( $term ) && ! wp_delete_term( $term['term_id'], 'ligatura_character_type' ) ) {
			$errors[] = 'Fixture term cleanup failed.';
		}
	}
	wp_send_json_success( array( 'checks' => $checks, 'errors' => $errors ) );
} );
