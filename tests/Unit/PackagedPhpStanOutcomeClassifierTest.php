<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PackagedPhpStanOutcome;
use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PackagedPhpStanOutcomeClassifier;
use PHPUnit\Framework\TestCase;

class PackagedPhpStanOutcomeClassifierTest extends TestCase {

	private PackagedPhpStanOutcomeClassifier $classifier;

	protected function setUp() :void {
		parent::setUp();
		$this->classifier = new PackagedPhpStanOutcomeClassifier();
	}

	public function testClassifyReturnsCleanSuccessForZeroExitCode() :void {
		$outcome = $this->classifier->classify( 0, 'anything at all' );
		$this->assertSame( PackagedPhpStanOutcome::STATUS_CLEAN_SUCCESS, $outcome->getStatus() );
		$this->assertTrue( $outcome->isSuccess() );
		$this->assertSame( 0, $outcome->toExitCode() );
	}

	public function testClassifyReturnsFindingsSuccessWhenOnlyFileErrorsExist() :void {
		$output = <<<TXT
noise before json
{"totals":{"errors":0,"file_errors":3}}
TXT;
		$outcome = $this->classifier->classify( 1, $output );
		$this->assertSame( PackagedPhpStanOutcome::STATUS_FINDINGS_SUCCESS, $outcome->getStatus() );
		$this->assertTrue( $outcome->isSuccess() );
		$this->assertSame( 0, $outcome->toExitCode() );
	}

	public function testClassifyReturnsNonReportableFailureWhenTotalsErrorsExist() :void {
		$output = '{"totals":{"errors":2,"file_errors":3}}';
		$outcome = $this->classifier->classify( 1, $output );
		$this->assertSame( PackagedPhpStanOutcome::STATUS_NON_REPORTABLE_FAILURE, $outcome->getStatus() );
		$this->assertFalse( $outcome->isSuccess() );
		$this->assertSame( 1, $outcome->toExitCode() );
	}

	public function testClassifyReturnsParseFailureForNonJsonOutput() :void {
		$outcome = $this->classifier->classify( 1, 'plain text output with no json envelope' );
		$this->assertSame( PackagedPhpStanOutcome::STATUS_PARSE_FAILURE, $outcome->getStatus() );
		$this->assertFalse( $outcome->isSuccess() );
		$this->assertSame( 1, $outcome->toExitCode() );
	}

	public function testClassifyReturnsParseFailureForMalformedJsonEnvelope() :void {
		$outcome = $this->classifier->classify( 1, '{"totals":' );
		$this->assertSame( PackagedPhpStanOutcome::STATUS_PARSE_FAILURE, $outcome->getStatus() );
		$this->assertFalse( $outcome->isSuccess() );
		$this->assertSame( 1, $outcome->toExitCode() );
	}

	public function testClassifyReturnsParseFailureWhenTotalsMissing() :void {
		$outcome = $this->classifier->classify( 1, '{"files":[]}' );
		$this->assertSame( PackagedPhpStanOutcome::STATUS_PARSE_FAILURE, $outcome->getStatus() );
		$this->assertFalse( $outcome->isSuccess() );
		$this->assertSame( 1, $outcome->toExitCode() );
	}

	public function testClassifyReturnsNonReportableFailureWhenTotalsAreZeroOnNonZeroExit() :void {
		$outcome = $this->classifier->classify( 1, '{"totals":{"errors":0,"file_errors":0}}' );
		$this->assertSame( PackagedPhpStanOutcome::STATUS_NON_REPORTABLE_FAILURE, $outcome->getStatus() );
		$this->assertFalse( $outcome->isSuccess() );
		$this->assertSame( 1, $outcome->toExitCode() );
	}
}
