<?php
declare( strict_types=1 );
defined( 'ABSPATH' ) || exit;

class SPC_Bunny_Warmer {

    private const CRON_HOOK = 'spc_bunny_warm_batch';
    private const QUEUE_OPT = 'spc_bunny_warm_queue';

    private const DEFAULT_BATCH = 5;
    private const DEFAULT_DELAY = 30;

    public static function register_hooks(): void {
        add_action( self::CRON_HOOK, [ static::class, 'process_batch' ] );
    }

    public static function schedule( int $delay = 60 ): void {
        $opts = get_option( 'spc_bunny_settings', [] );
        if ( empty( $opts['enable_warmer'] ) ) {
            return;
        }

        // Store flag — do NOT fetch sitemaps here (deferred to cron).
        update_option( 'spc_bunny_warm_pending', true, false );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + $delay, 'spc_bunny_warm_interval', self::CRON_HOOK );
        }
    }

    public static function process_batch(): void {
        // Atomic overlap prevention — only one batch runs at a time.
        if ( ! wp_cache_add( 'spc_bunny_warming', 1, 'spc-bunny', 120 ) ) {
            return;
        }

        $queue = get_option( self::QUEUE_OPT, [] );

        // First run after purge: expand URLs from sitemap.
        if ( empty( $queue ) && get_option( 'spc_bunny_warm_pending' ) ) {
            $queue = self::collect_urls();
            update_option( self::QUEUE_OPT, $queue, false );
            delete_option( 'spc_bunny_warm_pending' );
        }

        if ( empty( $queue ) ) {
            wp_clear_scheduled_hook( self::CRON_HOOK );
            wp_cache_delete( 'spc_bunny_warming', 'spc-bunny' );
            return;
        }

        $opts       = get_option( 'spc_bunny_settings', [] );
        $batch_size = max( 1, min( 50, (int) ( $opts['warmer_batch_size'] ?? self::DEFAULT_BATCH ) ) );

        $batch = array_splice( $queue, 0, $batch_size );
        update_option( self::QUEUE_OPT, $queue, false );

        foreach ( $batch as $url ) {
            wp_remote_get( $url, [
                'timeout'    => 10,
                'blocking'   => false,
                'user-agent' => 'SPC-Bunny-Warmer/' . SPC_BUNNY_VERSION,
                'sslverify'  => true,
            ] );
        }

        // Clear overlap lock.
        wp_cache_delete( 'spc_bunny_warming', 'spc-bunny' );

        // If queue drained, stop recurring schedule.
        if ( empty( $queue ) ) {
            wp_clear_scheduled_hook( self::CRON_HOOK );
        }
    }

    public static function get_queue_count(): int {
        return count( get_option( self::QUEUE_OPT, [] ) );
    }

    public static function is_warming(): bool {
        return (bool) wp_next_scheduled( self::CRON_HOOK );
    }

    public static function cancel(): void {
        $next = wp_next_scheduled( self::CRON_HOOK );
        if ( $next ) {
            wp_unschedule_event( $next, self::CRON_HOOK );
        }
        wp_clear_scheduled_hook( self::CRON_HOOK );
        delete_option( self::QUEUE_OPT );
        delete_option( 'spc_bunny_warm_pending' );
    }

    /**
     * Detect the sitemap URL for whichever SEO plugin is active.
     * Returns an empty string if nothing is found.
     */
    public static function detect_sitemap_url(): string {

        // Yoast SEO — free and premium
        if ( defined( 'WPSEO_VERSION' ) ) {
            return home_url( '/sitemap_index.xml' );
        }

        // Rank Math
        if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
            return home_url( '/sitemap_index.xml' );
        }

        // All in One SEO (AIOSEO)
        if ( class_exists( 'AIOSEO\Plugin\AIOSEO' ) || defined( 'AIOSEO_VERSION' ) ) {
            return home_url( '/sitemap.xml' );
        }

        // SEOPress — free and pro
        if ( defined( 'SEOPRESS_VERSION' ) || class_exists( 'SeoPress_Admin_Init' ) ) {
            return home_url( '/seopress-sitemap.xml' );
        }

        // SlimSEO
        if ( class_exists( 'SlimSEO\Plugin' ) || defined( 'SLIM_SEO_VER' ) ) {
            return home_url( '/sitemap.xml' );
        }

        // The SEO Framework
        if ( class_exists( 'The_SEO_Framework\Load' ) || function_exists( 'the_seo_framework' ) ) {
            return home_url( '/sitemap.xml' );
        }

        // Squirrly SEO
        if ( defined( 'SQ_VERSION' ) ) {
            return home_url( '/sitemap.xml' );
        }

        // Math Rank (separate check)
        if ( defined( 'MATHRANK_VERSION' ) ) {
            return home_url( '/sitemap_index.xml' );
        }

        // WP core sitemap (WordPress 5.5+ built-in, no SEO plugin required)
        if ( function_exists( 'wp_sitemaps_get_server' ) ) {
            return home_url( '/wp-sitemap.xml' );
        }

        return '';
    }

    public static function collect_urls(): array {
        $urls = [];

        // 1. Try the detected SEO plugin sitemap first
        $detected = self::detect_sitemap_url();
        if ( $detected ) {
            $urls = self::parse_sitemap( $detected );
        }

        // 2. If that didn't work, try all known sitemap paths as fallback
        if ( empty( $urls ) ) {
            $candidates = [
                home_url( '/sitemap_index.xml' ),  // Yoast, Rank Math
                home_url( '/sitemap.xml' ),         // AIOSEO, SlimSEO, The SEO Framework, WP core
                home_url( '/wp-sitemap.xml' ),      // WP core (explicit)
                home_url( '/seopress-sitemap.xml' ),// SEOPress
            ];
            // Deduplicate (detected URL may already be in the list)
            if ( $detected ) {
                $candidates = array_diff( $candidates, [ $detected ] );
            }
            foreach ( $candidates as $sm ) {
                $parsed = self::parse_sitemap( $sm );
                if ( ! empty( $parsed ) ) {
                    $urls = $parsed;
                    break;
                }
            }
        }

        // 3. Ultimate fallback — homepage + recent 50 posts
        if ( empty( $urls ) ) {
            $urls[] = home_url( '/' );
            $posts  = get_posts( [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 50,
                'fields'         => 'ids',
            ] );
            foreach ( $posts as $id ) {
                $url = get_permalink( $id );
                if ( $url ) {
                    $urls[] = $url;
                }
            }
        }

        return array_values( array_unique( array_filter( $urls ) ) );
    }

    /**
     * Validate a URL belongs to this site (SSRF protection).
     *
     * Prevents sitemap injection attacks where attacker-controlled <loc>
     * entries could make the server fetch internal network resources.
     *
     * @since 3.0.0
     * @param string $url URL to validate.
     * @return bool
     */
    private static function is_allowed_url( string $url ): bool {
        $parsed = wp_parse_url( $url );
        if ( ! in_array( $parsed['scheme'] ?? '', [ 'http', 'https' ], true ) ) {
            return false;
        }
        $home_host = wp_parse_url( home_url(), PHP_URL_HOST );
        return strtolower( $parsed['host'] ?? '' ) === strtolower( $home_host ?? '' );
    }

    private static function parse_sitemap( string $url, bool $is_index = true, int $depth = 0 ): array {
        if ( $depth > 2 ) { return []; }
        $response = wp_remote_get( $url, [
            'timeout'    => 10,
            'user-agent' => 'SPC-Bunny-Warmer/1.0',
            'sslverify'  => true,
        ] );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [];
        }
        $prev_errors = libxml_use_internal_errors( true );
        $xml = simplexml_load_string( wp_remote_retrieve_body( $response ) );
        libxml_clear_errors();
        libxml_use_internal_errors( $prev_errors );
        if ( ! $xml ) {
            return [];
        }
        $urls = [];
        // Sitemap index — recurse into child sitemaps
        if ( $is_index && isset( $xml->sitemap ) ) {
            foreach ( $xml->sitemap as $child ) {
                $child_url = (string) ( $child->loc ?? '' );
                if ( $child_url && self::is_allowed_url( $child_url ) ) {
                    $urls = array_merge( $urls, self::parse_sitemap( $child_url, false, $depth + 1 ) );
                    if ( count( $urls ) >= 500 ) {
                        break;
                    }
                }
            }
        }
        // Regular sitemap — collect <loc> entries
        if ( isset( $xml->url ) ) {
            foreach ( $xml->url as $entry ) {
                $loc = (string) ( $entry->loc ?? '' );
                if ( $loc && self::is_allowed_url( $loc ) ) {
                    $urls[] = $loc;
                }
            }
        }
        return array_slice( $urls, 0, 500 );
    }
}
