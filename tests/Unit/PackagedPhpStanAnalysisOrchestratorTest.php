<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PackagedPhpStanAnalysisOrchestrator;
use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PackagedPhpStanOutcome;
use PHPUnit\Framework\TestCase;

class PackagedPhpStanAnalysisOrchestratorTest extends TestCase {

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
	}

	public function testRunCommandReturnsCleanSuccessForZeroExit() :void {
		$outcome = ( new PackagedPhpStanAnalysisOrchestrator() )->runCommand(
			[ \PHP_BINARY, '-r', 'echo "ok"; exit(0);' ],
			$this->projectRoot,
			static function () :void {
			}
		);

		$this->assertSame( PackagedPhpStanOutcome::STATUS_CLEAN_SUCCESS, $outcome->getStatus() );
		$this->assertSame( 0, $outcome->toExitCode() );
	}

	public function testRunCommandReturnsFindingsSuccessForFileErrorsOnly() :void {
		$outcome = ( new PackagedPhpStanAnalysisOrchestrator() )->runCommand(
			[
				\PHP_BINARY,
				'-r',
				'echo "noise\n{\"totals\":{\"errors\":0,\"file_errors\":2}}\n"; exit(1);',
			],
			$this->projectRoot,
			static function () :void {
			}
		);

		$this->assertSame( PackagedPhpStanOutcome::STATUS_FINDINGS_SUCCESS, $outcome->getStatus() );
		$this->assertSame( 0, $outcome->toExitCode() );
	}

	public function testRunCommandReturnsHardFailureWhenTotalsErrorsExist() :void {
		$outcome = ( new PackagedPhpStanAnalysisOrchestrator() )->runCommand(
			[
				\PHP_BINARY,
				'-r',
				'echo "{\"totals\":{\"errors\":1,\"file_errors\":0}}"; exit(1);',
			],
			$this->projectRoot,
			static function () :void {
			}
		);

		$this->assertSame( PackagedPhpStanOutcome::STATUS_NON_REPORTABLE_FAILURE, $outcome->getStatus() );
		$this->assertSame( 1, $outcome->toExitCode() );
	}

	public function testRunCommandReturnsParseFailureForUnparseableOutput() :void {
		$outcome = ( new PackagedPhpStanAnalysisOrchestrator() )->runCommand(
			[
				\PHP_BINARY,
				'-r',
				'echo "not json"; exit(1);',
			],
			$this->projectRoot,
			static function () :void {
			}
		);

		$this->assertSame( PackagedPhpStanOutcome::STATUS_PARSE_FAILURE, $outcome->getStatus() );
		$this->assertSame( 1, $outcome->toExitCode() );
	}

	public function testRunCommandReturnsHardFailureForNonZeroWithZeroTotals() :void {
		$outcome = ( new PackagedPhpStanAnalysisOrchestrator() )->runCommand(
			[
				\PHP_BINARY,
				'-r',
				'echo "{\"totals\":{\"errors\":0,\"file_errors\":0}}"; exit(1);',
			],
			$this->projectRoot,
			static function () :void {
			}
		);

		$this->assertSame( PackagedPhpStanOutcome::STATUS_NON_REPORTABLE_FAILURE, $outcome->getStatus() );
		$this->assertSame( 1, $outcome->toExitCode() );
	}
}
