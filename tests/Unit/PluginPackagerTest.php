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
}
