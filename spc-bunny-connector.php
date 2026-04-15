<?php
/**
 * Plugin Name: SPC Bunny Connector
 * Plugin URI:  https://nahnumedia.com
 * Description: Integrates Super Page Cache with Bunny.net CDN. Purges Bunny Pull Zone HTML cache on SPC events, deploys Edge Rules for full HTML caching, shows live CDN stats, warms cache after purges.
 * Version:     3.0.0
 * Author:      Nahnu Media
 * Author URI:  https://nahnumedia.com
 * License:     GPL-2.0+
 * Text Domain: spc-bunny
 * Requires PHP:  8.2
 * Requires at least: 6.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'SPC_BUNNY_VERSION', '3.0.0' );
define( 'SPC_BUNNY_FILE',    __FILE__ );
define( 'SPC_BUNNY_DIR',     plugin_dir_path( __FILE__ ) );
define( 'SPC_BUNNY_URL',     plugin_dir_url( __FILE__ ) );

require_once SPC_BUNNY_DIR . 'includes/class-spc-bunny-api.php';
require_once SPC_BUNNY_DIR . 'includes/class-spc-bunny-stats.php';
require_once SPC_BUNNY_DIR . 'includes/class-spc-bunny-purge.php';
require_once SPC_BUNNY_DIR . 'includes/class-spc-bunny-warmer.php';
require_once SPC_BUNNY_DIR . 'includes/class-spc-bunny-edge-rules.php';
require_once SPC_BUNNY_DIR . 'includes/class-spc-bunny-perma-cache.php';
require_once SPC_BUNNY_DIR . 'includes/class-spc-bunny-hooks.php';
require_once SPC_BUNNY_DIR . 'includes/class-spc-bunny-admin.php';
require_once SPC_BUNNY_DIR . 'includes/class-spc-bunny-updater.php';

/**
 * Return the shared SPC_Bunny_API instance.
 *
 * All internal code should call this instead of `new SPC_Bunny_API()`.
 * The constructor remains public for unit test instantiation with
 * explicit options arrays.
 *
 * @since 3.0.0
 * @return SPC_Bunny_API
 */
function spc_bunny_api(): SPC_Bunny_API {
	static $instance;
	return $instance ??= new SPC_Bunny_API();
}

SPC_Bunny_Warmer::register_hooks();

// Full purge — fires after SPC clears all cache.
add_action( 'swcfpc_purge_all', static function (): void {
	static $purge = null;
	$purge ??= new SPC_Bunny_Purge();
	if ( $purge->purge_all() ) {
		update_option( 'spc_bunny_spc_last_purge', wp_date( 'Y-m-d H:i:s' ), false );
	}
}, 20 );

// Per-URL purge — fires after SPC clears specific URLs.
add_action( 'swcfpc_purge_urls', static function ( array $urls ): void {
	spc_bunny_api()->purge_urls( $urls );
	update_option( 'spc_bunny_spc_last_purge', wp_date( 'Y-m-d H:i:s' ), false );
}, 20 );

// Retry hook for rate-limited per-URL purges.
add_action( 'spc_bunny_retry_purge_url', static function ( string $url ): void {
	spc_bunny_api()->purge_url( $url );
}, 10, 1 );

// Custom cron interval for cache warmer (fixed 30s, not dynamic).
add_filter( 'cron_schedules', static function ( array $schedules ): array {
	$schedules['spc_bunny_warm_interval'] = [
		'interval' => 30,
		'display'  => 'SPC Bunny Warmer Interval',
	];
	return $schedules;
} );

add_action( 'plugins_loaded', static function (): void {
	load_plugin_textdomain( 'spc-bunny', false, dirname( plugin_basename( SPC_BUNNY_FILE ) ) . '/languages' );
	new SPC_Bunny_Hooks();
	if ( is_admin() ) {
		new SPC_Bunny_Admin();
	}
} );

register_deactivation_hook( SPC_BUNNY_FILE, static function (): void {
	SPC_Bunny_Warmer::cancel();
	wp_clear_scheduled_hook( 'spc_bunny_cron_deploy' );
	wp_clear_scheduled_hook( 'spc_bunny_retry_purge_url' );
	delete_option( 'spc_bunny_warm_queue' );
	delete_option( 'spc_bunny_warm_pending' );
	delete_option( 'spc_bunny_deploy_params' );
	delete_option( 'spc_bunny_deploy_status' );
} );
