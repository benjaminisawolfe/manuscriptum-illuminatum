<?php
/**
 * Demo content tools for non-production test installations.
 *
 * @package LigaturaManuscriptiIlluminati
 */

namespace LigaturaManuscriptiIlluminati\DemoContent;

use LigaturaManuscriptiIlluminati\PrivateNotes;
use LigaturaManuscriptiIlluminati\JournalDates;
use LigaturaManuscriptiIlluminati\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META_KEY       = '_paginae_manuscripti_illuminati_demo_content';
const REST_NAMESPACE = 'ligatura-manuscripti-illuminati/v1';

/**
 * Post types managed by the demo-content tooling.
 *
 * @return string[]
 */
function managed_post_types(): array {
	return array( 'post', 'page', 'ligatura_character', 'ligatura_wiki', 'ligatura_diary', 'ligatura_covenant' );
}

/**
 * Register the hidden cleanup marker for demo records.
 */
function register_demo_meta(): void {
	foreach ( managed_post_types() as $post_type ) {
		register_post_meta(
			$post_type,
			META_KEY,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => __NAMESPACE__ . '\\can_access_demo_meta',
				'show_in_rest'      => true,
			)
		);
	}
}

/**
 * Check demo marker meta access.
 *
 * @param bool|null $allowed   Existing result.
 * @param string    $meta_key  Meta key.
 * @param int       $object_id Post ID.
 * @param int       $user_id   User ID.
 * @param string    $cap       Requested meta capability.
 */
function can_access_demo_meta( $allowed, string $meta_key, int $object_id, int $user_id = 0, string $cap = 'read_post_meta' ): bool {
	unset( $allowed, $meta_key, $user_id );

	if ( str_contains( $cap, 'edit' ) || str_contains( $cap, 'delete' ) || str_contains( $cap, 'add' ) ) {
		return current_user_can( 'edit_post', $object_id );
	}

	return current_user_can( 'read_post', $object_id );
}

/**
 * Register guarded REST endpoints for authenticated test automation.
 */
function register_rest_routes(): void {
	register_rest_route(
		REST_NAMESPACE,
		'/demo-content/status',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\rest_status',
			'permission_callback' => __NAMESPACE__ . '\\can_manage_demo_content',
		)
	);

	register_rest_route(
		REST_NAMESPACE,
		'/demo-content/create',
		array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\\rest_create',
			'permission_callback' => __NAMESPACE__ . '\\can_manage_demo_content',
			'args'                => array(
				'replace' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
		)
	);

	register_rest_route(
		REST_NAMESPACE,
		'/demo-content/delete',
		array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => __NAMESPACE__ . '\\rest_delete',
			'permission_callback' => __NAMESPACE__ . '\\can_manage_demo_content',
		)
	);
}

/**
 * Register the WP-CLI command when WP-CLI is available.
 */
function register_cli_command(): void {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		\WP_CLI::add_command( 'manuscriptum-illuminatum demo-content', CLI_Command::class );
	}
}

/**
 * Determine whether demo content may be managed on this site.
 */
function is_safe_environment(): bool {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$env  = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

	if ( in_array( $env, array( 'local', 'development', 'staging' ), true ) ) {
		return true;
	}

	return str_starts_with( $host, 'dev' ) || str_contains( $host, 'staging' ) || str_contains( $host, '.test' ) || str_contains( $host, '.local' );
}

/**
 * REST permission callback.
 */
function can_manage_demo_content(): bool {
	return current_user_can( 'manage_options' ) && is_safe_environment();
}

/**
 * REST status callback.
 */
function rest_status(): \WP_REST_Response {
	return rest_ensure_response(
		array(
			'safe_environment' => is_safe_environment(),
			'counts'           => count_demo_content(),
		)
	);
}

/**
 * REST create callback.
 *
 * @param \WP_REST_Request $request REST request.
 */
function rest_create( \WP_REST_Request $request ): \WP_REST_Response {
	return rest_ensure_response( create_demo_content( (bool) $request->get_param( 'replace' ) ) );
}

/**
 * REST delete callback.
 */
function rest_delete(): \WP_REST_Response {
	return rest_ensure_response( delete_demo_content() );
}

/**
 * Create the demo corpus.
 *
 * @param bool $replace Delete existing demo content first.
 * @return array<string,mixed>
 */
function create_demo_content( bool $replace = false ): array {
	if ( ! is_safe_environment() ) {
		return array(
			'created' => false,
			'error'   => __( 'Demo content can only be managed in a non-production test environment.', 'ligatura-manuscripti-illuminati' ),
			'counts'  => count_demo_content(),
		);
	}

	$existing = count_demo_content();

	if ( array_sum( $existing ) > 0 ) {
		if ( ! $replace ) {
			return array(
				'created' => false,
				'message' => __( 'Demo content already exists. Use replace to rebuild it.', 'ligatura-manuscripti-illuminati' ),
				'counts'  => $existing,
			);
		}

		delete_demo_content();
	}

	Taxonomies\seed_default_terms();

	$authors = get_demo_author_ids();
	$created = array();

	foreach ( demo_pages() as $item ) {
		create_demo_post( 'page', $item, $authors[0], $created );
	}

	foreach ( demo_posts() as $index => $item ) {
		create_demo_post( 'post', $item, $authors[ $index % count( $authors ) ], $created );
	}

	foreach ( demo_characters() as $index => $item ) {
		create_demo_post( 'ligatura_character', $item, $authors[ $index % count( $authors ) ], $created );
	}

	foreach ( demo_wiki_entries() as $index => $item ) {
		create_demo_post( 'ligatura_wiki', $item, $authors[ $index % count( $authors ) ], $created );
	}

	foreach ( demo_covenant_records() as $index => $item ) {
		create_demo_post( 'ligatura_covenant', $item, $authors[ $index % count( $authors ) ], $created );
	}

	foreach ( demo_diaries() as $index => $item ) {
		create_demo_post( 'ligatura_diary', $item, $authors[ $index % count( $authors ) ], $created );
	}

	apply_demo_relationships( $created );

	return array(
		'created' => true,
		'counts'  => count_demo_content(),
	);
}

