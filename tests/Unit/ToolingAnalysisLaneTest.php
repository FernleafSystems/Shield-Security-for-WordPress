<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PhpSyntaxLintReport;
use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PhpSyntaxLinter;
use FernleafSystems\ShieldPlatform\Tooling\Testing\ToolingAnalysisLane;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use PHPUnit\Framework\TestCase;

class ToolingAnalysisLaneTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = $this->createTrackedTempDir( 'shield-tooling-analysis-' );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testSyntaxFailureStopsBeforePhpStanRuns() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$linter = new class() extends PhpSyntaxLinter {

			public function lint( string $rootDir, array $relativePaths ) :PhpSyntaxLintReport {
				return new PhpSyntaxLintReport( 5, [
					[
						'path' => 'tests/Unit/BadTest.php',
						'output' => 'Parse error: unexpected token',
						'exit_code' => 255,
					],
				] );
			}
		};

		$lane = new ToolingAnalysisLane( $processRunner, $linter );

		$exitCode = $this->runLaneSilenced( $lane );
		$this->assertSame( 1, $exitCode );
		$this->assertCount( 0, $processRunner->calls );
	}

	public function testSuccessfulSyntaxLintRunsPhpStan() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$linter = new class() extends PhpSyntaxLinter {

			public function lint( string $rootDir, array $relativePaths ) :PhpSyntaxLintReport {
				return new PhpSyntaxLintReport( 12, [] );
			}
		};

		$lane = new ToolingAnalysisLane( $processRunner, $linter );

		$exitCode = $this->runLaneSilenced( $lane );
		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$commandString = \implode( ' ', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertStringContainsString( 'phpstan', $commandString );
		$this->assertStringContainsString( 'phpstan.tooling.neon.dist', $commandString );
	}

	private function runLaneSilenced( ToolingAnalysisLane $lane ) :int {
		\ob_start();
		try {
			return $lane->run( $this->projectRoot );
		}
		finally {
			\ob_end_clean();
		}
	}
}
