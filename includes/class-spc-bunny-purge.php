<?php
defined( 'ABSPATH' ) || exit;

class SPC_Bunny_Purge {

    private SPC_Bunny_API $api;

    public function __construct() {
        $this->api = new SPC_Bunny_API();
    }

    /**
     * Full Pull Zone purge — used for all events.
     * Bunny per-URL purge requires exact URL + variant matching which is unreliable.
     * A full zone purge is instant and guarantees a clean slate.
     */
    public function purge_all(): void {
        $result = $this->api->purge_all();
        $this->log_result( $result, 'full' );
        if ( ! is_wp_error( $result ) ) {
            update_option( 'spc_bunny_last_purge', current_time( 'mysql' ), false );
        }
        ( new SPC_Bunny_Stats() )->flush();
        SPC_Bunny_Warmer::schedule();
        SPC_Bunny_Perma_Cache::maybe_cleanup();
    }

    /**
     * Called on post save/update — always does a full purge.
     * Per-URL purge was unreliable due to URL variant mismatches (www/non-www,
     * trailing slash, query strings). Full purge ensures the updated page is
     * always fresh at the edge.
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

    private function log_result( mixed $result, string $context ): void {
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