/**
 * Delete all generated demo content.
 *
 * @return array<string,mixed>
 */
function delete_demo_content(): array {
	$ids = get_demo_post_ids();

	foreach ( $ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}

	return array(
		'deleted' => count( $ids ),
		'counts'  => count_demo_content(),
	);
}

/**
 * Count demo records by post type.
 *
 * @return array<string,int>
 */
function count_demo_content(): array {
	$counts = array();

	foreach ( managed_post_types() as $post_type ) {
		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'meta_key'       => META_KEY,
				'meta_value'     => '1',
				'no_found_rows'  => false,
			)
		);

		$counts[ $post_type ] = (int) $query->found_posts;
	}

	return $counts;
}

/**
 * Return all demo post IDs.
 *
 * @return int[]
 */
function get_demo_post_ids(): array {
	$query = new \WP_Query(
		array(
			'post_type'      => managed_post_types(),
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_key'       => META_KEY,
			'meta_value'     => '1',
		)
	);

	return array_map( 'absint', $query->posts );
}

/**
 * Pick existing site users for authorship without creating accounts.
 *
 * @return int[]
 */
function get_demo_author_ids(): array {
	$users = get_users(
		array(
			'role__in' => array( 'ligatura_player', 'ligatura_storyguide', 'administrator' ),
			'fields'   => 'ID',
			'number'   => 8,
			'orderby'  => 'ID',
		)
	);

	if ( empty( $users ) ) {
		$users = get_users(
			array(
				'fields'  => 'ID',
				'number'  => 1,
				'orderby' => 'ID',
			)
		);
	}

	return array_map( 'absint', $users ?: array( 1 ) );
}

/**
 * Create a single demo post.
 *
 * @param string              $post_type Post type.
 * @param array<string,mixed> $item      Demo item.
 * @param int                 $author_id Author ID.
 * @param array<string,int>   $created   Created key => post ID map.
 */
function create_demo_post( string $post_type, array $item, int $author_id, array &$created ): int {
	$post_data = array(
		'post_type'    => $post_type,
		'post_status'  => 'publish',
		'post_author'  => $author_id,
		'post_title'   => $item['title'],
		'post_name'    => $item['slug'] ?? sanitize_title( $item['title'] ),
		'post_excerpt' => $item['excerpt'] ?? '',
		'post_content' => $item['content'] ?? build_content( $item ),
	);
	$tax_input = array();

	foreach ( $item['terms'] ?? array() as $taxonomy => $terms ) {
		$taxonomy = (string) $taxonomy;
		$terms    = (array) $terms;

		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		foreach ( $terms as $term ) {
			if ( ! term_exists( $term, $taxonomy ) ) {
				wp_insert_term( $term, $taxonomy );
			}
		}

		$tax_input[ $taxonomy ] = $terms;
	}

	if ( ! empty( $tax_input ) ) {
		$post_data['tax_input'] = $tax_input;
	}

	if ( ! empty( $item['post_date'] ) ) {
		$post_data['post_date'] = $item['post_date'];
	}

	$post_id = 0;

	if ( 'page' === $post_type && ! empty( $post_data['post_name'] ) ) {
		$existing_page = get_page_by_path( (string) $post_data['post_name'], OBJECT, 'page' );

		if ( $existing_page instanceof \WP_Post
			&& 0 === (int) $existing_page->post_parent
			&& '' === trim( (string) $existing_page->post_content )
		) {
			$post_data['ID'] = $existing_page->ID;
			$post_id         = wp_update_post( wp_slash( $post_data ), true );
		}
	}

	if ( ! $post_id ) {
		$post_id = wp_insert_post( wp_slash( $post_data ), true );
	}

	if ( is_wp_error( $post_id ) ) {
		return 0;
	}

	update_post_meta( $post_id, META_KEY, true );

	foreach ( $item['meta'] ?? array() as $key => $value ) {
		update_post_meta( $post_id, (string) $key, $value );
	}

	if ( isset( $item['storyguide_notes'] ) && 'ligatura_wiki' === $post_type ) {
		update_post_meta( $post_id, PrivateNotes\META_KEY, wp_kses_post( (string) $item['storyguide_notes'] ) );
	}

	foreach ( $item['terms'] ?? array() as $taxonomy => $terms ) {
		assign_demo_terms( $post_id, (string) $taxonomy, (array) $terms );
	}

	if ( isset( $item['key'] ) ) {
		$created[ (string) $item['key'] ] = $post_id;
	}

	return $post_id;
}

/**
 * Assign terms, creating missing demo-only terms when needed.
 *
 * @param int      $post_id  Post ID.
 * @param string   $taxonomy Taxonomy name.
 * @param string[] $terms    Term names.
 */
function assign_demo_terms( int $post_id, string $taxonomy, array $terms ): void {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return;
	}

	foreach ( $terms as $term ) {
		if ( ! term_exists( $term, $taxonomy ) ) {
			wp_insert_term( $term, $taxonomy );
		}
	}

	wp_set_object_terms( $post_id, $terms, $taxonomy, false );
}

/**
 * Apply relationships that require created post IDs.
 *
 * @param array<string,int> $created Created key => post ID map.
 */
