<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalIntegrationTestLane;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class PowerTestToolingContractTest extends TestCase {

	public function testComposerScriptsEncodeDispatcherDefaultAndParallelUnitLane() :void {
		$composer = $this->loadComposerJson();
		$scripts = $composer[ 'scripts' ] ?? [];

		$this->assertIsArray( $scripts[ 'test:unit' ] ?? null );
		$this->assertIsArray( $scripts[ 'test:unit:serial' ] ?? null );
		$this->assertIsArray( $scripts[ 'test:unit:parallel' ] ?? null );
		$this->assertIsArray( $scripts[ 'test:unit:verify-power' ] ?? null );
		$this->assertContains( '@build:config', $scripts[ 'test:unit' ] );
		$this->assertContains( '@php bin/run-unit-tests.php', $scripts[ 'test:unit' ] );

		$serial = \implode( ' ', $scripts[ 'test:unit:serial' ] );
		$this->assertStringContainsString( 'vendor/phpunit/phpunit/phpunit -c phpunit-unit.xml', $serial );

		$parallel = \implode( ' ', $scripts[ 'test:unit:parallel' ] );
		$this->assertStringContainsString( 'vendor/brianium/paratest/bin/paratest -c phpunit-unit.xml', $parallel );
		$this->assertStringContainsString( '--runner WrapperRunner', $parallel );
		$this->assertStringContainsString( '--processes=auto', $parallel );
		$this->assertStringContainsString( '--no-coverage', $parallel );

		$this->assertSame(
			[ '@test:unit:serial', '@test:unit:parallel' ],
			$scripts[ 'test:unit:verify-power' ]
		);
	}

	public function testLocalIntegrationLaneRemainsSerialPhpUnit() :void {
		$lane = new LocalIntegrationTestLane();
		$reflection = new \ReflectionClass( $lane );
		$method = $reflection->getMethod( 'buildPhpUnitCommand' );
		$method->setAccessible( true );

		$command = $method->invoke( $lane, [ '--filter', 'InfrastructureSmokeTest' ] );
		$this->assertIsArray( $command );
		$this->assertSame( \PHP_BINARY, $command[ 0 ] ?? null );
		$this->assertContains( './vendor/phpunit/phpunit/phpunit', $command );
		$this->assertNotContains( 'paratest', $command );
		$this->assertContains( 'phpunit-integration.xml', $command );
	}

	public function testIntegrationBootstrapFailsFastWhenParallelTokensArePresent() :void {
		$bootstrapPath = \dirname( __DIR__, 2 ).'/tests/Integration/bootstrap.php';
		$process = new Process( [
			\PHP_BINARY,
			'-r',
			"putenv('TEST_TOKEN=1'); require '".\str_replace( '\\', '\\\\', $bootstrapPath )."';",
		], \dirname( __DIR__, 2 ) );

		$process->run();
		$this->assertSame( 1, $process->getExitCode() );
		$this->assertStringContainsString( 'integration tests are serial-only', \strtolower( $process->getErrorOutput().$process->getOutput() ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function loadComposerJson() :array {
		$path = \dirname( __DIR__, 2 ).'/composer.json';
		$raw = \file_get_contents( $path );
		$this->assertIsString( $raw );

		$data = \json_decode( $raw, true );
		$this->assertIsArray( $data );
		return $data;
	}
}
