<?php
/**
 * Global helper functions intentionally prefixed for theme consumption.
 *
 * @package LigaturaManuscriptiIlluminati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ligatura_can_view_storyguide_notes' ) ) {
	/**
	 * Determine whether a user can view Storyguide-only notes.
	 *
	 * @param int|null $user_id User ID.
	 */
	function ligatura_can_view_storyguide_notes( ?int $user_id = null ): bool {
		return \LigaturaManuscriptiIlluminati\PrivateNotes\can_view_storyguide_notes( $user_id );
	}
}

if ( ! function_exists( 'ligatura_get_storyguide_notes' ) ) {
	/**
	 * Get private Storyguide notes for an authorized viewer.
	 *
	 * @param int $post_id Wiki entry ID.
	 */
	function ligatura_get_storyguide_notes( int $post_id ): string {
		return \LigaturaManuscriptiIlluminati\PrivateNotes\get_storyguide_notes( $post_id );
	}
}

if ( ! function_exists( 'ligatura_render_storyguide_notes_panel' ) ) {
	/**
	 * Render private Storyguide note panel.
	 *
	 * @param int $post_id Wiki entry ID.
	 */
	function ligatura_render_storyguide_notes_panel( int $post_id ): string {
		return \LigaturaManuscriptiIlluminati\PrivateNotes\render_storyguide_notes_panel( $post_id );
	}
}

if ( ! function_exists( 'ligatura_get_author_nickname' ) ) {
	/**
	 * Return a privacy-safe author nickname.
	 *
	 * @param int $user_id User ID.
	 */
	function ligatura_get_author_nickname( int $user_id ): string {
		if ( $user_id < 1 ) {
			return '';
		}

		$nickname = get_user_meta( $user_id, 'nickname', true );
		$nickname = is_scalar( $nickname ) ? trim( sanitize_text_field( (string) $nickname ) ) : '';

		return $nickname;
	}
}

if ( ! function_exists( 'ligatura_get_post_author_nickname' ) ) {
	/**
	 * Return a post author's privacy-safe nickname.
	 *
	 * @param int $post_id Post ID.
	 */
	function ligatura_get_post_author_nickname( int $post_id ): string {
		return ligatura_get_author_nickname( (int) get_post_field( 'post_author', $post_id ) );
	}
}

if ( ! function_exists( 'ligatura_get_journal_saga_date' ) ) {
	/**
	 * Return a Journal's normalized Saga Date.
	 *
	 * @param int $post_id Journal ID.
	 */
	function ligatura_get_journal_saga_date( int $post_id ): string {
		return \LigaturaManuscriptiIlluminati\JournalDates\get_post_saga_date( $post_id );
	}
}

if ( ! function_exists( 'ligatura_get_journal_character_id' ) ) {
	/**
	 * Return the canonical in-world Character Author for a Journal.
	 */
	function ligatura_get_journal_character_id( int $post_id ): int {
		return \LigaturaManuscriptiIlluminati\JournalAuthorship\get_character_id( $post_id );
	}
}

if ( ! function_exists( 'ligatura_get_journal_character_name' ) ) {
	/**
	 * Return the visible in-world Journal author name without exposing its user owner.
	 */
	function ligatura_get_journal_character_name( int $post_id ): string {
		return \LigaturaManuscriptiIlluminati\JournalAuthorship\get_character_name( $post_id );
	}
}

if ( ! function_exists( 'ligatura_get_journal_character_image_id' ) ) {
	/**
	 * Return the Character Author's Campaign Image attachment ID.
	 */
	function ligatura_get_journal_character_image_id( int $post_id ): int {
		return \LigaturaManuscriptiIlluminati\JournalAuthorship\get_character_image_id( $post_id );
	}
}

if ( ! function_exists( 'ligatura_format_journal_saga_date' ) ) {
	/**
	 * Return a Journal's visitor-facing Saga Date.
	 *
	 * @param int $post_id Journal ID.
	 */
	function ligatura_format_journal_saga_date( int $post_id ): string {
		return \LigaturaManuscriptiIlluminati\JournalDates\format_post_saga_date( $post_id );
	}
}

if ( ! function_exists( 'ligatura_get_latest_journal_posts' ) ) {
	/**
	 * Return the latest N Journals by Saga Date, sorted newest-to-oldest for display.
	 *
	 * @param int $count Number of Journals.
	 * @return \WP_Post[]
	 */
	function ligatura_get_latest_journal_posts( int $count ): array {
		return \LigaturaManuscriptiIlluminati\JournalDates\latest_journal_posts( $count );
	}
}