function apply_demo_relationships( array $created ): void {
	foreach ( demo_wiki_entries() as $item ) {
		if ( empty( $item['key'] ) || empty( $created[ $item['key'] ] ) ) {
			continue;
		}

		$post_id = $created[ $item['key'] ];

		update_id_list_meta( $post_id, 'ligatura_related_characters', ids_for_keys( $created, $item['related_characters'] ?? array() ) );
		update_id_list_meta( $post_id, 'ligatura_related_entries', ids_for_keys( $created, $item['related_entries'] ?? array() ) );
		update_id_list_meta( $post_id, 'ligatura_related_places', ids_for_keys( $created, $item['related_places'] ?? array() ) );
	}

	foreach ( demo_covenant_records() as $item ) {
		if ( empty( $item['key'] ) || empty( $created[ $item['key'] ] ) ) {
			continue;
		}

		$post_id = $created[ $item['key'] ];
		update_id_list_meta( $post_id, 'ligatura_related_characters', ids_for_keys( $created, $item['related_characters'] ?? array() ) );
		update_id_list_meta( $post_id, 'ligatura_related_entries', ids_for_keys( $created, $item['related_entries'] ?? array() ) );
		update_id_list_meta( $post_id, 'ligatura_related_places', ids_for_keys( $created, $item['related_places'] ?? array() ) );
	}

	foreach ( demo_diaries() as $item ) {
		if ( empty( $item['key'] ) || empty( $item['related_character'] ) || empty( $created[ $item['key'] ] ) || empty( $created[ $item['related_character'] ] ) ) {
			continue;
		}

		update_post_meta( $created[ $item['key'] ], 'ligatura_diary_character', $created[ $item['related_character'] ] );
	}
}

/**
 * Convert keys to created IDs.
 *
 * @param array<string,int> $created Created key => post ID map.
 * @param string[]          $keys    Entity keys.
 * @return int[]
 */
function ids_for_keys( array $created, array $keys ): array {
	$ids = array();

	foreach ( $keys as $key ) {
		if ( isset( $created[ $key ] ) ) {
			$ids[] = $created[ $key ];
		}
	}

	return $ids;
}

/**
 * Update a comma-separated ID-list meta field.
 *
 * @param int   $post_id Post ID.
 * @param string $key    Meta key.
 * @param int[] $ids     IDs.
 */
function update_id_list_meta( int $post_id, string $key, array $ids ): void {
	if ( empty( $ids ) ) {
		return;
	}

	update_post_meta( $post_id, $key, implode( ',', array_map( 'absint', $ids ) ) );
}

/**
 * Build rich post content from structured demo arrays.
 *
 * @param array<string,mixed> $item Demo item.
 */
function build_content( array $item ): string {
	$html = '';

	foreach ( $item['intro'] ?? array() as $paragraph ) {
		$html .= '<p>' . wp_kses_post( $paragraph ) . '</p>';
	}

	if ( ! empty( $item['quote'] ) ) {
		$html .= '<blockquote><p>' . wp_kses_post( (string) $item['quote'] ) . '</p></blockquote>';
	}

	if ( ! empty( $item['list'] ) ) {
		$html .= '<ul>';
		foreach ( (array) $item['list'] as $entry ) {
			$html .= '<li>' . wp_kses_post( (string) $entry ) . '</li>';
		}
		$html .= '</ul>';
	}

	foreach ( $item['sections'] ?? array() as $section ) {
		$level = min( 4, max( 2, absint( $section['level'] ?? 2 ) ) );
		$html .= '<h' . $level . '>' . esc_html( (string) $section['heading'] ) . '</h' . $level . '>';

		foreach ( $section['paragraphs'] ?? array() as $paragraph ) {
			$html .= '<p>' . wp_kses_post( (string) $paragraph ) . '</p>';
		}

		if ( ! empty( $section['list'] ) ) {
			$html .= '<ol>';
			foreach ( (array) $section['list'] as $entry ) {
				$html .= '<li>' . wp_kses_post( (string) $entry ) . '</li>';
			}
			$html .= '</ol>';
		}

		if ( ! empty( $section['table'] ) ) {
			$html .= build_table( (array) $section['table'] );
		}
	}

	return $html;
}

/**
 * Build a simple HTML table.
 *
 * @param array<string,array<int,array<int,string>>|array<int,string>> $table Table data.
 */
function build_table( array $table ): string {
	$html = '<table><thead><tr>';

	foreach ( $table['headers'] ?? array() as $header ) {
		$html .= '<th>' . esc_html( (string) $header ) . '</th>';
	}

	$html .= '</tr></thead><tbody>';

	foreach ( $table['rows'] ?? array() as $row ) {
		$html .= '<tr>';
		foreach ( (array) $row as $cell ) {
			$html .= '<td>' . esc_html( (string) $cell ) . '</td>';
		}
		$html .= '</tr>';
	}

	$html .= '</tbody></table>';

	return $html;
}

/**
 * Demo pages.
 *
 * @return array<int,array<string,mixed>>
 */
