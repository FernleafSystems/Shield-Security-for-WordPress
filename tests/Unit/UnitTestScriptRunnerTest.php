<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\UnitTestExecutionSelector;
use FernleafSystems\ShieldPlatform\Tooling\Testing\UnitTestScriptRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use PHPUnit\Framework\TestCase;

class UnitTestScriptRunnerTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testFullRunExecutesIsolatedOperatorSuiteBeforeParallelSuite() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$exitCode = $this->newRunner( $processRunner )->run( [], $this->projectRoot );

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 2, $processRunner->calls );
		$this->assertContains( 'tests/Unit/ReleaseOperatorCommandTest.php', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( '--no-configuration', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( 'memory_limit=1536M', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( '--disallow-test-output', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( './vendor/brianium/paratest/bin/paratest', $processRunner->calls[ 1 ][ 'command' ] );
	}

	public function testUnitDirectorySelectionExecutesTheIsolatedOperatorSuite() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$exitCode = $this->newRunner( $processRunner )->run( [ 'tests/Unit' ], $this->projectRoot );

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 2, $processRunner->calls );
		$this->assertContains( 'tests/Unit/ReleaseOperatorCommandTest.php', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertNotContains( 'tests/Unit', $processRunner->calls[ 0 ][ 'command' ] );
	}

	public function testMatchingFilterExecutesTheIsolatedOperatorSuite() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$exitCode = $this->newRunner( $processRunner )->run( [ '--filter', 'ReleaseOperatorCommandTest' ], $this->projectRoot );

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 2, $processRunner->calls );
		$this->assertContains( 'tests/Unit/ReleaseOperatorCommandTest.php', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( '--filter', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( 'ReleaseOperatorCommandTest', $processRunner->calls[ 0 ][ 'command' ] );
	}

	public function testMethodFilterExecutesTheIsolatedOperatorSuite() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$this->newRunner( $processRunner )->run( [ '--filter', 'testInteractiveMenuRoutesBuildZipAction' ], $this->projectRoot );

		$this->assertCount( 2, $processRunner->calls );
		$this->assertContains( 'tests/Unit/ReleaseOperatorCommandTest.php', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( 'testInteractiveMenuRoutesBuildZipAction', $processRunner->calls[ 0 ][ 'command' ] );
	}

	public function testFunctionalFlagDoesNotConsumeFollowingFilterForTheIsolatedOperatorSuite() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$this->newRunner( $processRunner )->run(
			[ '--functional', '--filter', 'testInteractiveMenuRoutesBuildZipAction' ],
			$this->projectRoot
		);

		$this->assertCount( 2, $processRunner->calls );
		$this->assertNotContains( '--functional', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( '--filter', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( 'testInteractiveMenuRoutesBuildZipAction', $processRunner->calls[ 0 ][ 'command' ] );
	}

	public function testOptionsOnlyRunExecutesOperatorWithoutParatestWorkerOptions() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$this->newRunner( $processRunner )->run( [ '--processes=2' ], $this->projectRoot );

		$this->assertCount( 2, $processRunner->calls );
		$this->assertContains( 'tests/Unit/ReleaseOperatorCommandTest.php', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertNotContains( '--processes=2', $processRunner->calls[ 0 ][ 'command' ] );
	}

	public function testExplicitOperatorPathExecutesOnlyTheIsolatedOperatorSuite() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$exitCode = $this->newRunner( $processRunner )->run( [ 'tests/Unit/ReleaseOperatorCommandTest.php' ], $this->projectRoot );

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertSame( 1, \count( \array_keys( $processRunner->calls[ 0 ][ 'command' ], 'tests/Unit/ReleaseOperatorCommandTest.php', true ) ) );
	}

	public function testSplitRunsMergeJUnitOutputAndApplyEmptySuiteFailureGlobally() :void {
		$junitPath = $this->createTrackedTempPath( 'shield-unit-junit-', '.xml' );
		$processRunner = new RecordingProcessRunner( [
			[ 'exit_code' => 0, 'junit_class' => 'ReleaseOperatorCommandTest', 'junit_tests' => 30 ],
			[ 'exit_code' => 0, 'junit_class' => 'UnitTestExecutionSelectorTest', 'junit_tests' => 14 ],
		] );
		$exitCode = $this->newRunner( $processRunner )->run(
			[
				'--log-junit',
				$junitPath,
				'tests/Unit/ReleaseOperatorCommandTest.php',
				'tests/Unit/UnitTestExecutionSelectorTest.php',
			],
			$this->projectRoot
		);
		$this->assertSame( 0, $exitCode );
		$xml = \simplexml_load_file( $junitPath );
		$this->assertInstanceOf( \SimpleXMLElement::class, $xml );
		$this->assertSame( '44', (string)$xml[ 'tests' ] );
		$this->assertNotEmpty( $xml->xpath( '//*[contains(@classname, "ReleaseOperatorCommandTest")]' ) );
		$this->assertNotEmpty( $xml->xpath( '//*[contains(@classname, "UnitTestExecutionSelectorTest")]' ) );

		$matched = new RecordingProcessRunner( [
			[ 'exit_code' => 0, 'junit_tests' => 0 ],
			[ 'exit_code' => 0, 'junit_tests' => 14 ],
		] );
		$this->assertSame(
			0,
			$this->newRunner( $matched )->run( [ '--filter', 'UnitTestExecutionSelectorTest', '--fail-on-empty-test-suite' ], $this->projectRoot )
		);

		$empty = new RecordingProcessRunner( [
			[ 'exit_code' => 0, 'junit_tests' => 0 ],
			[ 'exit_code' => 0, 'junit_tests' => 0 ],
		] );
		$this->assertSame(
			1,
			$this->newRunner( $empty )->run( [ '--filter', 'ThisSelectionDoesNotExist', '--fail-on-empty-test-suite' ], $this->projectRoot )
		);
	}

	public function testAutoModeSelectsParatestWrapperWithoutFilter() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$runner = $this->newRunner( $processRunner );

		$exitCode = $runner->run( [ 'tests/Unit/UnitTestExecutionSelectorTest.php' ], $this->projectRoot );

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertContains( './vendor/brianium/paratest/bin/paratest', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( 'WrapperRunner', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( '--processes=1', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertNotContains( '--functional', $processRunner->calls[ 0 ][ 'command' ] );
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
		foreach ( $processRunner->calls as $call ) {
			$this->assertContains( '--processes=1', $call[ 'command' ] );
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
		$this->assertContains( '--functional', $command );
		$this->assertContains( '--filter', $command );
		$this->assertNotContains( './vendor/phpunit/phpunit/phpunit', $command );
		$this->assertNotContains( 'WrapperRunner', $command );
		$this->assertNotContains( '--processes=1', $command );
	}

	public function testDirectoryRunsKeepAutomaticParallelism() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$this->newRunner( $processRunner )->run( [ 'tests/Unit/ActionRouter' ], $this->projectRoot );
		$this->assertContains( '--processes=auto', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertNotContains( '--processes=1', $processRunner->calls[ 0 ][ 'command' ] );
	}

	/** @dataProvider explicitWorkerCounts */
	public function testSingleFileHonoursExplicitWorkerCount( array $workerArgs ) :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$this->newRunner( $processRunner )->run(
			\array_merge( $workerArgs, [ 'tests/Unit/UnitTestExecutionSelectorTest.php' ] ),
			$this->projectRoot
		);
		$command = $processRunner->calls[ 0 ][ 'command' ];
		$this->assertNotContains( '--processes=1', $command );
		foreach ( $workerArgs as $arg ) {
			$this->assertContains( $arg, $command );
		}
	}

	public static function explicitWorkerCounts() :array {
		return [
			'long' => [ [ '--processes', '2' ] ],
			'inline' => [ [ '--processes=2' ] ],
			'auto' => [ [ '--processes=auto' ] ],
			'short' => [ [ '-p', '2' ] ],
			'compact' => [ [ '-p2' ] ],
		];
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
			$this->assertContains( '--functional', $call[ 'command' ] );
			$this->assertContains( '--filter', $call[ 'command' ] );
			$this->assertContains( 'UnitTest', $call[ 'command' ] );
			$this->assertNotContains( 'Runner', $call[ 'command' ] );
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
			$this->assertContains( '--functional', $call[ 'command' ] );
			$this->assertContains( '--filter=UnitTest', $call[ 'command' ] );
			$this->assertNotContains( 'Runner', $call[ 'command' ] );
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
		$this->assertContains( '--functional', $command );
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
