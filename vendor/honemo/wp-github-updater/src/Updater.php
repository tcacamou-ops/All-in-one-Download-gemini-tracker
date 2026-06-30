<?php
declare(strict_types=1);

namespace honemo\updater;

/**
 * GitHub Plugin Updater
 *
 * Updates a WordPress plugin from a GitHub repository (public or private).
 *
 * @package honemo\updater
 */
class Updater {

	/**
	 * Plugin file path
	 *
	 * @var string
	 */
	private string $file;

	/**
	 * Plugin data from WordPress
	 *
	 * @var array<string, string>|null
	 */
	private ?array $plugin_data = null;

	/**
	 * Plugin basename
	 *
	 * @var string
	 */
	private string $basename;

	/**
	 * Whether the plugin is currently active
	 *
	 * @var bool
	 */
	private bool $active = false;

	/**
	 * GitHub API response data
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $github_response = null;

	/**
	 * GitHub username
	 *
	 * @var string
	 */
	private string $github_username;

	/**
	 * GitHub repository name
	 *
	 * @var string
	 */
	private string $github_repository;

	/**
	 * GitHub authentication token
	 *
	 * @var string
	 */
	private string $github_token;

	/**
	 * GitHub API request URI pattern
	 *
	 * @var string
	 */
	private string $request_uri_pattern = 'https://api.github.com/repos/%s/%s/releases';

	/**
	 * Constructor
	 *
	 * @param string $file           Main plugin file path.
	 * @param string $repository_url GitHub repository URL (e.g., https://github.com/username/repo).
	 * @param string $github_token   GitHub authentication token (optional for public repos).
	 *                               For security, use a constant from wp-config.php instead of hardcoding.
	 *                               Example: define('GITHUB_UPDATER_TOKEN', 'ghp_xxxxx') in wp-config.php
	 *                               Then pass: constant('GITHUB_UPDATER_TOKEN').
	 */
	public function __construct(
		string $file,
		string $repository_url,
		string $github_token = ''
	) {
		$this->file     = $file;
		$this->basename = plugin_basename( $this->file );

		// Parse repository URL to extract username and repository name.
		$this->parse_repository_url( $repository_url );

		$this->github_token = $this->get_secure_token( $github_token );
	}

	/**
	 * Parse GitHub repository URL to extract username and repository name
	 *
	 * Supports various GitHub URL formats:
	 * - https://github.com/username/repo
	 * - https://github.com/username/repo.git
	 * - git@github.com:username/repo.git
	 * - github.com/username/repo
	 *
	 * @param string $url GitHub repository URL.
	 *
	 * @throws \InvalidArgumentException If URL format is invalid.
	 *
	 * @return void
	 */
	private function parse_repository_url( string $url ): void {
		// Remove trailing slashes and .git extension.
		$url = rtrim( $url, '/' );
		$url = preg_replace( '/\.git$/', '', $url );

		// Handle git@ format.
		if ( 0 === strpos( $url, 'git@github.com:' ) ) {
			$url = str_replace( 'git@github.com:', 'https://github.com/', $url );
		}

		// Add https:// if missing.
		if ( ! preg_match( '#^https?://#', $url ) ) {
			$url = 'https://' . $url;
		}

		// Parse URL to extract path.
		$parsed = wp_parse_url( $url );

		if ( ! isset( $parsed['host'] ) || 'github.com' !== $parsed['host'] ) {
			throw new \InvalidArgumentException( 'Invalid GitHub repository URL. Must be a github.com URL.' );
		}

		if ( ! isset( $parsed['path'] ) ) {
			throw new \InvalidArgumentException( 'Invalid GitHub repository URL. Missing repository path.' );
		}

		// Extract username and repository from path (format: /username/repo).
		$path_parts = array_filter( explode( '/', trim( $parsed['path'], '/' ) ) );

		if ( count( $path_parts ) < 2 ) {
			throw new \InvalidArgumentException( 'Invalid GitHub repository URL. Expected format: https://github.com/username/repository' );
		}

		$this->github_username   = $path_parts[0];
		$this->github_repository = $path_parts[1];
	}