if ( ! function_exists( 'ligatura_get_covenant_saga_date' ) ) {
	/**
	 * Return a Covenant Record's normalized Saga Date.
	 */
	function ligatura_get_covenant_saga_date( int $post_id ): string {
		return \LigaturaManuscriptiIlluminati\CovenantDirectory\get_post_saga_date( $post_id );
	}
}

if ( ! function_exists( 'ligatura_format_covenant_saga_date' ) ) {
	/**
	 * Return a Covenant Record's visitor-facing Saga Date.
	 */
	function ligatura_format_covenant_saga_date( int $post_id ): string {
		return \LigaturaManuscriptiIlluminati\CovenantDirectory\format_post_saga_date( $post_id );
	}
}

if ( ! function_exists( 'ligatura_get_campaign_content_directory_url' ) ) {
	/**
	 * Return the editable root Page URL for a first-class campaign content type.
	 */
	function ligatura_get_campaign_content_directory_url( string $post_type ): string {
		return \LigaturaManuscriptiIlluminati\PostTypes\get_directory_url( $post_type );
	}
}

if ( ! function_exists( 'ligatura_get_campaign_content_post_type_for_page' ) ) {
	/**
	 * Return the campaign post type represented by an editable directory Page.
	 */
	function ligatura_get_campaign_content_post_type_for_page( int $post_id ): string {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type ) {
			return '';
		}

		return \LigaturaManuscriptiIlluminati\PostTypes\get_post_type_for_permalink_root( $post->post_name );
	}
}

