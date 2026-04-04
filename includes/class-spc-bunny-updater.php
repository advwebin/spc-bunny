<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// Load the WP_GitHub_Updater library
require_once SPC_BUNNY_DIR . 'includes/class-wp-github-updater.php';

/**
 * Initialise GitHub auto-updates via WP_GitHub_Updater.
 *
 * Version detection: reads the Version header from the raw plugin file on the
 * main branch — no GitHub Release required. Just push to main and bump the
 * version header; WordPress will see the update within 6 hours.
 *
 * To use a private repo, add an 'access_token' key to the config array.
 */
function spc_bunny_init_updater(): void {
    if ( ! is_admin() ) {
        return;
    }
    new WP_GitHub_Updater( [
        'slug'                => 'spc-bunny-connector/spc-bunny-connector.php',
        'proper_folder_name'  => 'spc-bunny-connector',
        'api_url'             => 'https://api.github.com/repos/jaimealnassim/SPC-Bunny',
        'raw_url'             => 'https://raw.githubusercontent.com/jaimealnassim/SPC-Bunny/main',
        'github_url'          => 'https://github.com/jaimealnassim/SPC-Bunny',
        'zip_url'             => 'https://github.com/jaimealnassim/SPC-Bunny/archive/refs/heads/main.zip',
        'requires'            => '6.0',
        'tested'              => '6.7',
        'readme'              => 'README.md',
        'access_token'        => '',
    ] );
}
add_action( 'init', 'spc_bunny_init_updater' );
