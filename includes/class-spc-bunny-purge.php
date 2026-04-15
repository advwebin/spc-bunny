<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

class SPC_Bunny_Purge {

	/**
	 * Full Pull Zone purge with atomic deduplication lock.
	 *
	 * Uses wp_cache_add() for an atomic lock — maps to Memcached::add()
	 * or Redis SETNX. First writer wins; concurrent calls return early.
	 *
	 * @since 3.0.0
	 */
	public function purge_all(): bool {
		$locked = wp_cache_add( 'spc_bunny_purge_lock', 1, 'spc-bunny', 15 );
		if ( ! $locked ) {
			$this->log_result(
				new WP_Error( 'throttled', __( 'Purge deduplicated (another purge in progress).', 'spc-bunny' ) ),
				'full (deduplicated)'
			);
			return false;
		}

		$result = spc_bunny_api()->purge_all();
		$this->log_result( $result, 'full' );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		update_option( 'spc_bunny_last_purge', wp_date( 'Y-m-d H:i:s' ), false );
		( new SPC_Bunny_Stats() )->flush();
		SPC_Bunny_Warmer::schedule();
		SPC_Bunny_Perma_Cache::maybe_cleanup();

		return true;
	}

	/**
	 * Per-URL purge for a single post save.
	 *
	 * Purges: post permalink, homepage, primary category archive, primary tag archive.
	 * Does NOT fall back to full zone purge on failure — logs error and moves on.
	 * On rate limit, schedules retry via wp_schedule_single_event.
	 *
	 * @since 3.0.0
	 * @param int $post_id The post ID.
	 */
	public function purge_post( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return;
		}

		$urls = [ get_permalink( $post_id ), home_url( '/' ) ];

		$categories = get_the_category( $post_id );
		if ( ! empty( $categories ) ) {
			$urls[] = get_category_link( $categories[0]->term_id );
		}
		$tags = get_the_tags( $post_id );
		if ( ! empty( $tags ) ) {
			$urls[] = get_tag_link( $tags[0]->term_id );
		}

		/**
		 * Filter the list of URLs to purge when a post is saved.
		 *
		 * @since 3.0.0
		 * @param string[] $urls    URLs to purge (max ~4 by default).
		 * @param int      $post_id The post ID that triggered the purge.
		 * @param WP_Post  $post    The post object.
		 */
		$urls = apply_filters( 'spc_bunny_purge_urls_for_post', $urls, $post_id, $post );

		$results = spc_bunny_api()->purge_urls( array_values( array_unique( array_filter( $urls ) ) ) );

		$errors = array_filter( $results, 'is_wp_error' );
		$this->log_result(
			empty( $errors ) ? true : reset( $errors ),
			sprintf( 'per-url (post %d, %d URLs)', $post_id, count( $results ) )
		);

		foreach ( $results as $url => $result ) {
			if ( is_wp_error( $result ) && 'rate_limited' === $result->get_error_code() ) {
				$retry_after = $result->get_error_data()['retry_after'] ?? 5;
				wp_schedule_single_event( time() + $retry_after, 'spc_bunny_retry_purge_url', [ $url ] );
			}
		}
	}

	/**
	 * Log a purge result to the purge log option.
	 *
	 * @param true|WP_Error $result  The purge result.
	 * @param string        $context Description of the purge context.
	 */
	private function log_result( true|WP_Error $result, string $context ): void {
		$log = get_option( 'spc_bunny_purge_log', [] );
		array_unshift( $log, [
			'time'    => wp_date( 'Y-m-d H:i:s' ),
			'context' => $context,
			'success' => ! is_wp_error( $result ),
			'message' => is_wp_error( $result ) ? $result->get_error_message() : 'OK',
		] );
		update_option( 'spc_bunny_purge_log', array_slice( $log, 0, 20 ), false );
	}
}