	/**
	 * Get GitHub token from secure sources
	 *
	 * Checks multiple secure sources in order of priority:
	 * 1. Passed parameter (if not empty)
	 * 2. WordPress constant (GITHUB_UPDATER_TOKEN)
	 * 3. Environment variable (GITHUB_UPDATER_TOKEN)
	 * 4. WordPress option (github_updater_token)
	 *
	 * @param string $token Token passed to constructor.
	 *
	 * @return string The token from the most secure available source.
	 */
	private function get_secure_token( string $token ): string {
		// 1. Use passed token if provided.
		if ( ! empty( $token ) ) {
			return $token;
		}

		// 2. Check for constant in wp-config.php (recommended).
		if ( defined( 'GITHUB_UPDATER_TOKEN' ) ) {
			return constant( 'GITHUB_UPDATER_TOKEN' );
		}

		// 3. Check for environment variable.
		$env_token = getenv( 'GITHUB_UPDATER_TOKEN' );
		if ( false !== $env_token && ! empty( $env_token ) ) {
			return $env_token;
		}

		// 4. Check for WordPress option (stored in database).
		$option_token = get_option( 'github_updater_token', '' );
		if ( ! empty( $option_token ) ) {
			return $option_token;
		}

		// No token found - will work for public repositories only.
		return '';
	}

	/**
	 * Initialize the updater
	 *
	 * Hooks into WordPress filters to handle plugin updates.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'modify_transient' ], 10, 1 );
		add_filter( 'http_request_args', [ $this, 'set_header_token' ], 10, 2 );
		add_filter( 'plugins_api', [ $this, 'plugin_popup' ], 10, 3 );
		add_filter( 'upgrader_post_install', [ $this, 'after_install' ], 10, 3 );
	}

	/**
	 * Modify the plugin update transient
	 *
	 * Checks if a new version is available on GitHub and updates the transient.
	 *
	 * @param object $transient The update_plugins transient object.
	 *
	 * @return object Modified transient.
	 */
	public function modify_transient( object $transient ): object {
		if ( ! property_exists( $transient, 'checked' ) ) {
			return $transient;
		}

		$this->fetch_github_repos();
		$this->get_plugin_data();

		$github_version  = $this->normalize_version( $this->github_response['tag_name'] ?? '0' );
		$current_version = $transient->checked[ $this->basename ] ?? '0';

		if ( version_compare( $github_version, $current_version, 'gt' ) ) {
			$plugin = [
				'url'         => $this->plugin_data['PluginURI'] ?? '',
				'slug'        => current( explode( '/', $this->basename ) ),
				'package'     => $this->github_response['zipball_url'] ?? '',
				'new_version' => $github_version,
			];

			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				// Initialize response property if not exists. @phpstan-ignore-next-line.
				$transient->response = [];
			}
			// Add plugin to response.
			$transient->response[ $this->basename ] = (object) $plugin;
		}

