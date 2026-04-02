<?php
/**
 * Plugin Name: SPC Bunny Connector
 * Plugin URI:  https://nahnumedia.com
 * Description: Integrates Super Page Cache with Bunny.net CDN. Purges Bunny Pull Zone HTML cache on SPC events, deploys Edge Rules for full HTML caching, shows live CDN stats, warms cache after purges.
 * Version:     1.8.4
 * Author:      Nahnu Media
 * Author URI:  https://nahnumedia.com
 * License:     GPL-2.0+
 * Text Domain: spc-bunny
 */

defined( 'ABSPATH' ) || exit;

define( 'SPC_BUNNY_VERSION', '1.8.4' );
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

SPC_Bunny_Warmer::register_hooks();

/**
 * SPC purge hooks — registered at file scope to guarantee they are in place
 * regardless of plugin load order.
 *
 * IMPORTANT: swcfpc_cf_purge_whole_cache_after and swcfpc_cf_purge_cache_by_urls_after
 * only fire when Cloudflare is actively connected. Since this site uses Bunny CDN
 * instead of Cloudflare, those hooks never fire.
 *
 * The correct hooks from cache_controller.class.php are:
 *   swcfpc_purge_all  — line 754 — fires after every full cache purge
 *   swcfpc_purge_urls — line 860 — fires after per-URL purge, passes $urls array
 *
 * These fire unconditionally regardless of CDN provider.
 */
function spc_bunny_on_spc_purge_all(): void {
    $purge = new SPC_Bunny_Purge();
    $purge->purge_all();
    update_option( 'spc_bunny_spc_last_purge', current_time( 'mysql' ), false );
}

function spc_bunny_on_spc_purge_urls( $urls = [] ): void {
    // Always do a full purge — Bunny per-URL purge is unreliable due to URL variants
    $purge = new SPC_Bunny_Purge();
    $purge->purge_all();
    update_option( 'spc_bunny_spc_last_purge', current_time( 'mysql' ), false );
}

add_action( 'swcfpc_purge_all',  'spc_bunny_on_spc_purge_all',  20, 0 );
add_action( 'swcfpc_purge_urls', 'spc_bunny_on_spc_purge_urls', 20, 1 );

add_action( 'plugins_loaded', function (): void {
    new SPC_Bunny_Hooks();
    if ( is_admin() ) {
        new SPC_Bunny_Admin();
    }
} );
