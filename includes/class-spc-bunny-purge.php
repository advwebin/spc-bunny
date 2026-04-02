<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

class SPC_Bunny_Purge {

    private SPC_Bunny_API $api;

    public function __construct() {
        $this->api = new SPC_Bunny_API();
    }

    /**
     * Full Pull Zone purge.
     * Only runs warmer and Perma-Cache cleanup on success.
     */
    public function purge_all(): void {
        $result = $this->api->purge_all();
        $this->log_result( $result, 'full' );

        if ( is_wp_error( $result ) ) {
            return;
        }

        update_option( 'spc_bunny_last_purge', current_time( 'mysql' ), false );
        ( new SPC_Bunny_Stats() )->flush();
        SPC_Bunny_Warmer::schedule();
        SPC_Bunny_Perma_Cache::maybe_cleanup();
    }

    /**
     * Purge triggered by a post save. Guards against revisions, autosaves,
     * and non-published posts before calling purge_all().
     */
    public function purge_post( int $post_id ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post || $post->post_status !== 'publish' ) {
            return;
        }
        $this->purge_all();
    }

    private function log_result( true|WP_Error $result, string $context ): void {
        $log = get_option( 'spc_bunny_purge_log', [] );
        array_unshift( $log, [
            'time'    => current_time( 'mysql' ),
            'context' => $context,
            'success' => ! is_wp_error( $result ),
            'message' => is_wp_error( $result ) ? $result->get_error_message() : 'OK',
        ] );
        update_option( 'spc_bunny_purge_log', array_slice( $log, 0, 20 ), false );
    }
}
