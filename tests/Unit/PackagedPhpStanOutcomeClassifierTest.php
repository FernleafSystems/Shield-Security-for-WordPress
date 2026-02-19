<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PackagedPhpStanOutcomeClassifier;
use PHPUnit\Framework\TestCase;

class PackagedPhpStanOutcomeClassifierTest extends TestCase {

	private PackagedPhpStanOutcomeClassifier $classifier;

	protected function setUp() :void {
		parent::setUp();
		$this->classifier = new PackagedPhpStanOutcomeClassifier();
	}

	public function testClassifyReturnsCleanSuccessForZeroExitCode() :void {
		$this->assertSame(
			PackagedPhpStanOutcomeClassifier::OUTCOME_CLEAN_SUCCESS,
			$this->classifier->classify( 0, 'anything at all' )
		);
	}

	public function testClassifyReturnsFindingsSuccessWhenOnlyFileErrorsExist() :void {
		$output = <<<TXT
noise before json
{"totals":{"errors":0,"file_errors":3}}
TXT;
		$this->assertSame(
			PackagedPhpStanOutcomeClassifier::OUTCOME_FINDINGS_SUCCESS,
			$this->classifier->classify( 1, $output )
		);
	}

	public function testClassifyReturnsNonReportableFailureWhenTotalsErrorsExist() :void {
		$output = '{"totals":{"errors":2,"file_errors":3}}';
		$this->assertSame(
			PackagedPhpStanOutcomeClassifier::OUTCOME_NON_REPORTABLE_FAILURE,
			$this->classifier->classify( 1, $output )
		);
	}

	public function testClassifyReturnsParseFailureForNonJsonOutput() :void {
		$this->assertSame(
			PackagedPhpStanOutcomeClassifier::OUTCOME_PARSE_FAILURE,
			$this->classifier->classify( 1, 'plain text output with no json envelope' )
		);
	}

	public function testClassifyReturnsParseFailureForMalformedJsonEnvelope() :void {
		$this->assertSame(
			PackagedPhpStanOutcomeClassifier::OUTCOME_PARSE_FAILURE,
			$this->classifier->classify( 1, '{"totals":' )
		);
	}

	public function testClassifyReturnsParseFailureWhenTotalsMissing() :void {
		$this->assertSame(
			PackagedPhpStanOutcomeClassifier::OUTCOME_PARSE_FAILURE,
			$this->classifier->classify( 1, '{"files":[]}' )
		);
	}
}

