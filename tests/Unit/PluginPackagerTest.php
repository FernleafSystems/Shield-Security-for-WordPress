<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PluginPackager;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for PluginPackager.
 * Focus: Test orchestrator logic that remains in PluginPackager after extraction.
 */
class PluginPackagerTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = dirname( dirname( __DIR__ ) );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	private function invokePrivateMethod( object $object, string $methodName, array $args = [] ) {
		$reflection = new ReflectionClass( $object );
		$method = $reflection->getMethod( $methodName );
		$method->setAccessible( true );
		return $method->invokeArgs( $object, $args );
	}

	private function createPackager() :PluginPackager {
		return new PluginPackager( $this->projectRoot, function ( string $message ) {} );
	}

	// =========================================================================
	// resolveOutputDirectory() - Required parameter validation
	// =========================================================================

	/**
	 * @dataProvider providerInvalidOutputDirectory
	 */
	public function testOutputDirectoryRequired( $input ) :void {
		$packager = $this->createPackager();

		$this->expectException( \RuntimeException::class );
		$this->invokePrivateMethod( $packager, 'resolveOutputDirectory', [ $input ] );
	}

	public static function providerInvalidOutputDirectory() :array {
		return [
			'empty string' => [ '' ],
			'null'         => [ null ],
			'whitespace'   => [ '   ' ],
			'quotes only'  => [ '""' ],
		];
	}

	// =========================================================================
	// resolveOptions() - Option defaults and overrides
	// =========================================================================

	public function testResolveOptionsIncludesPackageDependencyBuildByDefault() :void {
		$packager = $this->createPackager();
		$options = $this->invokePrivateMethod( $packager, 'resolveOptions', [ [] ] );

		$this->assertArrayHasKey( 'package_dependency_build', $options );
		$this->assertTrue( $options[ 'package_dependency_build' ] );
	}

	public function testResolveOptionsAllowsDisablingPackageDependencyBuild() :void {
		$packager = $this->createPackager();
		$options = $this->invokePrivateMethod( $packager, 'resolveOptions', [
			[ 'package_dependency_build' => false ],
		] );

		$this->assertFalse( $options[ 'package_dependency_build' ] );
	}

	public function testResolveOptionsIgnoresLegacyComposerOptionKeys() :void {
		$packager = $this->createPackager();
		$options = $this->invokePrivateMethod( $packager, 'resolveOptions', [
			[
				'composer_root' => false,
				'composer_lib'  => false,
			],
		] );

		$this->assertTrue( $options[ 'composer_install' ] );
		$this->assertArrayNotHasKey( 'composer_root', $options );
		$this->assertArrayNotHasKey( 'composer_lib', $options );
	}

	public function testUpdatePackageFilesSyncsReadmeAndHeaderFromPluginJson() :void {
		$packager = $this->createPackager();
		$tempDir = $this->createTrackedTempDir( 'shield-packager-test-' );

		file_put_contents(
			$tempDir.'/plugin.json',
			json_encode( [ 'properties' => [ 'version' => '9.8.7' ] ], JSON_PRETTY_PRINT )
		);
		file_put_contents( $tempDir.'/readme.txt', "Stable tag: 1.0.0\n" );
		file_put_contents(
			$tempDir.'/icwp-wpsf.php',
			"<?php\n/*\n * Plugin Name: Test\n * Version: 1.0.0\n */\n"
		);

		$this->invokePrivateMethod( $packager, 'updatePackageFiles', [ $tempDir ] );

		$readme = (string)file_get_contents( $tempDir.'/readme.txt' );
		$pluginHeader = (string)file_get_contents( $tempDir.'/icwp-wpsf.php' );

		$this->assertStringContainsString( 'Stable tag: 9.8.7', $readme );
		$this->assertStringContainsString( '* Version: 9.8.7', $pluginHeader );
	}

	public function testUpdatePackageFilesFailsWhenPluginJsonVersionMissing() :void {
		$packager = $this->createPackager();
		$tempDir = $this->createTrackedTempDir( 'shield-packager-test-' );

		file_put_contents( $tempDir.'/plugin.json', json_encode( [ 'properties' => [] ], JSON_PRETTY_PRINT ) );
		file_put_contents( $tempDir.'/readme.txt', "Stable tag: 1.0.0\n" );
		file_put_contents(
			$tempDir.'/icwp-wpsf.php',
			"<?php\n/*\n * Plugin Name: Test\n * Version: 1.0.0\n */\n"
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'properties.version missing' );

		$this->invokePrivateMethod( $packager, 'updatePackageFiles', [ $tempDir ] );
	}
}
