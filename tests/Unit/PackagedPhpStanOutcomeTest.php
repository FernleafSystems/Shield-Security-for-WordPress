<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PackagedPhpStanOutcome;
use PHPUnit\Framework\TestCase;

class PackagedPhpStanOutcomeTest extends TestCase {

	public function testCleanSuccessMapsToSuccessExitAndMessage() :void {
		$outcome = PackagedPhpStanOutcome::cleanSuccess();
		$this->assertSame( PackagedPhpStanOutcome::STATUS_CLEAN_SUCCESS, $outcome->getStatus() );
		$this->assertTrue( $outcome->isSuccess() );
		$this->assertSame( 0, $outcome->toExitCode() );
		$this->assertStringContainsString( 'completed with no findings', $outcome->toConsoleMessage() );
	}

	public function testFindingsSuccessMapsToSuccessExitAndMessage() :void {
		$outcome = PackagedPhpStanOutcome::findingsSuccess();
		$this->assertSame( PackagedPhpStanOutcome::STATUS_FINDINGS_SUCCESS, $outcome->getStatus() );
		$this->assertTrue( $outcome->isSuccess() );
		$this->assertSame( 0, $outcome->toExitCode() );
		$this->assertStringContainsString( 'findings (informational only)', $outcome->toConsoleMessage() );
	}

	public function testNonReportableFailureMapsToFailureExitAndMessage() :void {
		$outcome = PackagedPhpStanOutcome::nonReportableFailure();
		$this->assertSame( PackagedPhpStanOutcome::STATUS_NON_REPORTABLE_FAILURE, $outcome->getStatus() );
		$this->assertFalse( $outcome->isSuccess() );
		$this->assertSame( 1, $outcome->toExitCode() );
		$this->assertStringContainsString( 'returned non-zero without reportable findings', $outcome->toConsoleMessage() );
	}

	public function testParseFailureMapsToFailureExitAndMessage() :void {
		$outcome = PackagedPhpStanOutcome::parseFailure();
		$this->assertSame( PackagedPhpStanOutcome::STATUS_PARSE_FAILURE, $outcome->getStatus() );
		$this->assertFalse( $outcome->isSuccess() );
		$this->assertSame( 1, $outcome->toExitCode() );
		$this->assertStringContainsString( 'could not be parsed as JSON', $outcome->toConsoleMessage() );
	}
}