function demo_pages(): array {
	return array(
		array(
			'key'     => 'page_wiki_directory',
			'title'   => 'Speculum',
			'slug'    => 'wiki',
			'excerpt' => 'An editable index page for campaign lore.',
			'intro'   => array(
				'This editable Speculum page introduces the covenant lore before the generated index of Wiki entries.',
				'Storyguides can revise these paragraphs in the WordPress editor without changing the underlying content model.',
			),
		),
		array(
			'key'     => 'page_characters_directory',
			'title'   => 'Personae',
			'slug'    => 'characters',
			'excerpt' => 'An editable index page for campaign characters.',
			'intro'   => array(
				'This editable Personae page introduces the people of the saga before the generated alphabetical character index.',
				'The directory below is assembled from Character records rather than from hard-coded Page text.',
			),
		),
		array(
			'key'     => 'page_journals_directory',
			'title'   => 'Commentarii',
			'slug'    => 'journals',
			'excerpt' => 'An editable index page for campaign Journals.',
			'intro'   => array(
				'This editable Commentarii page gives Journals a top-level home while preserving normal Page editing.',
				'The directory below follows Saga Date from newest to oldest, with undated Journals kept after dated entries.',
			),
		),
		array(
			'key'     => 'page_covenant_records_directory',
			'title'   => 'Covenant Records',
			'slug'    => 'covenant-records',
			'excerpt' => 'An editable index page for covenant records.',
			'intro'   => array(
				'This editable covenant-records page introduces the covenant’s practical archives before the generated record index.',
				'The entries below come from Covenant Record posts and remain separate from this Page body.',
			),
		),
		array(
			'key'     => 'page_saga_primer',
			'title'   => 'Saga Primer',
			'slug'    => 'saga-primer',
			'excerpt' => 'A plain-language starting point for the demonstration saga.',
			'intro'   => array(
				'The covenant watches the Seine from the shadow of Quiet Bell, where Hermetic caution meets the noise of royal engineers, toll clerks, monks, and river traffic.',
				'Use this page as a demo overview for navigation, introductory paragraphs, and ordinary page typography.',
			),
			'sections' => array(
				array(
					'heading'    => 'What Players Know',
					'paragraphs' => array(
						'The covenant is young, argumentative, and not quite as secure as its charter claims. The magi hold a modest library, a few contested vis sources, and an agreement with local workers who prefer payment in coin rather than wonders.',
					),
					'list'       => array( 'The covenant is near Andely in Normandy.', 'The mundane war has made every road suspicious.', 'Hermetic visitors arrive by Redcap, riverboat, and rumor.' ),
				),
				array(
					'heading'    => 'Useful Links',
					'paragraphs' => array(
						'See the <a href="/wiki/covenant-of-the-quiet-bell/">Covenant of the Quiet Bell</a> Speculum entry and the <a href="/characters/">Personae directory</a> for more staged examples.',
					),
				),
			),
		),
		array(
			'key'     => 'page_roster',
			'title'   => 'Covenant Roster',
			'slug'    => 'covenant-roster',
			'excerpt' => 'A page for checking lists and short character references.',
			'intro'   => array( 'This roster records the people most often named in table conversation, from sworn magi to the cook who knows which tower leaks first.' ),
			'sections' => array(
				array(
					'heading' => 'Council',
					'table'   => array(
						'headers' => array( 'Name', 'Role', 'Usual Concern' ),
						'rows'    => array(
							array( 'Aveline of Bonisagus', 'Librarian and theorist', 'Keeping experiments documented' ),
							array( 'Ysabeau of Merinita', 'Envoy to strange neighbors', 'Promising less than she knows' ),
							array( 'Martin le Verrier', 'Mundane liaison', 'Noble manners and costly glass' ),
						),
					),
				),
			),
		),
		array(
			'key'     => 'page_seasonal_ledger',
			'title'   => 'Seasonal Ledger',
			'slug'    => 'seasonal-ledger',
			'excerpt' => 'Seasonal notes suitable for dense page content.',
			'intro'   => array( 'The ledger is a practical page for stores, debts, copying projects, and minor crises that do not deserve a full covenant record.' ),
			'sections' => array(
				array(
					'heading'    => 'Spring',
					'paragraphs' => array( 'Rain damaged two sacks of rye, but the herb beds revived. A Redcap delivered three letters and requested a dry pallet in the guest house.' ),
				),
				array(
					'heading'    => 'Summer',
					'paragraphs' => array( 'The lower gate needed new iron straps. Renaud says the smith did good work but drank like a bishop at Easter.' ),
				),
			),
		),
		array(
			'key'     => 'page_house_rules',
			'title'   => 'House Rules',
			'slug'    => 'house-rules',
			'excerpt' => 'A compact page with lists and internal organization.',
			'intro'   => array( 'This page is not a rules engine. It is a demo stand-in for the sort of practical reference a troupe tends to maintain.' ),
			'list'    => array( 'Laboratory totals are recorded narratively.', 'Long-term correspondence may count as exposure when it changes play.', 'Vis accounting should name both source and season.' ),
		),
		array(
			'key'     => 'page_letters',
			'title'   => 'Player Correspondence',
			'slug'    => 'player-correspondence',
			'excerpt' => 'A page for letters, quoted text, and gentle line lengths.',
			'intro'   => array( 'Letters are copied here when the troupe wants a public version without private Storyguide notes.' ),
			'quote'   => 'To my sodales at the covenant: the river road is open, but the toll is now counted twice and argued thrice.',
		),
		array(
			'key'     => 'page_long_title',
			'title'   => 'Questions To Settle Before the Next Tribunal Gathering in Normandy',
			'slug'    => 'questions-before-tribunal-gathering',
			'excerpt' => 'A deliberately long page title for wrapping tests.',
			'intro'   => array( 'This page exists to exercise a long primary title, ordinary paragraphs, and the title rule under realistic wrapping pressure.' ),
			'sections' => array(
				array(
					'heading'    => 'Open Questions',
					'paragraphs' => array( 'The council has not yet agreed who may speak for the covenant if questioned about vis rights, mundane obligations, or the strange courtesy owed to the Ash-Well.' ),
				),
			),
		),
	);
}

/**
 * Demo standard posts.
 *
 * @return array<int,array<string,mixed>>
 */
function demo_posts(): array {
	return array(
		demo_simple_item( 'post_spring_rain', 'Spring Rain over the Lower Bailey', 'spring-rain-over-the-lower-bailey', 'Rain filled the ditch, washed chalk from the path, and made every parchment curl at the corners.', '2026-08-19 09:00:00' ),
		demo_simple_item( 'post_redcap_arrives', 'A Redcap Arrives After Compline', 'redcap-arrives-after-compline', 'Petrus came late, refused wine, and asked whether the covenant still had a dry room with a door that locked.', '2026-08-20 21:10:00' ),
		demo_simple_item( 'post_market_argument', 'The Market Argument at Petit Andely', 'market-argument-at-petit-andely', 'A wool merchant accused the covenant cook of buying onions with clipped coin. Heloise settled the matter before a priest could make it worse.', '2026-08-21 12:30:00' ),
		demo_simple_item( 'post_tower_lamp', 'The Lamp in the East Tower', 'lamp-in-the-east-tower', 'Three covenfolk saw a blue lamp in the sealed tower window. The magi insist it was only moonlight through old glass.', '2026-08-22 23:00:00' ),
		demo_simple_item( 'post_bees', 'Bees in the Scriptorium', 'bees-in-the-scriptorium', 'The bees gathered around a damaged tractatus on Intellego. No one agrees whether this is auspicious.', '2026-08-23 14:00:00' ),
		demo_simple_item( 'post_salt_barge', 'Notes from the Salt Barge', 'notes-from-the-salt-barge', 'The salt barge brought gossip from Rouen, three cracked amphorae, and a sailor who claims he heard bells under the river.', '2026-08-24 17:20:00' ),
		demo_simple_item( 'post_old_goat', 'The Old Goat Returns Without Its Bell', 'old-goat-returns-without-its-bell', 'The animal was missing for two nights and came back smelling of apples in a month when no apple should be ripe.', '2026-08-25 08:40:00' ),
		demo_simple_item( 'post_long', 'A Rather Full Account of the Day the Gatehouse Refused to Echo Properly', 'gatehouse-refused-to-echo-properly', 'The gatehouse echo answered in Latin until noon and in poor Norman French thereafter. Renaud has asked that nobody test it after dark.', '2026-08-26 16:00:00' ),
	);
}

