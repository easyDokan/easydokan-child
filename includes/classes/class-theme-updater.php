<?php
/**
 * Theme updater from GitHub
 */

if ( ! class_exists( 'Easydokan_Theme_Updater' ) ) {

	class Easydokan_Theme_Updater {

		/**
		 * GitHub repository owner/name.
		 */
		private string $repo = 'easyDokan/easydokan-child';

		/**
		 * Theme slug (directory name).
		 */
		private string $slug = 'easydokan-child';

		/**
		 * Transient key used to cache the latest release data.
		 */
		private string $transient_key = 'easydokan_theme_update_check';

		/**
		 * How long to cache the release check (in seconds). Default: 6 hours.
		 */
		private int $cache_ttl = 21600;

		public function __construct() {
			add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_for_update' ) );
			add_filter( 'themes_api', array( $this, 'theme_info' ), 20, 3 );
			add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
		}

		/**
		 * Get the currently installed theme version from style.css.
		 */
		private function get_current_version(): string {
			$theme = wp_get_theme( $this->slug );

			return $theme->get( 'Version' );
		}

		/**
		 * Fetch the latest release from GitHub API (with caching).
		 */
		private function get_latest_release(): ?array {
			$cached = get_transient( $this->transient_key );

			if ( false !== $cached ) {
				return $cached;
			}

			$url = sprintf( 'https://api.github.com/repos/%s/releases/latest', $this->repo );

			$response = wp_remote_get( $url, array(
				'headers' => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				),
				'timeout' => 15,
			) );

			if ( is_wp_error( $response ) ) {
				return null;
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status_code ) {
				return null;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( empty( $body ) || ! isset( $body['tag_name'] ) ) {
				return null;
			}

			$release_data = array(
				'version'     => ltrim( $body['tag_name'], 'vV' ),
				'zipball_url' => $body['zipball_url'] ?? '',
				'body'        => $body['body'] ?? '',
				'published'   => $body['published_at'] ?? '',
				'html_url'    => $body['html_url'] ?? '',
			);

			set_transient( $this->transient_key, $release_data, $this->cache_ttl );

			return $release_data;
		}

		/**
		 * Hook into the theme update check transient to inject our update data.
		 */
		public function check_for_update( $transient ) {
			if ( empty( $transient ) ) {
				$transient = new stdClass();
			}

			$release = $this->get_latest_release();

			if ( null === $release ) {
				return $transient;
			}

			$current_version = $this->get_current_version();

			if ( version_compare( $release['version'], $current_version, '>' ) ) {
				$transient->response[ $this->slug ] = array(
					'theme'       => $this->slug,
					'new_version' => $release['version'],
					'url'         => $release['html_url'],
					'package'     => $release['zipball_url'],
				);
			} else {
				// No update available — still report so WP knows we checked.
				$transient->no_update[ $this->slug ] = array(
					'theme'       => $this->slug,
					'new_version' => $release['version'],
					'url'         => $release['html_url'],
					'package'     => $release['zipball_url'],
				);
			}

			return $transient;
		}

		/**
		 * Provide theme details for the "View Details" popup in the WP dashboard.
		 */
		public function theme_info( $result, $action, $args ) {
			if ( 'theme_information' !== $action ) {
				return $result;
			}

			if ( ! isset( $args->slug ) || $args->slug !== $this->slug ) {
				return $result;
			}

			$release = $this->get_latest_release();

			if ( null === $release ) {
				return $result;
			}

			$theme = wp_get_theme( $this->slug );

			return (object) array(
				'name'           => $theme->get( 'Name' ),
				'slug'           => $this->slug,
				'version'        => $release['version'],
				'author'         => $theme->get( 'Author' ),
				'homepage'       => $theme->get( 'ThemeURI' ),
				'download_link'  => $release['zipball_url'],
				'requires'       => '6.0',
				'tested'         => get_bloginfo( 'version' ),
				'requires_php'   => '7.4',
				'last_updated'   => $release['published'],
				'sections'       => array(
					'description' => $theme->get( 'Description' ),
					'changelog'   => nl2br( esc_html( $release['body'] ) ),
				),
			);
		}

		/**
		 * Fix the extracted directory name after download.
		 *
		 * GitHub zipball extracts to "owner-repo-commitsha/" but WordPress
		 * expects the folder to match the theme slug exactly.
		 */
		public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
			// Only act on our theme.
			if ( ! isset( $hook_extra['theme'] ) || $hook_extra['theme'] !== $this->slug ) {
				return $source;
			}

			global $wp_filesystem;

			$corrected_source = trailingslashit( $remote_source ) . $this->slug . '/';

			if ( $source !== $corrected_source ) {
				$wp_filesystem->move( $source, $corrected_source );
			}

			return $corrected_source;
		}
	}
}

new Easydokan_Theme_Updater();