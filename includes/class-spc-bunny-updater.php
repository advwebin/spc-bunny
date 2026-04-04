<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * GitHub Updater for SPC Bunny Connector.
 *
 * Hooks into the WordPress plugin update system to check GitHub Releases
 * for a newer version tag. When found, WP's native "update available" UI
 * and one-click updater work exactly as they do for wordpress.org plugins.
 *
 * Release convention:
 *   - Create a GitHub Release tagged  v2.0.0  (semver, v-prefix).
 *   - Attach the plugin zip as a release asset named spc-bunny-connector.zip.
 *   - If no asset is attached, falls back to GitHub's auto-generated source zip.
 *
 * No API token is required for a public repository. GitHub's unauthenticated
 * REST API allows 60 requests/hour per IP, and WordPress caches the transient
 * for 12 hours, so in practice this uses ≈2 requests/day per site.
 */
class SPC_Bunny_Updater {

	private const GITHUB_USER = 'jaimealnassim';
	private const GITHUB_REPO = 'SPC-Bunny';
	private const PLUGIN_SLUG = 'spc-bunny-connector/spc-bunny-connector.php';
	private const TRANSIENT   = 'spc_bunny_github_release';
	private const CACHE_TTL   = 43200; // 12 hours

	public static function init(): void {
		$instance = new self();
		add_filter( 'pre_set_site_transient_update_plugins', [ $instance, 'check_for_update' ] );
		add_filter( 'plugins_api',                          [ $instance, 'plugin_info' ], 10, 3 );
		add_filter( 'upgrader_source_selection',            [ $instance, 'fix_folder_name' ], 10, 4 );
	}

	// ── Public filter callbacks ───────────────────────────────────────────────

	/**
	 * Inject update data into WordPress's plugin update transient.
	 *
	 * @param  object $transient The existing update_plugins transient value.
	 * @return object
	 */
	public function check_for_update( object $transient ): object {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = ltrim( $release['tag_name'] ?? '', 'v' );
		$local_version  = SPC_BUNNY_VERSION;

		if ( version_compare( $remote_version, $local_version, '>' ) ) {
			$transient->response[ self::PLUGIN_SLUG ] = (object) [
				'slug'        => 'spc-bunny-connector',
				'plugin'      => self::PLUGIN_SLUG,
				'new_version' => $remote_version,
				'url'         => $release['html_url'] ?? '',
				'package'     => $this->get_download_url( $release ),
				'icons'       => [],
				'banners'     => [],
				'tested'      => get_bloginfo( 'version' ),
				'requires_php'=> '8.1',
			];
		} else {
			// Mark as checked with no update (prevents repeated API hits)
			$transient->no_update[ self::PLUGIN_SLUG ] = (object) [
				'slug'        => 'spc-bunny-connector',
				'plugin'      => self::PLUGIN_SLUG,
				'new_version' => $local_version,
				'url'         => '',
				'package'     => '',
			];
		}

		return $transient;
	}

	/**
	 * Provide plugin information for the "View version x.x.x details" modal.
	 *
	 * @param  false|object|array $result Default result.
	 * @param  string             $action The requested action.
	 * @param  object             $args   Plugin API arguments.
	 * @return false|object
	 */
	public function plugin_info( $result, string $action, object $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}
		if ( ! isset( $args->slug ) || $args->slug !== 'spc-bunny-connector' ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) [
			'name'          => 'SPC Bunny Connector',
			'slug'          => 'spc-bunny-connector',
			'version'       => ltrim( $release['tag_name'] ?? '', 'v' ),
			'author'        => '<a href="https://nahnumedia.com">Nahnu Media</a>',
			'homepage'      => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
			'requires'      => '6.0',
			'requires_php'  => '8.1',
			'tested'        => get_bloginfo( 'version' ),
			'last_updated'  => $release['published_at'] ?? '',
			'sections'      => [
				'description' => $release['body'] ?? 'See GitHub for release notes.',
				'changelog'   => $release['body'] ?? '',
			],
			'download_link' => $this->get_download_url( $release ),
		];
	}

	/**
	 * GitHub's auto-generated source zip unpacks to  repo-tag/  not the expected
	 * plugin folder name. Rename it so WordPress installs to the right place.
	 *
	 * @param  string      $source        Extracted source directory.
	 * @param  string      $remote_source Temp directory path.
	 * @param  WP_Upgrader $upgrader      The upgrader instance.
	 * @param  array       $hook_extra    Extra args (contains 'plugin' for plugin updates).
	 * @return string
	 */
	public function fix_folder_name( string $source, string $remote_source, WP_Upgrader $upgrader, array $hook_extra ): string {
		if (
			! isset( $hook_extra['plugin'] ) ||
			$hook_extra['plugin'] !== self::PLUGIN_SLUG
		) {
			return $source;
		}

		$correct = trailingslashit( $remote_source ) . 'spc-bunny-connector/';

		if ( $source !== $correct && is_dir( $source ) ) {
			global $wp_filesystem;
			if ( $wp_filesystem && $wp_filesystem->move( $source, $correct ) ) {
				return $correct;
			}
		}

		return $source;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Fetch (and cache) the latest GitHub release object.
	 *
	 * @return array|null Decoded release array, or null on failure.
	 */
	private function get_latest_release(): ?array {
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url      = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			self::GITHUB_USER,
			self::GITHUB_REPO
		);
		$response = wp_remote_get( $url, [
			'timeout' => 10,
			'headers' => [
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			],
		] );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			// Cache a short negative result to avoid hammering GitHub on repeated failures
			set_transient( self::TRANSIENT, [], 300 );
			return null;
		}

		$body    = wp_remote_retrieve_body( $response );
		$release = json_decode( $body, true );

		if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
			set_transient( self::TRANSIENT, [], 300 );
			return null;
		}

		set_transient( self::TRANSIENT, $release, self::CACHE_TTL );
		return $release;
	}

	/**
	 * Return the download URL for the release.
	 *
	 * Prefers a release asset named spc-bunny-connector.zip (so the zip
	 * unpacks with the correct folder name). Falls back to GitHub's
	 * auto-generated zipball, which unpacks to  repo-tag/  and is
	 * corrected by fix_folder_name().
	 *
	 * @param  array $release GitHub release array.
	 * @return string
	 */
	private function get_download_url( array $release ): string {
		foreach ( $release['assets'] ?? [] as $asset ) {
			if (
				isset( $asset['name'], $asset['browser_download_url'] ) &&
				$asset['name'] === 'spc-bunny-connector.zip' &&
				! empty( $asset['browser_download_url'] )
			) {
				return $asset['browser_download_url'];
			}
		}
		// Fallback: GitHub auto-generated source zip
		return $release['zipball_url'] ?? '';
	}
}
