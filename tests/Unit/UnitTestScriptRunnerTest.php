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

	public function testAutoModeSelectsParallelWithoutFilter() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$runner = $this->newRunner( $processRunner );

		$exitCode = $runner->run( [ 'tests/Unit/UnitTestExecutionSelectorTest.php' ], $this->projectRoot );

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertContains( './vendor/brianium/paratest/bin/paratest', $processRunner->calls[ 0 ][ 'command' ] );
	}

	public function testAutoModeRunsMultipleConcretePathsThroughSeparateParatestCommands() :void {
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
		$this->assertContains( './vendor/brianium/paratest/bin/paratest', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( './vendor/brianium/paratest/bin/paratest', $processRunner->calls[ 1 ][ 'command' ] );
		$this->assertSame( 'tests/Unit/UnitTestExecutionSelectorTest.php', $processRunner->calls[ 0 ][ 'command' ][ \count( $processRunner->calls[ 0 ][ 'command' ] ) - 1 ] );
		$this->assertSame( 'tests/Unit/UnitTestScriptRunnerTest.php', $processRunner->calls[ 1 ][ 'command' ][ \count( $processRunner->calls[ 1 ][ 'command' ] ) - 1 ] );
	}

	public function testAutoModeFallsBackToSerialWithFilter() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$runner = $this->newRunner( $processRunner );

		$exitCode = $runner->run(
			[ '--filter', 'testBuildCommandUsesSerialPhpUnitWhenFilterIsPresent', 'tests/Unit/UnitTestExecutionSelectorTest.php' ],
			$this->projectRoot
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertContains( './vendor/phpunit/phpunit/phpunit', $processRunner->calls[ 0 ][ 'command' ] );
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

	public function testExplicitParallelModeUsesParatestEvenWithFilter() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$runner = $this->newRunner( $processRunner );

		$exitCode = $runner->run(
			[
				'--runner-mode',
				'parallel',
				'--filter',
				'testBuildCommandUsesParatestByDefault',
				'tests/Unit/UnitTestExecutionSelectorTest.php',
			],
			$this->projectRoot
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertContains( './vendor/brianium/paratest/bin/paratest', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertNotContains( '--runner-mode', $processRunner->calls[ 0 ][ 'command' ] );
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
}
