<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\UnitTestExecutionSelector;
use FernleafSystems\ShieldPlatform\Tooling\Testing\UnitTestScriptRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use PHPUnit\Framework\TestCase;

class UnitTestScriptRunnerTest extends TestCase {

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
	}

	public function testAutoModeSelectsParatestWrapperWithoutFilter() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$runner = $this->newRunner( $processRunner );

		$exitCode = $runner->run( [ 'tests/Unit/UnitTestExecutionSelectorTest.php' ], $this->projectRoot );

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertContains( './vendor/brianium/paratest/bin/paratest', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( 'WrapperRunner', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertNotContains( '-f', $processRunner->calls[ 0 ][ 'command' ] );
	}

	public function testAutoModeRunsMultipleConcretePathsThroughSeparateParatestWrapperCommands() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$runner = $this->newRunner( $processRunner );

		$exitCode = $runner->run(
			[
				'tests/Unit/UnitTestExecutionSelectorTest.php',
				'tests/Unit/UnitTestScriptRunnerTest.php',
			],
			$this->projectRoot
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 2, $processRunner->calls );
		$this->assertCommandContainsOnlyOnePath(
			$processRunner->calls[ 0 ][ 'command' ],
			'tests/Unit/UnitTestExecutionSelectorTest.php',
			'tests/Unit/UnitTestScriptRunnerTest.php'
		);
		$this->assertCommandContainsOnlyOnePath(
			$processRunner->calls[ 1 ][ 'command' ],
			'tests/Unit/UnitTestScriptRunnerTest.php',
			'tests/Unit/UnitTestExecutionSelectorTest.php'
		);
	}

	public function testAutoModeUsesParatestFunctionalWithFilter() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$runner = $this->newRunner( $processRunner );

		$exitCode = $runner->run(
			[ '--filter', 'testBuildCommandUsesParatestWrapperByDefault', 'tests/Unit/UnitTestExecutionSelectorTest.php' ],
			$this->projectRoot
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$command = $processRunner->calls[ 0 ][ 'command' ];
		$this->assertContains( './vendor/brianium/paratest/bin/paratest', $command );
		$this->assertContains( 'Runner', $command );
		$this->assertContains( '-f', $command );
		$this->assertContains( '--filter', $command );
		$this->assertNotContains( './vendor/phpunit/phpunit/phpunit', $command );
		$this->assertNotContains( 'WrapperRunner', $command );
	}

	public function testAutoModeUsesSerialPhpUnitWithDatasetShortcutFilter() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$runner = $this->newRunner( $processRunner );

		$exitCode = $runner->run(
			[ '--filter', 'testOutputDirectoryRequired@null', 'tests/Unit/PluginPackagerTest.php' ],
			$this->projectRoot
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertContains( './vendor/phpunit/phpunit/phpunit', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertNotContains( './vendor/brianium/paratest/bin/paratest', $processRunner->calls[ 0 ][ 'command' ] );
	}

	public function testAutoModeRunsMultipleConcretePathsWithFilterThroughSeparateFunctionalCommands() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$runner = $this->newRunner( $processRunner );

		$exitCode = $runner->run(
			[
				'--filter',
				'UnitTest',
				'tests/Unit/UnitTestExecutionSelectorTest.php',
				'tests/Unit/UnitTestScriptRunnerTest.php',
			],
			$this->projectRoot
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 2, $processRunner->calls );
		foreach ( $processRunner->calls as $call ) {
			$this->assertContains( './vendor/brianium/paratest/bin/paratest', $call[ 'command' ] );
			$this->assertContains( 'Runner', $call[ 'command' ] );
			$this->assertContains( '-f', $call[ 'command' ] );
			$this->assertContains( '--filter', $call[ 'command' ] );
			$this->assertContains( 'UnitTest', $call[ 'command' ] );
		}
		$this->assertCommandContainsOnlyOnePath(
			$processRunner->calls[ 0 ][ 'command' ],
			'tests/Unit/UnitTestExecutionSelectorTest.php',
			'tests/Unit/UnitTestScriptRunnerTest.php'
		);
		$this->assertCommandContainsOnlyOnePath(
			$processRunner->calls[ 1 ][ 'command' ],
			'tests/Unit/UnitTestScriptRunnerTest.php',
			'tests/Unit/UnitTestExecutionSelectorTest.php'
		);
	}

	public function testAutoModeRunsMultipleConcretePathsWithEqualsFilterThroughSeparateFunctionalCommands() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$runner = $this->newRunner( $processRunner );

		$exitCode = $runner->run(
			[
				'--filter=UnitTest',
				'tests/Unit/UnitTestExecutionSelectorTest.php',
				'tests/Unit/UnitTestScriptRunnerTest.php',
			],
			$this->projectRoot
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 2, $processRunner->calls );
		foreach ( $processRunner->calls as $call ) {
			$this->assertContains( './vendor/brianium/paratest/bin/paratest', $call[ 'command' ] );
			$this->assertContains( 'Runner', $call[ 'command' ] );
			$this->assertContains( '-f', $call[ 'command' ] );
			$this->assertContains( '--filter=UnitTest', $call[ 'command' ] );
		}
	}

	public function testExplicitSerialModeUsesPhpUnitEvenWithoutFilter() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$runner = $this->newRunner( $processRunner );

		$exitCode = $runner->run(
			[ '--runner-mode=serial', 'tests/Unit/UnitTestExecutionSelectorTest.php' ],
			$this->projectRoot
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertContains( './vendor/phpunit/phpunit/phpunit', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertNotContains( '--runner-mode=serial', $processRunner->calls[ 0 ][ 'command' ] );
	}

	public function testExplicitParallelModeUsesParatestFunctionalWithFilter() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$runner = $this->newRunner( $processRunner );

		$exitCode = $runner->run(
			[
				'--runner-mode',
				'parallel',
				'--filter',
				'testBuildCommandUsesParatestWrapperByDefault',
				'tests/Unit/UnitTestExecutionSelectorTest.php',
			],
			$this->projectRoot
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$command = $processRunner->calls[ 0 ][ 'command' ];
		$this->assertContains( './vendor/brianium/paratest/bin/paratest', $command );
		$this->assertContains( 'Runner', $command );
		$this->assertContains( '-f', $command );
		$this->assertNotContains( '--runner-mode', $command );
		$this->assertNotContains( 'parallel', $command );
	}

	public function testExplicitParallelModeRejectsDatasetShortcutFilter() :void {
		$runner = $this->newRunner();

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'dataset shortcut' );

		$runner->run(
			[
				'--runner-mode=parallel',
				'--filter',
				'testOutputDirectoryRequired@null',
				'tests/Unit/PluginPackagerTest.php',
			],
			$this->projectRoot
		);
	}

	public function testMissingRunnerModeValueThrows() :void {
		$runner = $this->newRunner();
		$this->expectException( \InvalidArgumentException::class );
		$runner->run( [ '--runner-mode' ], $this->projectRoot );
	}

	public function testInvalidRunnerModeThrows() :void {
		$runner = $this->newRunner();
		$this->expectException( \InvalidArgumentException::class );
		$runner->run( [ '--runner-mode=invalid' ], $this->projectRoot );
	}

	private function newRunner( ?RecordingProcessRunner $processRunner = null ) :UnitTestScriptRunner {
		return new UnitTestScriptRunner(
			$processRunner ?? new RecordingProcessRunner( [ 0 ] ),
			new UnitTestExecutionSelector()
		);
	}

	/**
	 * @param string[] $command
	 */
	private function assertCommandContainsOnlyOnePath( array $command, string $expectedPath, string $unexpectedPath ) :void {
		$this->assertContains( './vendor/brianium/paratest/bin/paratest', $command );
		$this->assertContains( $expectedPath, $command );
		$this->assertNotContains( $unexpectedPath, $command );
		$this->assertSame( $expectedPath, $command[ \count( $command ) - 1 ] );
	}
}