/**
 * Build a simple demo item.
 */
function demo_simple_item( string $key, string $title, string $slug, string $intro, string $date ): array {
	return array(
		'key'     => $key,
		'title'   => $title,
		'slug'    => $slug,
		'post_date' => $date,
		'excerpt' => $intro,
		'intro'   => array( $intro, 'The note is public table color for demo archive cards, excerpts, dates, and ordinary post rhythm.' ),
	);
}

/**
 * Demo characters.
 *
 * @return array<int,array<string,mixed>>
 */
function demo_characters(): array {
	return array(
		character_item( 'char_aveline', 'Aveline of Bonisagus', 'aveline-of-bonisagus', 'Magus', 'Bonisagus', 'Librarian', 'Patient, exacting, and more comfortable with marginal notes than visitors.' ),
		character_item( 'char_martin', 'Martin le Verrier of House Jerbiton', 'martin-le-verrier', 'Magus', 'Jerbiton', 'Mundane liaison', 'A polished diplomat who collects glass, rumors, and inconvenient obligations.' ),
		character_item( 'char_ysabeau', 'Ysabeau Under the Apple Bough', 'ysabeau-under-the-apple-bough', 'Magus', 'Merinita', 'Envoy', 'A Merinita maga whose bargains are tidy until they are not.' ),
		character_item( 'char_odo', 'Brother Odo of Saint-Wandrille', 'brother-odo-of-saint-wandrille', 'Companion', '', 'Chaplain and copyist', 'A Benedictine scholar with careful hands and a talent for noticing omissions.' ),
		character_item( 'char_renaud', 'Renaud the Gatewarden', 'renaud-the-gatewarden', 'Grog', '', 'Gatewarden', 'A scarred veteran who trusts hinges, dogs, and short answers.' ),
		character_item( 'char_heloise', 'Heloise of the Dye House', 'heloise-of-the-dye-house', 'Covenfolk', '', 'Steward', 'She knows which debts are real and which are theater.' ),
		character_item( 'char_colin', 'Colin Two-Knives', 'colin-two-knives', 'Companion', '', 'Scout', 'A river scout with a talent for vanishing before questions are assigned.' ),
		character_item( 'char_sibyl', 'Sibyl of the Ash Well', 'sibyl-of-the-ash-well', 'NPC', '', 'Local wise woman', 'She speaks as if every promise has already been witnessed by the trees.' ),
		character_item( 'char_petrus', 'Petrus Quick-Sandal', 'petrus-quick-sandal', 'NPC', 'Mercere', 'Redcap', 'A Redcap who counts milestones, secrets, and meals with equal seriousness.' ),
		character_item( 'char_guarin', 'Guarin of Tremere, Who Measures Every Door Before Entering', 'guarin-of-tremere-measures-every-door', 'Magus', 'Tremere', 'Vis auditor', 'A visiting magus whose courtesy feels like a ledger being opened.' ),
	);
}

/**
 * Build one character item.
 */
function character_item( string $key, string $title, string $slug, string $type, string $house, string $role, string $summary ): array {
	$age = match ( $type ) {
		'Magus'     => 44,
		'Grog'      => 37,
		'Covenfolk' => 31,
		default     => 29,
	};

	return array(
		'key'     => $key,
		'title'   => $title,
		'slug'    => $slug,
		'excerpt' => $summary,
		'intro'   => array( $summary ),
		'sections' => array(
			array(
				'heading'    => 'At the Table',
				'paragraphs' => array( 'This character is staged to exercise portrait-free cards, structured sheet fields, public notes, and variable-length prose.' ),
			),
		),
		'meta'    => array(
			'ligatura_character_type'      => $type,
			'ligatura_house'               => $house,
			'ligatura_tradition'           => $house ? 'Hermetic' : 'Mundane',
			'ligatura_player'              => $type === 'Magus' ? 'Storyguide pool' : 'Open troupe character',
			'ligatura_apparent_age'        => $age,
			'ligatura_status'              => 'Active',
			'ligatura_age'                 => $age,
			'ligatura_birth_year'          => (string) ( 1204 - $age ),
			'ligatura_origin'              => 'Normandy',
			'ligatura_occupation'          => $role,
			'ligatura_role'                => $role,
			'ligatura_covenant_role'       => $role,
			'ligatura_brief_description'   => $summary,
			'ligatura_characteristics'     => 'Int +1, Per +1, Pre 0, Com +1, Str 0, Sta +1, Dex 0, Qik 0',
			'ligatura_personality_traits'  => 'Careful +2, Proud +1, Curious +2',
			'ligatura_reputations'         => $house ? 'Order of Hermes: promising correspondent 1' : 'Local: dependable 1',
			'ligatura_confidence'          => 'Confidence 1 (3 points)',
			'ligatura_warping'             => $type === 'Magus' ? 'Warping 1' : 'Warping 0',
			'ligatura_wounds'              => 'No current wounds.',
			'ligatura_virtues'             => $house ? 'The Gift; Hermetic Magus; Puissant Area Lore' : 'Educated; Tough; Well-Traveled',
			'ligatura_flaws'               => $house ? 'Driven; Covenant Upbringing; Busybody' : 'Dependent; Oathbound; Judged by Rumor',
			'ligatura_abilities'           => 'Area Lore: Normandy 3, Latin 4, Artes Liberales 2, Awareness 3',
			'ligatura_arts'                => $house ? 'Intellego 8, Vim 7, Corpus 5, Mentem 4' : '',
			'ligatura_spells'              => $house ? 'Lamp Without Flame; Whisper Through the Crack; Measure the Hidden Thread' : '',
			'ligatura_equipment'           => 'Travel cloak, wax tablets, belt knife, sealed pouch',
			'ligatura_full_sheet'          => '<p><strong>Use in play:</strong> ' . esc_html( $summary ) . '</p><p>The full sheet area intentionally contains enough rich text to test longer character pages without becoming a rules engine.</p>',
			'ligatura_notes_public'        => '<p>Public demo note: this record is part of the generated demo corpus and may be deleted later.</p>',
		),
		'terms'   => array(
			'ligatura_character_type' => array( $type ),
			'ligatura_saga_topic'     => array( 'Covenant Affairs', 'Normandy' ),
			'ligatura_hermetic_house' => $house ? array( $house ) : array(),
		),
	);
}

