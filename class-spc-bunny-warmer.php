<?php
defined( 'ABSPATH' ) || exit;

/**
 * GitHub auto-updates via WP_GitHub_Updater (radishconcepts/WordPress-GitHub-Plugin-Updater).
 *
 * How it works:
 *   - Fetches raw.githubusercontent.com/jaimealnassim/SPC-Bunny/main/spc-bunny-connector.php
 *   - Reads the Version: header from that file
 *   - If it is higher than the installed version, WordPress shows the update notice
 *   - Download uses the zipball of the main branch; upgrader_post_install renames the
 *     extracted folder to spc-bunny-connector/ so WordPress installs to the right place
 *
 * No GitHub Release needed — just bump the Version: header and push to main.
 */

require_once SPC_BUNNY_DIR . 'includes/class-wp-github-updater.php';

if ( is_admin() ) {
	new WP_GitHub_Updater( array(
		// Full plugin slug — used to locate the installed plugin file and as the
		// key in the update transient. Must match plugin_basename( __FILE__ ) exactly.
		'slug'               => 'spc-bunny-connector/spc-bunny-connector.php',

		// Folder name WordPress should install the plugin into.
		'proper_folder_name' => 'spc-bunny-connector',

		// GitHub API URL — used to fetch repo metadata (description, last updated).
		'api_url'            => 'https://api.github.com/repos/jaimealnassim/SPC-Bunny',

		// Raw file base URL — library appends /spc-bunny-connector.php to read Version:.
		'raw_url'            => 'https://raw.githubusercontent.com/jaimealnassim/SPC-Bunny/main',

		// GitHub repo URL — shown as the plugin homepage in the update screen.
		'github_url'         => 'https://github.com/jaimealnassim/SPC-Bunny',

		// Download URL — zipball of the main branch.
		'zip_url'            => 'https://github.com/jaimealnassim/SPC-Bunny/zipball/main',

		// WordPress version requirements shown in the update screen.
		'requires'           => '6.0',
		'tested'             => '6.7',

		// File the library falls back to for version detection if the plugin header fails.
		'readme'             => 'README.md',

		// Leave empty for public repos. Set to a GitHub PAT for private repos.
		'access_token'       => '',

		'sslverify'          => true,
	) );
}
