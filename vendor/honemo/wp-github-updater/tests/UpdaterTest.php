<?php
declare(strict_types=1);

namespace honemo\updater\Tests;

use honemo\updater\Updater;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Test case for Updater class
 *
 * @package honemo\updater\Tests
 */
class UpdaterTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Setup which runs before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress functions.
		Functions\when( 'plugin_basename' )->alias(
			function ( $file ) {
				return basename( dirname( $file ) ) . '/' . basename( $file );
			}
		);
		Functions\when( 'plugin_dir_path' )->justReturn( '/path/to/plugin/' );
		Functions\when( 'is_plugin_active' )->justReturn( false );
		Functions\when( 'activate_plugin' )->justReturn( null );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'add_query_arg' )->returnArg( 3 );
	}

	/**
	 * Teardown which runs after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test constructor sets properties correctly.
	 */
	public function test_constructor_sets_properties(): void {
		$updater = new Updater(
			'/path/to/plugin.php',
			'https://github.com/testuser/test-repo',
			'test-token'
		);

		$reflection = new ReflectionClass( $updater );

		$file_property = $reflection->getProperty( 'file' );
		$file_property->setAccessible( true );
		$this->assertSame( '/path/to/plugin.php', $file_property->getValue( $updater ) );

		$username_property = $reflection->getProperty( 'github_username' );
		$username_property->setAccessible( true );
		$this->assertSame( 'testuser', $username_property->getValue( $updater ) );

		$repo_property = $reflection->getProperty( 'github_repository' );
		$repo_property->setAccessible( true );
		$this->assertSame( 'test-repo', $repo_property->getValue( $updater ) );
	}

	/**
	 * Test constructor parses various URL formats.
	 */
	public function test_constructor_parses_various_url_formats(): void {
		// Test standard HTTPS URL.
		$updater1          = new Updater(
			'/path/to/plugin.php',
			'https://github.com/user1/repo1'
		);
		$reflection        = new ReflectionClass( $updater1 );
		$username_property = $reflection->getProperty( 'github_username' );
		$username_property->setAccessible( true );
		$this->assertSame( 'user1', $username_property->getValue( $updater1 ) );

		// Test URL with .git extension.
		$updater2      = new Updater(
			'/path/to/plugin.php',
			'https://github.com/user2/repo2.git'
		);
		$repo_property = $reflection->getProperty( 'github_repository' );
		$repo_property->setAccessible( true );
		$this->assertSame( 'repo2', $repo_property->getValue( $updater2 ) );

		// Test URL with trailing slash.
		$updater3 = new Updater(
			'/path/to/plugin.php',
			'https://github.com/user3/repo3/'
		);
		$this->assertSame( 'user3', $username_property->getValue( $updater3 ) );
		$this->assertSame( 'repo3', $repo_property->getValue( $updater3 ) );

		// Test URL without https://.
		$updater4 = new Updater(
			'/path/to/plugin.php',
			'github.com/user4/repo4'
		);
		$this->assertSame( 'user4', $username_property->getValue( $updater4 ) );
		$this->assertSame( 'repo4', $repo_property->getValue( $updater4 ) );
	}

	/**
	 * Test constructor throws exception for invalid URL.
	 */
	public function test_constructor_throws_exception_for_invalid_url(): void {
		$this->expectException( \InvalidArgumentException::class );

		new Updater(
			'/path/to/plugin.php',
			'https://gitlab.com/user/repo'
		);
	}

	/**
	 * Test get_secure_token uses passed token first.
	 */
	public function test_get_secure_token_uses_passed_token(): void {
		$updater = new Updater(
			'/path/to/plugin.php',
			'https://github.com/testuser/test-repo',
			'passed-token'
		);

		$reflection     = new ReflectionClass( $updater );
		$token_property = $reflection->getProperty( 'github_token' );
		$token_property->setAccessible( true );

		$this->assertSame( 'passed-token', $token_property->getValue( $updater ) );
	}

	/**
	 * Test get_secure_token uses constant when no token passed.
	 */
	public function test_get_secure_token_uses_constant(): void {
		define( 'GITHUB_UPDATER_TOKEN', 'constant-token' );

		$updater = new Updater(
			'/path/to/plugin.php',
			'https://github.com/testuser/test-repo'
		);

		$reflection     = new ReflectionClass( $updater );
		$token_property = $reflection->getProperty( 'github_token' );
		$token_property->setAccessible( true );

		$this->assertSame( 'constant-token', $token_property->getValue( $updater ) );
	}

	/**
	 * Test get_secure_token uses WordPress option when no other source.
	 */
	public function test_get_secure_token_uses_wordpress_option(): void {
		// Reset constant if defined in previous test.
		if ( defined( 'GITHUB_UPDATER_TOKEN' ) ) {
			$this->markTestSkipped( 'Cannot undefine constant in same process' );
		}

		Functions\expect( 'get_option' )
			->once()
			->with( 'github_updater_token', '' )
			->andReturn( 'option-token' );

		$updater = new Updater(
			'/path/to/plugin.php',
			'https://github.com/testuser/test-repo'
		);

		$reflection     = new ReflectionClass( $updater );
		$token_property = $reflection->getProperty( 'github_token' );
		$token_property->setAccessible( true );

		$this->assertSame( 'option-token', $token_property->getValue( $updater ) );
	}

	/**
	 * Test init adds WordPress filters.
	 */
	public function test_init_adds_filters(): void {
		// Reset the add_filter mock for this test.
		Monkey\tearDown();
		Monkey\setUp();

		// Mock WordPress functions needed by constructor.
		Functions\when( 'plugin_basename' )->alias(
			function ( $file ) {
				return basename( dirname( $file ) ) . '/' . basename( $file );
			}
		);
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$filter_count = 0;
		Functions\when( 'add_filter' )->alias(
			function () use ( &$filter_count ) {
				++$filter_count;
				return true;
			}
		);

		$updater = new Updater(
			'/path/to/plugin.php',
			'https://github.com/testuser/test-repo'
		);

		$updater->init();

		// Should have called add_filter 4 times.
		$this->assertSame( 4, $filter_count );
	}

	/**
	 * Test normalize_version removes "v" prefix.
	 */
	public function test_normalize_version_removes_v_prefix(): void {
		$updater = new Updater(
			'/path/to/plugin.php',
			'https://github.com/testuser/test-repo'
		);

		$reflection = new ReflectionClass( $updater );
		$method     = $reflection->getMethod( 'normalize_version' );
		$method->setAccessible( true );

		$this->assertSame( '1.0.0', $method->invoke( $updater, 'v1.0.0' ) );
		$this->assertSame( '2.3.4', $method->invoke( $updater, 'V2.3.4' ) );
		$this->assertSame( '1.0.0', $method->invoke( $updater, '1.0.0' ) );
		$this->assertSame( '10.20.30', $method->invoke( $updater, 'v10.20.30' ) );
	}

	/**
	 * Test modify_transient returns early if checked property missing.
	 */
	public function test_modify_transient_returns_early_without_checked_property(): void {
		$updater = new Updater(
			'/path/to/plugin.php',
			'https://github.com/testuser/test-repo'
		);

		$transient = (object) [];
		$result    = $updater->modify_transient( $transient );

		$this->assertSame( $transient, $result );
		$this->assertObjectNotHasProperty( 'response', $result );
	}

	/**
	 * Test modify_transient does not add response when no update available.
	 */
	public function test_modify_transient_no_update_available(): void {
		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				[
					'body' => json_encode(
						[
							[
								'tag_name'    => 'v1.0.0',
								'zipball_url' => 'https://api.github.com/repos/test/repo/zipball/v1.0.0',
							],
						]
					),
				]
			);

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturnUsing(
				function ( $response ) {
					return $response['body'];
				}
			);

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'get_plugin_data' )
			->once()
			->andReturn(
				[
					'Name'        => 'Test Plugin',
					'PluginURI'   => 'https://example.com',
					'Version'     => '1.0.0',
					'Description' => 'Test plugin description',
				]
			);

		$updater = new Updater(
			'/path/to/plugin.php',
			'https://github.com/testuser/test-repo'
		);

		$transient = (object) [
			'checked' => [
				'to/plugin.php' => '1.0.0',
			],
		];

		$result = $updater->modify_transient( $transient );

		$this->assertObjectNotHasProperty( 'response', $result );
	}

	/**
	 * Test modify_transient adds response when update available.
	 */
	public function test_modify_transient_adds_update_when_available(): void {
		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				[
					'body' => json_encode(
						[
							[
								'tag_name'    => 'v2.0.0',
								'zipball_url' => 'https://api.github.com/repos/test/repo/zipball/v2.0.0',
							],
						]
					),
				]
			);

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturnUsing(
				function ( $response ) {
					return $response['body'];
				}
			);

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'get_plugin_data' )
			->once()
			->andReturn(
				[
					'Name'        => 'Test Plugin',
					'PluginURI'   => 'https://example.com',
					'Version'     => '1.0.0',
					'Description' => 'Test plugin description',
				]
			);

		$updater = new Updater(
			'/path/to/plugin.php',
			'https://github.com/testuser/test-repo'
		);

		$transient = (object) [
			'checked' => [
				'to/plugin.php' => '1.0.0',
			],
		];

		$result = $updater->modify_transient( $transient );

		$this->assertObjectHasProperty( 'response', $result );
		$this->assertIsArray( $result->response );
		$this->assertArrayHasKey( 'to/plugin.php', $result->response );
		$this->assertSame( '2.0.0', $result->response['to/plugin.php']->new_version );
	}

	/**
	 * Test plugin_popup returns false for wrong action.
	 */
	public function test_plugin_popup_returns_false_for_wrong_action(): void {
		$updater = new Updater(
			'/path/to/plugin.php',
			'https://github.com/testuser/test-repo'
		);

		$args   = (object) [ 'slug' => 'test-plugin' ];
		$result = $updater->plugin_popup( false, 'plugin_install', $args );

		$this->assertFalse( $result );
	}

	/**
	 * Test plugin_popup returns plugin information.
	 */
	public function test_plugin_popup_returns_plugin_information(): void {
		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				[
					'body' => json_encode(
						[
							[
								'tag_name'     => 'v1.5.0',
								'zipball_url'  => 'https://api.github.com/repos/test/repo/zipball/v1.5.0',
								'body'         => 'Release notes',
								'published_at' => '2026-01-01',
							],
						]
					),
				]
			);

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturnUsing(
				function ( $response ) {
					return $response['body'];
				}
			);

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'get_plugin_data' )
			->once()
			->andReturn(
				[
					'Name'        => 'Test Plugin',
					'PluginURI'   => 'https://example.com',
					'Version'     => '1.0.0',
					'Description' => 'Test plugin description',
					'Author'      => 'Test Author',
					'AuthorURI'   => 'https://author.com',
					'AuthorName'  => 'Test Author',
					'RequiresWP'  => '6.0',
					'TestedUpTo'  => '6.4',
				]
			);

		$updater = new Updater(
			'/path/to/plugin.php',
			'https://github.com/testuser/test-repo'
		);

		// Basename will be 'to/plugin.php', first part is 'to'.
		$args   = (object) [ 'slug' => 'to' ];
		$result = $updater->plugin_popup( false, 'plugin_information', $args );

		$this->assertIsObject( $result );
		$this->assertSame( 'Test Plugin', $result->name );
		$this->assertSame( '1.5.0', $result->version );
		$this->assertSame( 'Test plugin description', $result->short_description );
	}

	/**
	 * Test set_header_token adds authorization header for GitHub API.
	 */
	public function test_set_header_token_adds_authorization_header(): void {
		$updater = new Updater(
			'/path/to/plugin.php',
			'https://github.com/testuser/test-repo',
			'test-token'
		);

		$parsed_args = [ 'headers' => [] ];
		$url         = 'https://api.github.com/repos/test/repo?access_token=test-token';

		$result = $updater->set_header_token( $parsed_args, $url );

		$this->assertArrayHasKey( 'headers', $result );
		$this->assertArrayHasKey( 'Authorization', $result['headers'] );
		$this->assertSame( 'token test-token', $result['headers']['Authorization'] );
	}

	/**
	 * Test set_header_token does not modify non-GitHub URLs.
	 */
	public function test_set_header_token_ignores_non_github_urls(): void {
		$updater = new Updater(
			'/path/to/plugin.php',
			'https://github.com/testuser/test-repo',
			'test-token'
		);

		$parsed_args = [ 'headers' => [] ];
		$url         = 'https://example.com/api';

		$result = $updater->set_header_token( $parsed_args, $url );

		$this->assertSame( $parsed_args, $result );
	}
}
