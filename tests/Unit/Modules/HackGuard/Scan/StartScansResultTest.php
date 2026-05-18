<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\StartScansResult;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class StartScansResultTest extends BaseUnitTest {

	public function test_result_normalizes_requested_slugs_and_reports_partial_success() :void {
		$result = StartScansResult::fromRequested( [ 'afs', '', 'afs', 'wpv' ] )
								  ->addStarted( 'afs', 11 )
								  ->addFailure( 'wpv', StartScansResult::REASON_ALREADY_EXISTS );

		$this->assertSame( [ 'afs', 'wpv' ], $result->getRequestedSlugs() );
		$this->assertSame( [ 11 ], $result->getStartedScanIDs() );
		$this->assertSame( [ 'afs' ], $result->getStartedSlugs() );
		$this->assertTrue( $result->hasStarted() );
		$this->assertTrue( $result->hasFailures() );
		$this->assertTrue( $result->isPartialSuccess() );
		$this->assertSame( StartScansResult::CODE_PARTIAL_START, $result->getErrorCode() );
		$this->assertSame( [ StartScansResult::REASON_ALREADY_EXISTS ], \array_column( $result->getFailures(), 'reason' ) );
	}

	public function test_resumed_scans_are_reported_with_started_scan_ids_and_slugs() :void {
		$result = StartScansResult::fromRequested( [ 'afs' ] )
								  ->addResumed( 'afs', 22 );

		$this->assertSame( [ 22 ], $result->getStartedScanIDs() );
		$this->assertSame( [ 'afs' ], $result->getStartedSlugs() );
		$this->assertTrue( $result->hasStarted() );
		$this->assertFalse( $result->hasFailures() );
		$this->assertSame( '', $result->getErrorCode() );
	}

	public function test_failure_log_message_can_ignore_expected_reasons() :void {
		$result = StartScansResult::fromRequested( [ 'afs', 'apc', 'wpv' ] )
								  ->addFailure( 'afs', StartScansResult::REASON_ALREADY_EXISTS )
								  ->addFailure( 'apc', StartScansResult::REASON_ALREADY_EXISTS )
								  ->addFailure( 'wpv', StartScansResult::REASON_CREATE_FAILED );

		$this->assertSame(
			'Shield scan start failures: wpv:create_failed',
			$result->getFailureLogMessage( [ StartScansResult::REASON_ALREADY_EXISTS ] )
		);
		$this->assertSame(
			'',
			StartScansResult::fromRequested( [ 'afs' ] )
							->addFailure( 'afs', StartScansResult::REASON_ALREADY_EXISTS )
							->getFailureLogMessage( [ StartScansResult::REASON_ALREADY_EXISTS ] )
		);
	}

	public function test_result_reports_no_selection_as_machine_code() :void {
		$result = StartScansResult::fromRequested( [] );

		$this->assertFalse( $result->hasRequestedScans() );
		$this->assertFalse( $result->hasStarted() );
		$this->assertSame( StartScansResult::CODE_NO_SCANS_SELECTED, $result->getErrorCode() );
	}
}