/**
 * Demo wiki entries.
 *
 * @return array<int,array<string,mixed>>
 */
function demo_wiki_entries(): array {
	return array(
		wiki_item( 'wiki_covenant', 'Covenant of the Quiet Bell', 'covenant-of-the-quiet-bell', 'Covenant', 'The covenant, its charter, and its uneasy place below the royal fortress.', array( 'char_aveline', 'char_heloise' ), array( 'Covenant Affairs', 'Quiet Bell' ), true ),
		wiki_item( 'wiki_ford', 'White Hart Ford', 'white-hart-ford', 'Place', 'A shallow river crossing where witnesses report a silent white hart at dawn.', array( 'char_colin' ), array( 'Vis', 'Faerie' ), true ),
		wiki_item( 'wiki_cistern', 'The Whispering Cistern Beneath the Old Kitchen', 'whispering-cistern-beneath-old-kitchen', 'Mystery', 'A damp chamber whose echoes repeat words that were not spoken there.', array( 'char_renaud' ), array( 'Secrets', 'Covenant Affairs' ), true ),
		wiki_item( 'wiki_petit_andely', 'Village of Petit Andely', 'village-of-petit-andely', 'Place', 'The nearest market village and the source of most mundane complications.', array( 'char_heloise' ), array( 'Andely', 'Mundanes' ), false ),
		wiki_item( 'wiki_ledger', 'The Toll Clerk\'s Ledger', 'toll-clerks-ledger', 'Artifact', 'A mundane ledger that records one more toll than any clerk remembers collecting.', array( 'char_martin' ), array( 'Politics', 'Mundanes' ), false ),
		wiki_item( 'wiki_thorn', 'Shrine of Saint Clothilde\'s Thorn', 'shrine-of-saint-clothildes-thorn', 'Place', 'A small roadside shrine where thorn blossoms appear after unconfessed bargains.', array( 'char_odo' ), array( 'Church', 'Secrets' ), true ),
		wiki_item( 'wiki_orchard', 'Regio of the Glass Orchard', 'regio-of-the-glass-orchard', 'Regio', 'A regio whose fruit reflects faces that have not yet arrived.', array( 'char_ysabeau' ), array( 'Faerie', 'Vis' ), true ),
		wiki_item( 'wiki_yarrow', 'Vis Source: Moonlit Yarrow', 'vis-source-moonlit-yarrow', 'Vis Source', 'A patch of yarrow that gathers Creo vis during the first clear moon of spring.', array( 'char_aveline' ), array( 'Vis', 'Covenant Affairs' ), false ),
		wiki_item( 'wiki_jerbiton_letters', 'House Jerbiton Correspondence in Rouen', 'house-jerbiton-correspondence-rouen', 'Hermetic House', 'Letters, favors, and delicate invitations moving through Rouen.', array( 'char_martin', 'char_petrus' ), array( 'Order of Hermes', 'Politics' ), false ),
		wiki_item( 'wiki_charter', 'The Covenant Charter of Quiet Bells', 'covenant-charter-of-quiet-bells', 'Covenant', 'The written agreement that defines the covenant\'s duties, silence, and shared stores.', array( 'char_guarin', 'char_aveline' ), array( 'Covenant Affairs', 'Tribunal' ), true ),
		wiki_item( 'wiki_bell', 'The Broken Bell of Saint Remigius', 'broken-bell-saint-remigius', 'Artifact', 'A cracked bell that rings in dreams when river fog reaches the lower ward.', array( 'char_odo' ), array( 'Church', 'Secrets' ), false ),
		wiki_item( 'wiki_baronial_claims', 'Norman Baronial Claims Around the Rock', 'norman-baronial-claims-around-rock', 'Noble House', 'A tangle of mundane claims that makes every friendly dinner potentially legal evidence.', array( 'char_martin' ), array( 'Politics', 'Normandy' ), false ),
		wiki_item( 'wiki_redcap_route', 'The Redcap Route Through Vexin', 'redcap-route-through-vexin', 'Faction', 'The messenger path that keeps the covenant in conversation with the wider Order.', array( 'char_petrus' ), array( 'Order of Hermes', 'Tribunal' ), false ),
		wiki_item( 'wiki_ash_well', 'The Ash-Well Bargain', 'ash-well-bargain', 'Mystery', 'An old promise remembered by ash trees, old women, and one nervous magus.', array( 'char_sibyl', 'char_ysabeau' ), array( 'Faerie', 'Secrets' ), true ),
		wiki_item( 'wiki_tribunal_rumors', 'Winter Tribunal Rumors Concerning the Siege Roads and the River Tolls', 'winter-tribunal-rumors-siege-roads-river-tolls', 'Event', 'A long-titled entry for testing wrapped archive and single-entry headings.', array( 'char_guarin', 'char_petrus' ), array( 'Tribunal', 'Politics' ), false ),
	);
}