if ( ! function_exists( 'ligatura_render_character_card' ) ) {
	/**
	 * Render a reusable character card.
	 *
	 * @param int $post_id Character ID.
	 */
	function ligatura_render_character_card( int $post_id ): string {
		if ( 'ligatura_character' !== get_post_type( $post_id ) || ! current_user_can( 'read_post', $post_id ) ) {
			return '';
		}

		$type        = get_post_meta( $post_id, 'ligatura_character_type', true );
		$house       = get_post_meta( $post_id, 'ligatura_house', true );
		$description = get_post_meta( $post_id, 'ligatura_brief_description', true );

		ob_start();
		?>
		<article class="manuscriptum-illuminatum-card manuscriptum-illuminatum-card--character">
			<a class="manuscriptum-illuminatum-card__image" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s', 'ligatura-manuscripti-illuminati' ), get_the_title( $post_id ) ) ); ?>">
				<?php
				if ( has_post_thumbnail( $post_id ) ) {
					echo get_the_post_thumbnail( $post_id, 'ligatura_entry_card', array( 'loading' => 'lazy' ) );
				}
				?>
			</a>
			<div class="manuscriptum-illuminatum-card__body">
				<p class="manuscriptum-illuminatum-card__meta"><?php echo esc_html( trim( $type . ( $house ? ' / ' . $house : '' ) ) ); ?></p>
				<h3><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></h3>
				<?php if ( $description ) : ?>
					<p><?php echo esc_html( wp_trim_words( (string) $description, 28 ) ); ?></p>
				<?php else : ?>
					<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $post_id ), 28 ) ); ?></p>
				<?php endif; ?>
			</div>
		</article>
		<?php

		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'ligatura_render_wiki_card' ) ) {
	/**
	 * Render a reusable wiki card.
	 *
	 * @param int $post_id Wiki entry ID.
	 */
	function ligatura_render_wiki_card( int $post_id ): string {
		if ( 'ligatura_wiki' !== get_post_type( $post_id ) || ! current_user_can( 'read_post', $post_id ) ) {
			return '';
		}

		$summary = get_post_meta( $post_id, 'ligatura_public_summary', true );
		$type    = get_post_meta( $post_id, 'ligatura_entry_type', true );

		ob_start();
		?>
		<article class="manuscriptum-illuminatum-card manuscriptum-illuminatum-card--wiki">
			<a class="manuscriptum-illuminatum-card__image" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s', 'ligatura-manuscripti-illuminati' ), get_the_title( $post_id ) ) ); ?>">
				<?php
				if ( has_post_thumbnail( $post_id ) ) {
					echo get_the_post_thumbnail( $post_id, 'ligatura_entry_card', array( 'loading' => 'lazy' ) );
				}
				?>
			</a>
			<div class="manuscriptum-illuminatum-card__body">
				<?php if ( $type ) : ?>
					<p class="manuscriptum-illuminatum-card__meta"><?php echo esc_html( (string) $type ); ?></p>
				<?php endif; ?>
				<h3><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></h3>
				<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $summary ? (string) $summary : get_the_excerpt( $post_id ) ), 30 ) ); ?></p>
			</div>
		</article>
		<?php

		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'ligatura_render_diary_card' ) ) {
	/**
	 * Render a reusable Journal card.
	 *
	 * @param int $post_id Journal ID.
	 */
	function ligatura_render_diary_card( int $post_id ): string {
		if ( 'ligatura_diary' !== get_post_type( $post_id ) || ! current_user_can( 'read_post', $post_id ) ) {
			return '';
		}

		$meta = array( ligatura_get_journal_character_name( $post_id ) );
		$date = ligatura_format_journal_saga_date( $post_id );

		if ( $date ) {
			$meta[] = sprintf(
				/* translators: %s: formatted Saga Date. */
				__( 'Saga Date: %s', 'ligatura-manuscripti-illuminati' ),
				$date
			);
		}

		ob_start();
		?>
		<article class="manuscriptum-illuminatum-card manuscriptum-illuminatum-card--diary" data-saga-date="<?php echo esc_attr( ligatura_get_journal_saga_date( $post_id ) ); ?>">
			<div class="manuscriptum-illuminatum-card__body">
				<p class="manuscriptum-illuminatum-card__meta"><?php echo esc_html( implode( ' / ', array_filter( $meta ) ) ); ?></p>
				<h3><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></h3>
				<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $post_id ), 28 ) ); ?></p>
			</div>
		</article>
		<?php

		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'ligatura_render_covenant_card' ) ) {
	/**
	 * Render a reusable covenant card.
	 *
	 * @param int $post_id Covenant record ID.
	 */
	function ligatura_render_covenant_card( int $post_id ): string {
		if ( 'ligatura_covenant' !== get_post_type( $post_id ) || ! current_user_can( 'read_post', $post_id ) ) {
			return '';
		}

		ob_start();
		?>
		<article class="manuscriptum-illuminatum-card manuscriptum-illuminatum-card--covenant">
			<a class="manuscriptum-illuminatum-card__image" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s', 'ligatura-manuscripti-illuminati' ), get_the_title( $post_id ) ) ); ?>">
				<?php
				if ( has_post_thumbnail( $post_id ) ) {
					echo get_the_post_thumbnail( $post_id, 'ligatura_entry_card', array( 'loading' => 'lazy' ) );
				}
				?>
			</a>
			<div class="manuscriptum-illuminatum-card__body">
				<h3><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></h3>
				<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $post_id ), 28 ) ); ?></p>
			</div>
		</article>
		<?php

		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'ligatura_get_related_entries' ) ) {
	/**
	 * Get related entry IDs from campaign meta fields.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	function ligatura_get_related_entries( int $post_id ): array {
		$keys = array( 'ligatura_related_characters', 'ligatura_related_places', 'ligatura_related_entries', 'ligatura_diary_character' );
		$ids  = array();

		foreach ( $keys as $key ) {
			$value = (string) get_post_meta( $post_id, $key, true );

			if ( '' === $value ) {
				continue;
			}

			foreach ( preg_split( '/[,\s]+/', $value ) as $raw_id ) {
				$id = absint( $raw_id );

				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}

		$ids = array_values( array_unique( $ids ) );

		usort(
			$ids,
			static function ( int $left_id, int $right_id ): int {
				$left_title  = get_the_title( $left_id );
				$right_title = get_the_title( $right_id );
				$left_key    = (string) preg_replace( '/^the\s+/iu', '', trim( wp_strip_all_tags( $left_title ) ) );
				$right_key   = (string) preg_replace( '/^the\s+/iu', '', trim( wp_strip_all_tags( $right_title ) ) );
				$result      = strnatcasecmp( $left_key, $right_key );

				if ( 0 === $result ) {
					$result = strnatcasecmp( $left_title, $right_title );
				}

				return 0 !== $result ? $result : $left_id <=> $right_id;
			}
		);

		return $ids;
	}
}
