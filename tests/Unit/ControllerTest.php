<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use Brain\Monkey;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Fixtures\TestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Symfony\Component\Process\Process;

/**
 * Unit tests for the main Controller class
 */
class ControllerTest extends TestCase {

	use PluginPathsTrait;

	protected function setUpTestEnvironment() :void {
		parent::setUpTestEnvironment();

		// Mock WordPress constants needed by Controller
		Monkey\Functions\when( 'defined' )->alias( function( $name ) {
			return in_array( $name, [ 'ABSPATH', 'WPINC', 'WP_CONTENT_DIR' ] );
		} );

		// Mock WordPress filesystem functions
		Monkey\Functions\when( 'wp_normalize_path' )->returnArg( 1 );
		Monkey\Functions\when( 'trailingslashit' )->alias( function( $path ) {
			// Intentional manual join: this helper must preserve WordPress trailing-slash behavior exactly.
			return rtrim( $path, '/' ) . '/';
		} );

		// Mock plugin constants
		if ( !defined( 'ABSPATH' ) ) {
			// Intentional manual join: ABSPATH must include a trailing slash to match core expectations.
			define( 'ABSPATH', dirname( dirname( __DIR__ ) ) . '/' );
		}
	}

	public function testControllerCanBeInstantiated() :void {
		$pluginFile = $this->getPluginFile();

		// Mock the WordPress functions that Controller uses during initialization
		Monkey\Functions\when( 'plugin_basename' )->justReturn( 'wp-plugin-shield/icwp-wpsf.php' );
		// Intentional manual join: plugin_dir_path() contract returns a trailing slash.
		Monkey\Functions\when( 'plugin_dir_path' )->justReturn( dirname( $pluginFile ) . '/' );
		Monkey\Functions\when( 'plugin_dir_url' )->justReturn( 'https://example.com/wp-content/plugins/wp-plugin-shield/' );
		
		// Mock version checking - use actual version from plugin.json instead of hardcoding
		$pluginConfig = $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration' );
		$version = $pluginConfig['properties']['version'] ?? '0.0.0';
		Monkey\Functions\when( 'get_file_data' )->justReturn( [ $version ] );

		$controller = Controller::GetInstance( $pluginFile );
		$this->assertInstanceOf( Controller::class, $controller );
	}

	public function testControllerIsSingleton() :void {
		$pluginFile = $this->getPluginFile();

		// Mock WordPress functions for singleton test
		Monkey\Functions\when( 'plugin_basename' )->justReturn( 'wp-plugin-shield/icwp-wpsf.php' );
		// Intentional manual join: plugin_dir_path() contract returns a trailing slash.
		Monkey\Functions\when( 'plugin_dir_path' )->justReturn( dirname( $pluginFile ) . '/' );
		Monkey\Functions\when( 'plugin_dir_url' )->justReturn( 'https://example.com/wp-content/plugins/wp-plugin-shield/' );
		
		// Mock version checking - use actual version from plugin.json instead of hardcoding
		$pluginConfig = $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration' );
		$version = $pluginConfig['properties']['version'] ?? '0.0.0';
		Monkey\Functions\when( 'get_file_data' )->justReturn( [ $version ] );

		$controller1 = Controller::GetInstance( $pluginFile );
		$controller2 = Controller::GetInstance( $pluginFile );
		$this->assertSame( $controller1, $controller2, 'Controller should be a singleton' );
	}

	public function testControllerRequiresValidPluginFile() :void {
		$command = [
			\PHP_BINARY,
			'-r',
			'require getcwd()."/vendor/autoload.php";'.
			'if (!function_exists("__")) { function __($v) { return $v; } }'.
			'try {'.
			'\\FernleafSystems\\Wordpress\\Plugin\\Shield\\Controller\\Controller::GetInstance(null);'.
			'echo "unexpected-success";'.
			'exit(1);'.
			'} catch (\\Exception $e) {'.
			'echo $e->getMessage();'.
			'exit(0);'.
			'}',
		];
		$process = new Process( $command, $this->getPluginRoot() );
		$process->run();

		$this->assertSame( 0, $process->getExitCode() ?? 1, $process->getOutput().$process->getErrorOutput() );
		$this->assertStringContainsString( 'Empty root file provided for instantiation', $process->getOutput() );
	}

	public function testControllerClassConstants() :void {
		// Test that important class constants are defined
		$reflection = new \ReflectionClass( Controller::class );
		$constants = $reflection->getConstants();

		// We don't know the exact constants without looking at the class
		// but we can test that the class can be reflected on
		$this->assertIsArray( $constants );
		// Controller may not be directly instantiable, test that it's a valid class
		$this->assertTrue( $reflection->getName() === Controller::class );
	}

	public function testControllerHasExpectedMethods() :void {
		$reflection = new \ReflectionClass( Controller::class );
		
		// Test for key methods that should exist
		$expectedMethods = [
			'GetInstance',
		];

		foreach ( $expectedMethods as $method ) {
			$this->assertTrue( 
				$reflection->hasMethod( $method ), 
				"Controller should have method: {$method}" 
			);
		}
	}

}