/**
 * Build a wiki item.
 *
 * @param string[] $characters Related character keys.
 * @param string[] $topics     Saga topic terms.
 */
function wiki_item( string $key, string $title, string $slug, string $entry_type, string $summary, array $characters, array $topics, bool $long ): array {
	$sections = array(
		array(
			'heading'    => 'What Is Known',
			'paragraphs' => array( $summary . ' The public account is useful but incomplete, as most public accounts around the covenant tend to be.' ),
		),
		array(
			'heading'    => 'Use in Play',
			'paragraphs' => array( 'This entry gives the demo site a realistic mix of lore, cross-links, and template content. It can be read as table color rather than final campaign canon.' ),
			'list'       => array( 'Mention it when visitors ask about local obligations.', 'Use it to test excerpts, taxonomy links, and related-entry display.', 'Let the entry breathe with ordinary prose rather than repeated filler.' ),
		),
	);

	if ( $long ) {
		$sections[] = array(
			'heading'    => 'Signs and Witnesses',
			'paragraphs' => array( 'Several witnesses disagree on dates, but agree on the unease. The most reliable account was written on a wax tablet and copied twice before anyone noticed the second hand in the margin.' ),
		);
		$sections[] = array(
			'level'      => 3,
			'heading'    => 'A Smaller Detail',
			'paragraphs' => array( 'The smaller detail is often the one players remember: a wet footprint, a silver thread, a monk refusing to cross a threshold.' ),
		);
		$sections[] = array(
			'level'      => 4,
			'heading'    => 'Marginal Note',
			'paragraphs' => array( 'This fourth-level heading exists to exercise the automatic wiki table of contents depth control.' ),
			'table'      => array(
				'headers' => array( 'Season', 'Sign', 'Likely Concern' ),
				'rows'    => array(
					array( 'Spring', 'Unseasonal blossom', 'Creo or faerie influence' ),
					array( 'Summer', 'Silent animal', 'Boundary omen' ),
					array( 'Winter', 'Echo under stone', 'Buried promise' ),
				),
			),
		);
	}

	return array(
		'key'                  => $key,
		'title'                => $title,
		'slug'                 => $slug,
		'excerpt'              => $summary,
		'intro'                => array( $summary ),
		'sections'             => $sections,
		'related_characters'   => $characters,
		'related_entries'      => array(),
		'related_places'       => array(),
		'storyguide_notes'     => $long ? '<p>Private demo note: this entry has an unresolved secret, but the note must remain visible only to Storyguides and Administrators.</p>' : '',
		'meta'                 => array(
			'ligatura_entry_type'          => $entry_type,
			'ligatura_entry_status'        => 'Active',
			'ligatura_in_world_date'       => 'Anno Domini 1204',
			'ligatura_public_summary'      => $summary,
		),
		'terms'                => array(
			'ligatura_entry_type'     => array( $entry_type ),
			'ligatura_saga_topic'     => $topics,
			'ligatura_hermetic_house' => str_contains( $title, 'Jerbiton' ) ? array( 'Jerbiton' ) : array(),
		),
	);
}

/**
 * Demo covenant records.
 *
 * @return array<int,array<string,mixed>>
 */
function demo_covenant_records(): array {
	return array(
		covenant_item( 'cov_great_hall', 'Great Hall of the Lower Ward', 'great-hall-lower-ward', 'A smoky hall for meals, judgments, and arguments that need witnesses.', '1204-06-10', 'Place', array( 'char_heloise' ), array(), array( 'wiki_covenant' ) ),
		covenant_item( 'cov_east_lab', 'East Tower Laboratory', 'east-tower-laboratory', 'A cramped laboratory with excellent dawn light and terrible winter drafts.', '1204-05-22', 'Place', array( 'char_aveline' ), array(), array( 'wiki_covenant' ) ),
		covenant_item( 'cov_library', 'Library of Damp Calfskin and Ash', 'library-damp-calfskin-ash', 'A modest library that smells of smoke, glue, and ambitious cataloging.', '1204-05-05', 'Artifact', array( 'char_guarin' ), array(), array( 'wiki_charter' ) ),
		covenant_item( 'cov_herb_garden', 'Herb Garden of the Moonlit Yarrow', 'herb-garden-moonlit-yarrow', 'Raised beds of medicinal herbs, kitchen greens, and suspiciously vigorous yarrow.', '1204-04-18', 'Place', array( 'char_ysabeau' ), array( 'wiki_yarrow' ), array() ),
		covenant_item( 'cov_guest_house', 'Guest House by the Old Lime Tree', 'guest-house-old-lime-tree', 'A guest house where Redcaps sleep lightly and companions hide wet boots.', '1204-03-28', 'Place', array( 'char_petrus' ), array( 'wiki_petit_andely' ), array() ),
		covenant_item( 'cov_gatehouse', 'Gatehouse Stores', 'gatehouse-stores', 'Practical stores of rope, nails, lamp oil, salted fish, and things Renaud refuses to label.', '1204-03-12', 'Place', array( 'char_renaud' ), array(), array( 'wiki_covenant' ) ),
		covenant_item( 'cov_vis_ledger', 'Vis Ledger and Wax Seal Chest', 'vis-ledger-wax-seal-chest', 'The record of gathered, owed, and disputed vis.', '1204-02-20', 'Artifact', array( 'char_aveline', 'char_guarin' ), array(), array( 'wiki_yarrow' ) ),
		covenant_item( 'cov_charter_chest', 'Charter Chest with Three Mismatched Keys', 'charter-chest-three-mismatched-keys', 'A lockable chest holding charters, copies, and letters too important to trust to memory.', '1204-01-30', 'Artifact', array( 'char_guarin' ), array(), array( 'wiki_charter' ) ),
	);
}

