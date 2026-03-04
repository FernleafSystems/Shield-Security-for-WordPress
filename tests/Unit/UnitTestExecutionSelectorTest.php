<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\UnitTestExecutionSelector;
use PHPUnit\Framework\TestCase;

class UnitTestExecutionSelectorTest extends TestCase {

	public function testShouldUseSerialPhpUnitWhenFilterArgProvided() :void {
		$selector = new UnitTestExecutionSelector();
		$this->assertTrue( $selector->shouldUseSerialPhpUnit( [ '--filter', 'FooTest' ] ) );
		$this->assertTrue( $selector->shouldUseSerialPhpUnit( [ '--filter=FooTest' ] ) );
	}

	public function testShouldUseParatestWhenNoFilterArgProvided() :void {
		$selector = new UnitTestExecutionSelector();
		$this->assertFalse( $selector->shouldUseSerialPhpUnit( [ 'tests/Unit/Rules' ] ) );
		$this->assertFalse( $selector->shouldUseSerialPhpUnit( [ '--group', 'fast' ] ) );
	}

	public function testBuildCommandUsesSerialPhpUnitWhenFilterIsPresent() :void {
		$selector = new UnitTestExecutionSelector();
		$command = $selector->buildCommand( [ '--filter', 'FooTest', 'tests/Unit' ] );

		$this->assertSame( \PHP_BINARY, $command[ 0 ] ?? null );
		$this->assertContains( './vendor/phpunit/phpunit/phpunit', $command );
		$this->assertNotContains( 'paratest', $command );
		$this->assertContains( '--filter', $command );
	}

	public function testBuildCommandUsesParatestByDefault() :void {
		$selector = new UnitTestExecutionSelector();
		$command = $selector->buildCommand( [ 'tests/Unit/UnitTestExecutionSelectorTest.php' ] );

		$this->assertSame( \PHP_BINARY, $command[ 0 ] ?? null );
		$this->assertContains( './vendor/brianium/paratest/bin/paratest', $command );
		$this->assertContains( '--runner', $command );
		$this->assertContains( 'WrapperRunner', $command );
		$this->assertContains( '--processes=auto', $command );
		$this->assertContains( '--no-coverage', $command );
	}
}