		return $transient;
	}

	/**
	 * Obviously ?? Get plugin data from WordPress
	 *
	 * @return void
	 */
	private function get_plugin_data(): void {
		if ( null !== $this->plugin_data ) {
			return;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$this->plugin_data = get_plugin_data( $this->file );
	}

	/**
	 * Provide plugin information for the update popup
	 *
	 * @param false|object|array<string, mixed> $result The result object or array. Default false.
	 * @param string                            $action The type of information being requested.
	 * @param object                            $args   Plugin API arguments.
	 *
	 * @return false|object Plugin information or false.
	 */
	public function plugin_popup( $result, string $action, object $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return false;
		}

		if ( current( explode( '/', $this->basename ) ) === $args->slug ) {
			$this->fetch_github_repos();
			$this->get_plugin_data();

			$plugin = [
				'name'              => $this->plugin_data['Name'] ?? '',
				'slug'              => $this->basename,
				'requires'          => $this->plugin_data['RequiresWP'] ?? '',
				'tested'            => $this->plugin_data['TestedUpTo'] ?? '',
				'version'           => $this->normalize_version( $this->github_response['tag_name'] ?? '' ),
				'author'            => $this->plugin_data['AuthorName'] ?? '',
				'author_profile'    => $this->plugin_data['AuthorURI'] ?? '',
				'last_updated'      => $this->github_response['published_at'] ?? '',
				'homepage'          => $this->plugin_data['PluginURI'] ?? '',
				'short_description' => $this->plugin_data['Description'] ?? '',
				'sections'          => [
					'Description' => $this->plugin_data['Description'] ?? '',
					'Updates'     => $this->github_response['body'] ?? '',
				],
				'download_link'     => $this->github_response['zipball_url'] ?? '',
			];

			return (object) $plugin;
		}

		return $result;
	}

	/**
	 * Reactivate the plugin after installation
	 *
	 * @param bool                 $response   Installation response.
	 * @param array<string, mixed> $hook_extra Extra arguments passed to hooked filters.
	 * @param array<string, mixed> $result     Installation result data.
	 *
	 * @return bool Installation response.
	 */
	public function after_install( bool $response, array $hook_extra, array $result ): bool {
		global $wp_filesystem;

		$install_directory = plugin_dir_path( $this->file );
		$wp_filesystem->move( $result['destination'], $install_directory );
		$result['destination'] = $install_directory;

		if ( $this->active ) {
			activate_plugin( $this->basename );
		}

		return $response;
	}

	/**
	 * Set GitHub authentication token in request headers
	 *
	 * @param array<string, mixed> $parsed_args HTTP request arguments.
	 * @param string               $url         The request URL.
	 *
	 * @return array<string, mixed> Modified request arguments.
	 */
	public function set_header_token( array $parsed_args, string $url ): array {
		$parsed_url = wp_parse_url( $url );

		if ( 'api.github.com' === ( $parsed_url['host'] ?? '' ) && isset( $parsed_url['query'] ) ) {
			parse_str( $parsed_url['query'], $query );

			if ( isset( $query['access_token'] ) ) {
				$parsed_args['headers']['Authorization'] = 'token ' . $query['access_token'];
				$this->active                            = is_plugin_active( $this->basename );
			}
		}

		return $parsed_args;
	}

	/**
	 * Fetches the latest release information from GitHub.
	 *
	 * @return void
	 */
	private function fetch_github_repos(): void {
		if ( null !== $this->github_response ) {
			return;
		}

		$args = [
			'method'      => 'GET',
			'timeout'     => 5,
			'redirection' => 5,
			'httpversion' => '1.0',
			'sslverify'   => true,
		];

		// Add authorization header if token is provided.
		if ( ! empty( $this->github_token ) ) {
			$args['headers'] = [
				'Authorization' => 'token ' . $this->github_token,
			];
		}

		$request_uri = sprintf( $this->request_uri_pattern, $this->github_username, $this->github_repository );
		$request     = wp_remote_get( $request_uri, $args );

		if ( is_wp_error( $request ) ) {
			$this->github_response = [];
			return;
		}

		$response = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( is_array( $response ) && ! empty( $response ) ) {
			$response = current( $response );
		}

		// Add access token to zipball URL for private repositories.
		if ( ! empty( $this->github_token ) && isset( $response['zipball_url'] ) ) {
			$response['zipball_url'] = add_query_arg( 'access_token', $this->github_token, $response['zipball_url'] );
		}

		$this->github_response = is_array( $response ) ? $response : [];
	}

	/**
	 * Normalize version number
	 *
	 * Removes leading "v" or "V" from version strings (e.g., "v1.0.0" becomes "1.0.0").
	 *
	 * @param string $version The version string to normalize.
	 *
	 * @return string Normalized version string.
	 */
	private function normalize_version( string $version ): string {
		return ltrim( $version, 'vV' );
	}
}
