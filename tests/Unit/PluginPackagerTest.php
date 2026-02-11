<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PluginPackager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for PluginPackager.
 * Focus: Test orchestrator logic that remains in PluginPackager after extraction.
 */
class PluginPackagerTest extends TestCase {

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = dirname( dirname( __DIR__ ) );
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
}