/**
 * Build a covenant record.
 */
function covenant_item( string $key, string $title, string $slug, string $summary, string $saga_date, string $entry_type, array $characters, array $places, array $entries ): array {
	return array(
		'key'     => $key,
		'title'   => $title,
		'slug'    => $slug,
		'excerpt' => $summary,
		'related_characters' => $characters,
		'related_places'     => $places,
		'related_entries'    => $entries,
		'intro'   => array( $summary ),
		'sections' => array(
			array(
				'heading'    => 'Current State',
				'paragraphs' => array( 'This record is intentionally practical, giving the covenant archive real inventory-like content to display.' ),
			),
			array(
				'heading' => 'Inspection Notes',
				'table'   => array(
					'headers' => array( 'Quality', 'Concern' ),
					'rows'    => array( array( 'Useful', 'Needs seasonal attention' ), array( 'Watched', 'Arguments likely' ) ),
				),
			),
		),
		'meta'    => array(
			'ligatura_entry_type'          => $entry_type,
			'ligatura_entry_status'        => 'Active',
			'ligatura_covenant_saga_date'  => $saga_date,
			'ligatura_public_summary'      => '<p>' . esc_html( $summary ) . '</p>',
		),
		'terms'   => array(
			'ligatura_entry_type' => array( $entry_type ),
			'ligatura_saga_topic' => array( 'Covenant Affairs' ),
		),
	);
}

/**
 * Demo diary entries.
 *
 * @return array<int,array<string,mixed>>
 */
function demo_diaries(): array {
	return array(
		diary_item( 'diary_aveline_spring', 'Aveline Notes the Spring Correspondence', 'aveline-notes-spring-correspondence', 'char_aveline', '2026-08-24', '1204-03-14', 'Three letters arrived with different seals and the same omission.' ),
		diary_item( 'diary_renaud_gate', 'Renaud Counts the Gatehouse Keys', 'renaud-counts-gatehouse-keys', 'char_renaud', '2026-08-19', '1204-03-15', 'There are still seven keys, though one now opens nothing I can find.' ),
		diary_item( 'diary_odo_shrine', 'Brother Odo Copies the Thorn Prayer', 'brother-odo-copies-thorn-prayer', 'char_odo', '2026-08-21', '1204-03-15', 'The prayer is orthodox if read quickly and troubling if read aloud at the thorn.' ),
		diary_item( 'diary_colin_river', 'Colin Reports from the River Road', 'colin-reports-river-road', 'char_colin', '2026-08-26', '', 'Two barges waited below the bend, neither flying a mark I knew.' ),
		diary_item( 'diary_ysabeau_apple', 'Ysabeau Dreams of an Apple Made of Glass', 'ysabeau-dreams-apple-glass', 'char_ysabeau', '2026-08-23', '1204-04-30', 'The apple rang when it touched the table, and every sleeper in the guest house woke thirsty.' ),
		diary_item( 'diary_heloise_market', 'Heloise Settles Accounts at Petit Andely', 'heloise-settles-accounts-petit-andely', 'char_heloise', '2026-08-20', '1204-05-01', 'The onion seller apologized only after I named his cousin and his debt.' ),
		diary_item( 'diary_petrus_route', 'Petrus Quick-Sandal Lists Unsafe Bridges', 'petrus-lists-unsafe-bridges', 'char_petrus', '2026-08-25', '1204-05-03', 'One bridge is broken, one is watched, and one asked me my mother\'s name.' ),
		diary_item( 'diary_guarin_audit', 'Guarin of Tremere Begins a Courteous Audit of Every Locked Chest', 'guarin-begins-courteous-audit', 'char_guarin', '2026-08-22', '1204-06-12', 'Everyone is polite. This is never comforting.' ),
	);
}

/**
 * Build a diary entry.
 */
function diary_item( string $key, string $title, string $slug, string $character_key, string $publication_date, string $saga_date, string $summary ): array {
	return array(
		'key'               => $key,
		'title'             => $title,
		'slug'              => $slug,
		'post_date'         => $publication_date . ' 10:00:00',
		'excerpt'           => $summary,
		'related_character' => $character_key,
		'intro'             => array( $summary, 'The Journal voice is intentionally specific enough to test single-entry display without becoming official campaign canon.' ),
		'sections'          => array(
			array(
				'heading'    => 'Observation',
				'paragraphs' => array( 'The writer notices one concrete thing and one thing they do not yet understand. That is usually enough to make a Journal entry useful later.' ),
			),
		),
		'meta'              => array(
			JournalDates\META_KEY    => $saga_date,
			'ligatura_diary_session_date' => $publication_date,
		),
		'terms'             => array(
			'ligatura_saga_topic' => array( 'Covenant Affairs' ),
		),
	);
}

/**
 * WP-CLI command for demo content on non-production test installations.
 */
final class CLI_Command {
	/**
	 * Create demo content.
	 *
	 * ## OPTIONS
	 *
	 * [--replace]
	 * : Delete existing generated demo content before recreating it.
	 */
	public function create( array $args, array $assoc_args ): void {
		unset( $args );

		if ( ! is_safe_environment() ) {
			\WP_CLI::error( 'Demo content can only be managed in a non-production test environment.' );
		}

		$result = create_demo_content( ! empty( $assoc_args['replace'] ) );
		\WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT ) );
		\WP_CLI::success( 'Demo content create command finished.' );
	}

	/**
	 * Delete generated demo content.
	 */
	public function delete( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		if ( ! is_safe_environment() ) {
			\WP_CLI::error( 'Demo content can only be managed in a non-production test environment.' );
		}

		$result = delete_demo_content();
		\WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT ) );
		\WP_CLI::success( 'Demo content delete command finished.' );
	}

	/**
	 * Show generated demo content counts.
	 */
	public function status( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		\WP_CLI::line( wp_json_encode( count_demo_content(), JSON_PRETTY_PRINT ) );
	}
}
