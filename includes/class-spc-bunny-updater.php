<?php
declare( strict_types=1 );
defined( 'ABSPATH' ) || exit;

/**
 * GitHub auto-updates via YahnisElsts/plugin-update-checker.
 *
 * Replaces the abandoned jkudish/WordPress-GitHub-Plugin-Updater (v1.6, last commit 2020).
 * PUC uses Authorization header for private repos (not query-string tokens).
 *
 * @since 3.0.0
 * @link https://github.com/YahnisElsts/plugin-update-checker
 */

if ( ! is_admin() ) {
	return;
}

$puc_autoload = SPC_BUNNY_DIR . 'vendor/autoload.php';
if ( ! file_exists( $puc_autoload ) ) {
	return;
}
require_once $puc_autoload;

use YahnisElsts\PluginUpdateChecker\v5p6\PucFactory;

$spc_bunny_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/advwebin/spc-bunny',
	SPC_BUNNY_FILE,
	'spc-bunny-connector'
);

$spc_bunny_update_checker->setBranch( 'main' );

// For private repos, define SPC_BUNNY_GITHUB_TOKEN in wp-config.php.
if ( defined( 'SPC_BUNNY_GITHUB_TOKEN' ) ) {
	$spc_bunny_update_checker->setAuthentication( constant( 'SPC_BUNNY_GITHUB_TOKEN' ) );
}
