<?php
defined( 'ABSPATH' ) || exit;

/**
 * Manages Bunny Perma-Cache storage cleanup.
 *
 * When you do a full Pull Zone purge, Bunny does NOT delete the Perma-Cache files.
 * Instead it switches to a new directory inside the storage zone. Old directories
 * accumulate and cost money. This class lists the __bcdn_perma_cache__ directory
 * via the Storage API and deletes all folders except the newest (active) one.
 *
 * Storage API endpoints by region:
 *   Falkenstein (EU)  → storage.bunnycdn.com
 *   New York (US)     → ny.storage.bunnycdn.com
 *   Los Angeles       → la.storage.bunnycdn.com
 *   Singapore         → sg.storage.bunnycdn.com
 *   Sydney            → syd.storage.bunnycdn.com
 *   Stockholm         → se.storage.bunnycdn.com
 *   São Paulo         → br.storage.bunnycdn.com
 *   Johannesburg      → jh.storage.bunnycdn.com
 */
class SPC_Bunny_Perma_Cache {

    // Storage region hostnames
    public const REGIONS = [
        'de'  => 'storage.bunnycdn.com',
        'ny'  => 'ny.storage.bunnycdn.com',
        'la'  => 'la.storage.bunnycdn.com',
        'sg'  => 'sg.storage.bunnycdn.com',
        'syd' => 'syd.storage.bunnycdn.com',
        'se'  => 'se.storage.bunnycdn.com',
        'br'  => 'br.storage.bunnycdn.com',
        'jh'  => 'jh.storage.bunnycdn.com',
    ];

    private string $zone_name;
    private string $zone_password;
    private string $region;

    public function __construct( string $zone_name, string $zone_password, string $region = 'de' ) {
        $this->zone_name     = $zone_name;
        $this->zone_password = $zone_password;
        $this->region        = $region;
    }

    private function host(): string {
        return self::REGIONS[ $this->region ] ?? self::REGIONS['de'];
    }

    private function base_url(): string {
        return 'https://' . $this->host() . '/' . $this->zone_name . '/';
    }

    private function request( string $method, string $url ): array|WP_Error {
        $response = wp_remote_request( $url, [
            'method'  => $method,
            'timeout' => 15,
            'headers' => [
                'AccessKey' => $this->zone_password,
                'Accept'    => 'application/json',
            ],
        ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'storage_error', "Storage API HTTP {$code}: {$body}" );
        }
        return json_decode( $body, true ) ?? [];
    }

    /**
     * List all directories inside __bcdn_perma_cache__ for this zone.
     * Returns array of directory objects from the Storage API, sorted by
     * DateCreated ascending (oldest first).
     */
    public function list_perma_cache_dirs(): array|WP_Error {
        $url    = $this->base_url() . '__bcdn_perma_cache__/';
        $result = $this->request( 'GET', $url );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( ! is_array( $result ) ) {
            return [];
        }
        // Filter to directories only
        $dirs = array_filter( $result, fn( $item ) => ! empty( $item['IsDirectory'] ) );
        // Sort by DateCreated ascending so newest is last
        usort( $dirs, fn( $a, $b ) => strcmp( $a['DateCreated'] ?? '', $b['DateCreated'] ?? '' ) );
        return array_values( $dirs );
    }

    /**
     * Delete a specific directory from the storage zone.
     * Bunny deletes directories recursively.
     *
     * @param string $object_name The directory ObjectName from the API (e.g. pullzone__myzone__123)
     */
    public function delete_dir( string $object_name ): bool|WP_Error {
        $url = $this->base_url() . '__bcdn_perma_cache__/' . ltrim( $object_name, '/' ) . '/';
        $result = $this->request( 'DELETE', $url );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return true;
    }

    /**
     * Clean up old Perma-Cache directories — keep the newest N, delete the rest.
     * Default keep=1 (only the current active directory).
     *
     * @param int $keep Number of newest directories to keep. Default 1.
     * @return array Results: ['deleted' => [...], 'kept' => [...], 'errors' => [...]]
     */
    public function cleanup( int $keep = 1 ): array {
        $results = [ 'deleted' => [], 'kept' => [], 'errors' => [] ];
        $dirs    = $this->list_perma_cache_dirs();

        if ( is_wp_error( $dirs ) ) {
            $results['errors'][] = $dirs->get_error_message();
            return $results;
        }

        if ( empty( $dirs ) ) {
            return $results;
        }

        $total    = count( $dirs );
        $keep     = max( 1, $keep );

        // Newest N dirs to keep (they're sorted ascending so take from the end)
        $to_keep   = array_slice( $dirs, -$keep );
        $to_delete = array_slice( $dirs, 0, max( 0, $total - $keep ) );

        foreach ( $to_keep as $dir ) {
            $results['kept'][] = $dir['ObjectName'] ?? '';
        }

        foreach ( $to_delete as $dir ) {
            $name   = $dir['ObjectName'] ?? '';
            $delete = $this->delete_dir( $name );
            if ( is_wp_error( $delete ) ) {
                $results['errors'][] = "Failed to delete {$name}: " . $delete->get_error_message();
            } else {
                $results['deleted'][] = $name;
            }
        }

        return $results;
    }

    /**
     * Test connectivity — list the perma cache dir and return count of folders found.
     */
    public function test_connection(): array|WP_Error {
        $dirs = $this->list_perma_cache_dirs();
        if ( is_wp_error( $dirs ) ) {
            return $dirs;
        }
        return [
            'folders_found' => count( $dirs ),
            'folders'       => array_map( fn( $d ) => $d['ObjectName'] ?? '', $dirs ),
        ];
    }

    public static function is_configured(): bool {
        $opts = get_option( 'spc_bunny_settings', [] );
        return ! empty( $opts['perma_cache_enabled'] )
            && ! empty( $opts['perma_cache_zone_name'] )
            && ! empty( $opts['perma_cache_zone_password'] );
    }

    public static function from_settings(): ?self {
        if ( ! self::is_configured() ) {
            return null;
        }
        $opts = get_option( 'spc_bunny_settings', [] );
        return new self(
            (string) $opts['perma_cache_zone_name'],
            (string) $opts['perma_cache_zone_password'],
            (string) ( $opts['perma_cache_region'] ?? 'de' )
        );
    }

    /**
     * Run cleanup after a full purge if Perma-Cache is configured.
     * Called by SPC_Bunny_Purge::purge_all().
     */
    public static function maybe_cleanup(): void {
        $instance = self::from_settings();
        if ( ! $instance ) {
            return;
        }
        $opts = get_option( 'spc_bunny_settings', [] );
        $keep = max( 1, (int) ( $opts['perma_cache_keep'] ?? 1 ) );
        $results = $instance->cleanup( $keep );

        // Log results
        $log = get_option( 'spc_bunny_purge_log', [] );
        $msg = sprintf(
            'Perma-Cache cleanup: deleted %d folder(s), kept %d. %s',
            count( $results['deleted'] ),
            count( $results['kept'] ),
            empty( $results['errors'] ) ? '' : 'Errors: ' . implode( '; ', $results['errors'] )
        );
        array_unshift( $log, [
            'time'    => current_time( 'mysql' ),
            'context' => 'perma-cache-cleanup',
            'success' => empty( $results['errors'] ),
            'message' => trim( $msg ),
        ] );
        update_option( 'spc_bunny_purge_log', array_slice( $log, 0, 20 ), false );
    }
}
