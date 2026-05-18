<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\UnitTestExecutionSelector;
use PHPUnit\Framework\TestCase;

class UnitTestExecutionSelectorTest extends TestCase {

	public function testSelectsSerialPhpUnitForExplicitSerialMode() :void {
		$selector = new UnitTestExecutionSelector();

		$this->assertSame(
			UnitTestExecutionSelector::STRATEGY_SERIAL_PHPUNIT,
			$selector->selectStrategy(
				[ 'tests/Unit/UnitTestExecutionSelectorTest.php' ],
				UnitTestExecutionSelector::MODE_SERIAL
			)
		);
	}

	public function testSelectsParatestWrapperWhenNoFilterIsPresent() :void {
		$selector = new UnitTestExecutionSelector();

		$this->assertSame(
			UnitTestExecutionSelector::STRATEGY_PARATEST_WRAPPER,
			$selector->selectStrategy( [ 'tests/Unit/Rules' ] )
		);
		$this->assertSame(
			UnitTestExecutionSelector::STRATEGY_PARATEST_WRAPPER,
			$selector->selectStrategy( [ '--group', 'fast' ] )
		);
	}

	public function testSelectsParatestFunctionalWhenFilterIsPresent() :void {
		$selector = new UnitTestExecutionSelector();

		$this->assertSame(
			UnitTestExecutionSelector::STRATEGY_PARATEST_FUNCTIONAL,
			$selector->selectStrategy( [ '--filter', 'FooTest' ] )
		);
		$this->assertSame(
			UnitTestExecutionSelector::STRATEGY_PARATEST_FUNCTIONAL,
			$selector->selectStrategy( [ '--filter=FooTest' ] )
		);
	}

	public function testSelectsSerialPhpUnitForNativeDatasetShortcutInAutoMode() :void {
		$selector = new UnitTestExecutionSelector();

		$this->assertSame(
			UnitTestExecutionSelector::STRATEGY_SERIAL_PHPUNIT,
			$selector->selectStrategy( [ '--filter', 'testOutputDirectoryRequired@null' ] )
		);
		$this->assertSame(
			UnitTestExecutionSelector::STRATEGY_SERIAL_PHPUNIT,
			$selector->selectStrategy( [ '--filter=testOutputDirectoryRequired#2' ] )
		);
	}

	public function testSelectsParatestFunctionalForLongFormDatasetRegex() :void {
		$selector = new UnitTestExecutionSelector();

		$this->assertSame(
			UnitTestExecutionSelector::STRATEGY_PARATEST_FUNCTIONAL,
			$selector->selectStrategy( [ '--filter', '/::testOutputDirectoryRequired .*"null"$/' ] )
		);
		$this->assertSame(
			UnitTestExecutionSelector::STRATEGY_PARATEST_FUNCTIONAL,
			$selector->selectStrategy( [ '--filter', '/::testOutputDirectoryRequired .*#2$/' ] )
		);
	}

	public function testExplicitParallelModeRejectsNativeDatasetShortcut() :void {
		$selector = new UnitTestExecutionSelector();

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'dataset shortcut' );

		$selector->selectStrategy(
			[ '--filter', 'testOutputDirectoryRequired@null' ],
			UnitTestExecutionSelector::MODE_PARALLEL
		);
	}

	public function testBuildCommandUsesParatestFunctionalWhenFilterIsPresent() :void {
		$selector = new UnitTestExecutionSelector();
		$command = $selector->buildCommand( [ '--filter', 'FooTest', 'tests/Unit' ] );

		$this->assertSame( \PHP_BINARY, $command[ 0 ] ?? null );
		$this->assertContains( './vendor/brianium/paratest/bin/paratest', $command );
		$this->assertContains( 'Runner', $command );
		$this->assertContains( '-f', $command );
		$this->assertContains( '--filter', $command );
		$this->assertContains( 'FooTest', $command );
		$this->assertNotContains( 'WrapperRunner', $command );
		$this->assertNotContains( './vendor/phpunit/phpunit/phpunit', $command );
	}

	public function testBuildCommandUsesParatestFunctionalWhenEqualsFilterIsPresent() :void {
		$selector = new UnitTestExecutionSelector();
		$command = $selector->buildCommand( [ '--filter=FooTest' ] );

		$this->assertContains( './vendor/brianium/paratest/bin/paratest', $command );
		$this->assertContains( 'Runner', $command );
		$this->assertContains( '-f', $command );
		$this->assertContains( '--filter=FooTest', $command );
		$this->assertNotContains( 'WrapperRunner', $command );
	}

	public function testBuildCommandUsesParatestFunctionalForMethodFilterPlusPath() :void {
		$selector = new UnitTestExecutionSelector();
		$command = $selector->buildCommand(
			[
				'--filter',
				'testBuildCommandUsesParatestWrapperByDefault',
				'tests/Unit/UnitTestExecutionSelectorTest.php',
			]
		);

		$this->assertContains( './vendor/brianium/paratest/bin/paratest', $command );
		$this->assertContains( 'Runner', $command );
		$this->assertContains( '-f', $command );
		$this->assertContains( 'testBuildCommandUsesParatestWrapperByDefault', $command );
		$this->assertContains( 'tests/Unit/UnitTestExecutionSelectorTest.php', $command );
	}

	public function testBuildCommandUsesParatestWrapperByDefault() :void {
		$selector = new UnitTestExecutionSelector();
		$command = $selector->buildCommand( [ 'tests/Unit/UnitTestExecutionSelectorTest.php' ] );

		$this->assertSame( \PHP_BINARY, $command[ 0 ] ?? null );
		$this->assertContains( './vendor/brianium/paratest/bin/paratest', $command );
		$this->assertContains( '--runner', $command );
		$this->assertContains( 'WrapperRunner', $command );
		$this->assertContains( '--processes=auto', $command );
		$this->assertContains( '--no-coverage', $command );
		$this->assertNotContains( '-f', $command );
	}

	public function testBuildCommandUsesExplicitSerialMode() :void {
		$selector = new UnitTestExecutionSelector();
		$command = $selector->buildCommand(
			[ 'tests/Unit/UnitTestExecutionSelectorTest.php' ],
			UnitTestExecutionSelector::MODE_SERIAL
		);

		$this->assertContains( './vendor/phpunit/phpunit/phpunit', $command );
		$this->assertNotContains( './vendor/brianium/paratest/bin/paratest', $command );
	}

	public function testBuildCommandUsesSerialForDatasetShortcutInAutoMode() :void {
		$selector = new UnitTestExecutionSelector();
		$command = $selector->buildCommand(
			[ '--filter', 'testOutputDirectoryRequired@null', 'tests/Unit/PluginPackagerTest.php' ]
		);

		$this->assertContains( './vendor/phpunit/phpunit/phpunit', $command );
		$this->assertNotContains( './vendor/brianium/paratest/bin/paratest', $command );
		$this->assertContains( 'testOutputDirectoryRequired@null', $command );
	}

	public function testInvalidModeThrows() :void {
		$selector = new UnitTestExecutionSelector();
		$this->expectException( \InvalidArgumentException::class );
		$selector->buildCommand( [ 'tests/Unit' ], 'bogus' );
	}
}
