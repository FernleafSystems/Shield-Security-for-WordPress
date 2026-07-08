<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\{
	Afs as AfsController,
	Base
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScansController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\StartScansResult;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\{
	Db,
	General
};

class ScansControllerStartNewScansTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_sql' )->returnArg();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_start_new_scans_classifies_duplicate_create_unknown_and_unready_outcomes() :void {
		$scansDb = new StartScansFakeScansDb(
			[ 'apc' => 501 ],
			[ 'wpv' ]
		);
		$queue = new StartScansFakeQueue();
		$wpDb = new StartScansFakeWpDb( $scansDb );
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( false ),
			'service_wpdb'      => $wpDb,
			'service_request'   => new UnitTestRequest(),
		] );

		$result = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestScanController( 'afs', true ),
			'apc' => new StartScansTestScanController( 'apc', true ),
			'wpv' => new StartScansTestScanController( 'wpv', true ),
			'bad' => new StartScansTestScanController( 'bad', false ),
		] ) )->startNewScans( [ 'afs', 'apc', 'wpv', 'missing', 'bad', 'afs' ] );

		$this->assertSame( [ 'afs', 'apc', 'wpv', 'missing', 'bad' ], $result->getRequestedSlugs() );
		$this->assertSame( [ 101, 501 ], $result->getStartedScanIDs() );
		$this->assertEqualsCanonicalizing( [
			StartScansResult::REASON_CREATE_FAILED,
			StartScansResult::REASON_UNKNOWN_SCAN,
			StartScansResult::REASON_SCAN_UNAVAILABLE,
		], \array_column( $result->getFailures(), 'reason' ) );
		$this->assertInsertedScanRunTrigger( $scansDb->insertedRecords[ 101 ], 'manual' );
		$this->assertSame( 1, $queue->dispatches );
		$this->assertSame( 1, $queue->watchdogSchedules );
		$this->assertSame( 1, $queue->staleStartBlockerChecks );
		$this->assertSame( 0, $wpDb->writeCount );
		$this->assertSame( 3, $scansDb->duplicateIDQueries );
		$this->assertSame( 0, $scansDb->duplicateCountQueries );
	}

	public function test_reset_ignored_and_dispatch_only_run_for_created_scans() :void {
		$scansDb = new StartScansFakeScansDb( [], [ 'wpv' ] );
		$queue = new StartScansFakeQueue();
		$wpDb = new StartScansFakeWpDb( $scansDb );
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( false ),
			'service_wpdb'      => $wpDb,
			'service_request'   => new UnitTestRequest(),
		] );

		$result = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestScanController( 'afs', true ),
			'wpv' => new StartScansTestScanController( 'wpv', true ),
		] ) )->startNewScans( [ 'afs', 'wpv' ], true );

		$this->assertTrue( $result->isPartialSuccess() );
		$this->assertSame( 1, $queue->dispatches );
		$this->assertSame( 1, $queue->watchdogSchedules );
		$this->assertSame( 1, $wpDb->writeCount );
	}

	public function test_no_dispatch_when_nothing_starts() :void {
		$scansDb = new StartScansFakeScansDb( [], [ 'afs' ] );
		$queue = new StartScansFakeQueue();
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( false ),
			'service_wpdb'      => new StartScansFakeWpDb( $scansDb ),
			'service_request'   => new UnitTestRequest(),
		] );

		$result = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestScanController( 'afs', true ),
		] ) )->startNewScans( [ 'afs' ] );

		$this->assertFalse( $result->hasStarted() );
		$this->assertSame( [ StartScansResult::REASON_CREATE_FAILED ], \array_column( $result->getFailures(), 'reason' ) );
		$this->assertSame( 0, $queue->dispatches );
		$this->assertSame( 0, $queue->watchdogSchedules );
	}

	public function test_active_duplicate_returns_existing_scan_and_keeps_queue_recovery_active() :void {
		$scansDb = new StartScansFakeScansDb( [ 'afs' => 501 ] );
		$queue = new StartScansFakeQueue();
		$wpDb = new StartScansFakeWpDb( $scansDb );
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( true ),
			'service_wpdb'      => $wpDb,
			'service_request'   => new UnitTestRequest(),
		] );

		$result = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestScanController( 'afs', true ),
		] ) )->startNewScans( [ 'afs' ] );

		$this->assertTrue( $result->hasStarted() );
		$this->assertSame( [ 501 ], $result->getStartedScanIDs() );
		$this->assertSame( [], $result->getFailures() );
		$this->assertSame( [], $scansDb->insertedRecords );
		$this->assertSame( 0, $queue->dispatches );
		$this->assertSame( 1, $queue->watchdogSchedules );
		$this->assertSame( 1, $queue->staleStartBlockerChecks );
		$this->assertSame( 1, $wpDb->queueNextChecks );
		$this->assertSame( 2, $scansDb->duplicateIDQueries );
		$this->assertSame( 0, $scansDb->duplicateCountQueries );
	}

	public function test_pure_duplicate_noop_does_not_clear_ignored_items() :void {
		$scansDb = new StartScansFakeScansDb( [ 'afs' => 501 ] );
		$queue = new StartScansFakeQueue();
		$wpDb = new StartScansFakeWpDb( $scansDb );
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( false ),
			'service_wpdb'      => $wpDb,
			'service_request'   => new UnitTestRequest(),
		] );

		$result = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestScanController( 'afs', true ),
		] ) )->startNewScans( [ 'afs' ], true );

		$this->assertTrue( $result->hasStarted() );
		$this->assertSame( [ 501 ], $result->getStartedScanIDs() );
		$this->assertSame( [], $result->getFailures() );
		$this->assertSame( 0, $wpDb->writeCount );
		$this->assertSame( 0, $queue->dispatches );
		$this->assertSame( 1, $queue->watchdogSchedules );
	}

	public function test_mixed_new_scan_plus_duplicate_creates_only_new_row_and_reports_both_accepted() :void {
		$scansDb = new StartScansFakeScansDb( [ 'wpv' => 601 ] );
		$queue = new StartScansFakeQueue();
		$wpDb = new StartScansFakeWpDb( $scansDb );
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( false ),
			'service_wpdb'      => $wpDb,
			'service_request'   => new UnitTestRequest(),
		] );

		$result = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestScanController( 'afs', true ),
			'wpv' => new StartScansTestScanController( 'wpv', true ),
		] ) )->startNewScans( [ 'afs', 'wpv' ], true );

		$this->assertSame( [ 101, 601 ], $result->getStartedScanIDs() );
		$this->assertSame( [], $result->getFailures() );
		$this->assertSame( [ 101 ], \array_keys( $scansDb->insertedRecords ) );
		$this->assertSame( 1, $wpDb->writeCount );
		$this->assertSame( 1, $queue->dispatches );
		$this->assertSame( 1, $queue->watchdogSchedules );
		$this->assertSame( 1, $queue->staleStartBlockerChecks );
		$this->assertSame( 2, $scansDb->duplicateIDQueries );
		$this->assertSame( 0, $scansDb->duplicateCountQueries );
	}

	public function test_multiple_duplicates_share_one_stale_start_watchdog_check() :void {
		$scansDb = new StartScansFakeScansDb( [
			'afs' => 501,
			'apc' => 502,
			'wpv' => 503,
		] );
		$queue = new StartScansFakeQueue();
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( false ),
			'service_wpdb'      => new StartScansFakeWpDb( $scansDb ),
			'service_request'   => new UnitTestRequest(),
		] );

		$result = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestScanController( 'afs', true ),
			'apc' => new StartScansTestScanController( 'apc', true ),
			'wpv' => new StartScansTestScanController( 'wpv', true ),
		] ) )->startNewScans( [ 'afs', 'apc', 'wpv' ] );

		$this->assertSame( [ 501, 502, 503 ], $result->getStartedScanIDs() );
		$this->assertSame( [], $result->getFailures() );
		$this->assertSame( [], $scansDb->insertedRecords );
		$this->assertSame( 1, $queue->staleStartBlockerChecks );
		$this->assertSame( 0, $queue->dispatches );
		$this->assertSame( 1, $queue->watchdogSchedules );
		$this->assertSame( 3, $scansDb->duplicateIDQueries );
		$this->assertSame( 0, $scansDb->duplicateCountQueries );
	}

	public function test_stale_recoverable_duplicate_path_still_resumes_and_clears_ignored() :void {
		$scansDb = new StartScansFakeScansDb( [ 'afs' => 501 ] );
		$queue = new StartScansFakeQueue( [ 'afs' => 501 ] );
		$wpDb = new StartScansFakeWpDb( $scansDb );
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( false ),
			'service_wpdb'      => $wpDb,
			'service_request'   => new UnitTestRequest(),
		] );

		$result = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestScanController( 'afs', true ),
		] ) )->startNewScans( [ 'afs' ], true );

		$this->assertSame( [ 501 ], $result->getStartedScanIDs() );
		$this->assertSame( [], $result->getFailures() );
		$this->assertSame( [], $scansDb->insertedRecords );
		$this->assertSame( 1, $wpDb->writeCount );
		$this->assertSame( 1, $queue->staleStartBlockerChecks );
		$this->assertSame( 2, $scansDb->duplicateIDQueries );
		$this->assertSame( 0, $scansDb->duplicateCountQueries );
	}

	public function test_cron_start_uses_cron_run_trigger() :void {
		$scansDb = new StartScansFakeScansDb();
		$queue = new StartScansFakeQueue();
		$this->installController( $scansDb, $queue, true );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( false ),
			'service_wpdb'      => new StartScansFakeWpDb( $scansDb ),
			'service_request'   => new UnitTestRequest(),
		] );

		$result = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestScanController( 'afs', true ),
		] ) )->startNewScans( [ 'afs' ] );

		$this->assertTrue( $result->hasStarted() );
		$this->assertSame( [ 101 ], $result->getStartedScanIDs() );
		$this->assertInsertedScanRunTrigger( $scansDb->insertedRecords[ 101 ], 'cron' );
		$this->assertSame( 1, $queue->dispatches );
		$this->assertSame( 1, $queue->watchdogSchedules );
	}

	public function test_cli_start_uses_cli_run_trigger_and_processes_without_builder_dispatch() :void {
		$scansDb = new StartScansFakeScansDb();
		$queue = new StartScansFakeQueue();
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( true ),
			'service_wpdb'      => new StartScansFakeWpDb( $scansDb ),
			'service_request'   => new UnitTestRequest(),
		] );

		$result = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestScanController( 'afs', true ),
		] ) )->startNewScans( [ 'afs' ] );

		$this->assertTrue( $result->hasStarted() );
		$this->assertSame( [ 101 ], $result->getStartedScanIDs() );
		$this->assertInsertedScanRunTrigger( $scansDb->insertedRecords[ 101 ], 'cli' );
		$this->assertSame( 0, $queue->dispatches );
		$this->assertSame( 1, $queue->watchdogSchedules );
		$this->assertSame( 0, $queue->staleStartBlockerChecks );
	}

	public function test_afs_asset_change_scan_creation_uses_run_trigger_contract() :void {
		$scansDb = new StartScansFakeScansDb();
		$queue = new StartScansFakeQueue();
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( false ),
			'service_wpdb'      => new StartScansFakeWpDb( $scansDb ),
			'service_request'   => new UnitTestRequest(),
		] );

		$started = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestAfsController( true ),
		] ) )->startAfsAssetScan( 'plugin', 'akismet/akismet.php' );

		$this->assertTrue( $started );
		$this->assertCount( 1, $scansDb->insertedRecords );
		$record = $scansDb->insertedRecords[ 101 ];
		$this->assertSame( 'plugin', $record->scope_type );
		$this->assertSame( 'akismet/akismet.php', $record->scope_key );
		$this->assertInsertedScanRunTrigger( $record, 'asset_change' );
		$this->assertSame( 1, $queue->dispatches );
		$this->assertSame( 1, $queue->watchdogSchedules );
		$this->assertSame( 0, $queue->staleStartBlockerChecks );
		$this->assertSame( [], $queue->staleStartBlockerRuns );
		$this->assertSame( [
			[ 'plugin', 'akismet/akismet.php' ],
		], $scansDb->filterByScopeCalls );
	}

	public function test_wpcli_afs_asset_change_scan_processes_without_builder_dispatch() :void {
		$scansDb = new StartScansFakeScansDb();
		$queue = new StartScansFakeQueue();
		$wpDb = new StartScansFakeWpDb( $scansDb );
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( true ),
			'service_wpdb'      => $wpDb,
			'service_request'   => new UnitTestRequest(),
		] );

		$started = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestAfsController( true ),
		] ) )->startAfsAssetScan( 'plugin', 'akismet/akismet.php' );

		$this->assertTrue( $started );
		$this->assertCount( 1, $scansDb->insertedRecords );
		$record = $scansDb->insertedRecords[ 101 ];
		$this->assertSame( 'plugin', $record->scope_type );
		$this->assertSame( 'akismet/akismet.php', $record->scope_key );
		$this->assertInsertedScanRunTrigger( $record, 'asset_change' );
		$this->assertSame( 0, $queue->dispatches );
		$this->assertSame( 1, $queue->watchdogSchedules );
		$this->assertSame( 1, $wpDb->queueNextChecks );
		$this->assertSame( 0, $queue->staleStartBlockerChecks );
	}

	public function test_afs_core_asset_change_scan_uses_core_scope_contract() :void {
		$scansDb = new StartScansFakeScansDb();
		$queue = new StartScansFakeQueue();
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( false ),
			'service_wpdb'      => new StartScansFakeWpDb( $scansDb ),
			'service_request'   => new UnitTestRequest(),
		] );

		$started = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestAfsController( true ),
		] ) )->startAfsAssetScan( 'core', '' );

		$this->assertTrue( $started );
		$record = $scansDb->insertedRecords[ 101 ];
		$this->assertSame( 'core', $record->scope_type );
		$this->assertSame( 'core', $record->scope_key );
		$this->assertInsertedScanRunTrigger( $record, 'asset_change' );
		$this->assertSame( 1, $queue->dispatches );
		$this->assertSame( 1, $queue->watchdogSchedules );
		$this->assertSame( 0, $queue->staleStartBlockerChecks );
		$this->assertSame( [
			[ 'core', 'core' ],
		], $scansDb->filterByScopeCalls );
	}

	public function test_scoped_asset_duplicate_runs_stale_recovery_with_normalized_scope_and_declines_non_stale() :void {
		$scansDb = new StartScansFakeScansDb( [
			[
				'scan'       => 'afs',
				'id'         => 501,
				'scope_type' => 'plugin',
				'scope_key'  => 'akismet/akismet.php',
			],
		] );
		$queue = new StartScansFakeQueue();
		$wpDb = new StartScansFakeWpDb( $scansDb );
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( true ),
			'service_wpdb'      => $wpDb,
			'service_request'   => new UnitTestRequest(),
		] );

		$started = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestAfsController( true ),
		] ) )->startAfsAssetScan( 'plugin', ' akismet/akismet.php ', true );

		$this->assertFalse( $started );
		$this->assertSame( [], $scansDb->insertedRecords );
		$this->assertSame( 0, $queue->dispatches );
		$this->assertSame( 0, $queue->watchdogSchedules );
		$this->assertSame( 0, $wpDb->queueNextChecks );
		$this->assertSame( 0, $wpDb->writeCount );
		$this->assertSame( [
			[
				'slugs'      => [ 'afs' ],
				'scope_type' => 'plugin',
				'scope_key'  => 'akismet/akismet.php',
			],
		], $queue->staleStartBlockerRuns );
		$this->assertSame( [
			[ 'plugin', 'akismet/akismet.php' ],
		], $scansDb->filterByScopeCalls );
	}

	public function test_stale_asset_duplicate_resumes_when_retry_still_blocked_without_dispatching_builder() :void {
		$scansDb = new StartScansFakeScansDb( [
			[
				'scan'       => 'afs',
				'id'         => 501,
				'scope_type' => 'theme',
				'scope_key'  => 'twentytwentysix',
			],
		] );
		$queue = new StartScansFakeQueue( [
			[
				'scan'       => 'afs',
				'id'         => 501,
				'scope_type' => 'theme',
				'scope_key'  => 'twentytwentysix',
			],
		] );
		$wpDb = new StartScansFakeWpDb( $scansDb );
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( false ),
			'service_wpdb'      => $wpDb,
			'service_request'   => new UnitTestRequest(),
		] );

		$started = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestAfsController( true ),
		] ) )->startAfsAssetScan( 'theme', 'twentytwentysix' );

		$this->assertTrue( $started );
		$this->assertSame( [], $scansDb->insertedRecords );
		$this->assertSame( 0, $queue->dispatches );
		$this->assertSame( 0, $queue->watchdogSchedules );
		$this->assertSame( 0, $wpDb->queueNextChecks );
		$this->assertSame( 2, $scansDb->duplicateIDQueries );
		$this->assertSame( [
			[
				'slugs'      => [ 'afs' ],
				'scope_type' => 'theme',
				'scope_key'  => 'twentytwentysix',
			],
		], $queue->staleStartBlockerRuns );
		$this->assertSame( [
			[ 'theme', 'twentytwentysix' ],
			[ 'theme', 'twentytwentysix' ],
		], $scansDb->filterByScopeCalls );
	}

	public function test_stale_asset_duplicate_replacement_creates_new_scan_and_dispatches_builder() :void {
		$scansDb = new StartScansFakeScansDb( [
			[
				'scan'       => 'afs',
				'id'         => 501,
				'scope_type' => 'plugin',
				'scope_key'  => 'akismet/akismet.php',
			],
		] );
		$queue = new StartScansFakeQueue( [
			[
				'scan'       => 'afs',
				'id'         => 501,
				'scope_type' => 'plugin',
				'scope_key'  => 'akismet/akismet.php',
			],
		] );
		$queue->afterStaleStartBlockerRun = static function () use ( $scansDb ) :void {
			$scansDb->removeExistingScan( 'afs', 'plugin', 'akismet/akismet.php' );
		};
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( false ),
			'service_wpdb'      => new StartScansFakeWpDb( $scansDb ),
			'service_request'   => new UnitTestRequest(),
		] );

		$started = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestAfsController( true ),
		] ) )->startAfsAssetScan( 'plugin', 'akismet/akismet.php' );

		$this->assertTrue( $started );
		$this->assertCount( 1, $scansDb->insertedRecords );
		$record = $scansDb->insertedRecords[ 101 ];
		$this->assertSame( 'afs', $record->scan );
		$this->assertSame( 'plugin', $record->scope_type );
		$this->assertSame( 'akismet/akismet.php', $record->scope_key );
		$this->assertInsertedScanRunTrigger( $record, 'asset_change' );
		$this->assertSame( 1, $queue->dispatches );
		$this->assertSame( 1, $queue->watchdogSchedules );
		$this->assertSame( 2, $scansDb->duplicateIDQueries );
		$this->assertSame( [
			[
				'slugs'      => [ 'afs' ],
				'scope_type' => 'plugin',
				'scope_key'  => 'akismet/akismet.php',
			],
		], $queue->staleStartBlockerRuns );
	}

	public function test_reset_ignored_clears_exact_scope_only_after_accepted_asset_start() :void {
		$scansDb = new StartScansFakeScansDb( [
			[
				'scan'       => 'afs',
				'id'         => 501,
				'scope_type' => 'plugin',
				'scope_key'  => 'akismet/akismet.php',
			],
		] );
		$queue = new StartScansFakeQueue( [
			[
				'scan'       => 'afs',
				'id'         => 501,
				'scope_type' => 'plugin',
				'scope_key'  => 'akismet/akismet.php',
			],
		] );
		$wpDb = new StartScansFakeWpDb( $scansDb );
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( false ),
			'service_wpdb'      => $wpDb,
			'service_request'   => new UnitTestRequest(),
		] );

		$started = ( new StartScansControllerTestDouble( [
			'afs' => new StartScansTestAfsController( true ),
		] ) )->startAfsAssetScan( 'plugin', 'akismet/akismet.php', true );

		$this->assertTrue( $started );
		$this->assertSame( 1, $wpDb->writeCount );
		$this->assertCount( 1, $wpDb->writeQueries );
		$sql = $wpDb->writeQueries[ 0 ];
		$this->assertStringContainsString( "`scan`='afs'", $sql );
		$this->assertStringContainsString( "`asset_type`='plugin'", $sql );
		$this->assertStringContainsString( "`asset_key`='akismet/akismet.php'", $sql );
		$this->assertSame( [], $scansDb->insertedRecords );
		$this->assertSame( 0, $queue->dispatches );
	}

	public function test_invalid_asset_inputs_and_unready_afs_do_not_run_stale_recovery() :void {
		$scansDb = new StartScansFakeScansDb( [
			[
				'scan'       => 'afs',
				'id'         => 501,
				'scope_type' => 'plugin',
				'scope_key'  => 'akismet/akismet.php',
			],
		] );
		$queue = new StartScansFakeQueue( [
			[
				'scan'       => 'afs',
				'id'         => 501,
				'scope_type' => 'plugin',
				'scope_key'  => 'akismet/akismet.php',
			],
		] );
		$this->installController( $scansDb, $queue );
		ServicesState::installItems( [
			'service_wpgeneral' => new StartScansFakeGeneral( false ),
			'service_wpdb'      => new StartScansFakeWpDb( $scansDb ),
			'service_request'   => new UnitTestRequest(),
		] );
		$controller = new StartScansControllerTestDouble( [
			'afs' => new StartScansTestAfsController( false ),
		] );

		$this->assertFalse( $controller->startAfsAssetScan( 'invalid', 'akismet/akismet.php' ) );
		$this->assertFalse( $controller->startAfsAssetScan( 'plugin', ' ' ) );
		$this->assertFalse( $controller->startAfsAssetScan( 'plugin', 'akismet/akismet.php' ) );
		$this->assertSame( 0, $queue->staleStartBlockerChecks );
		$this->assertSame( [], $queue->staleStartBlockerRuns );
		$this->assertSame( [], $scansDb->filterByScopeCalls );
		$this->assertSame( [], $scansDb->insertedRecords );
		$this->assertSame( 0, $queue->dispatches );
		$this->assertSame( 0, $queue->watchdogSchedules );
	}

	private function assertInsertedScanRunTrigger( Record $record, string $expectedRunTrigger ) :void {
		$this->assertSame( $expectedRunTrigger, $record->run_trigger );
		$this->assertArrayHasKey( 'run_trigger', $record->getRawData() );
		$this->assertArrayNotHasKey( 'trigger', $record->getRawData() );
	}

	private function installController( StartScansFakeScansDb $scansDb, StartScansFakeQueue $queue, bool $isScanCron = false ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scans'             => $scansDb,
			'scan_items'        => new class {
				public function getTable() :string {
					return 'scan_items';
				}
			},
			'scan_result_items' => new class {
				public function getTable() :string {
					return 'scan_result_items';
				}
			},
		];
		$controller->opts = new class( $isScanCron ) {
			private bool $isScanCron;

			public function __construct( bool $isScanCron ) {
				$this->isScanCron = $isScanCron;
			}

			public function optGet( string $key ) {
				return $key === 'is_scan_cron' ? $this->isScanCron : false;
			}
		};
		$controller->comps = (object)[
			'scans_queue' => $queue,
			'scans'       => new class {
				public function resetScanResultsCountMemoization() :void {
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}

class StartScansControllerTestDouble extends ScansController {

	private array $scanCons;

	public function __construct( array $scanCons ) {
		$this->scanCons = $scanCons;
	}

	public function getScanCon( string $slug ) {
		return $this->scanCons[ $slug ] ?? null;
	}

	public function canStartScans( bool $isCli = false ) :bool {
		unset( $isCli );
		return true;
	}
}

class StartScansTestScanController extends Base {

	private string $slug;

	private bool $ready;

	public function __construct( string $slug, bool $ready ) {
		$this->slug = $slug;
		$this->ready = $ready;
	}

	public function getSlug() :string {
		return $this->slug;
	}

	public function isReady() :bool {
		return $this->ready;
	}

	protected function newItemActionHandler() {
		return null;
	}

	public function buildScanAction( ?\FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO $scanAction = null ) {
		return $scanAction;
	}

	public function buildScanResult( array $rawResult ) :\FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Record {
		unset( $rawResult );
		return new \FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Record();
	}
}

class StartScansTestAfsController extends AfsController {

	private bool $ready;

	public function __construct( bool $ready ) {
		$this->ready = $ready;
	}

	public function getSlug() :string {
		return 'afs';
	}

	public function isReady() :bool {
		return $this->ready;
	}
}

class StartScansFakeScansDb {

	public int $lastID = 100;

	public array $insertedRecords = [];

	public array $existingScans;
	public array $insertFailures;

	public int $duplicateIDQueries = 0;
	public int $duplicateCountQueries = 0;
	public array $filterByScopeCalls = [];

	public function __construct( array $existingScans = [], array $insertFailures = [] ) {
		$this->existingScans = $this->normalizeExistingScans( $existingScans );
		$this->insertFailures = $insertFailures;
	}

	public function getRecord() :Record {
		return new Record();
	}

	public function getTable() :string {
		return 'scans';
	}

	public function getQueryInserter() :object {
		return new class( $this ) {
			private StartScansFakeScansDb $db;

			public function __construct( StartScansFakeScansDb $db ) {
				$this->db = $db;
			}

			public function insert( Record $record ) :bool {
				if ( \in_array( $record->scan, $this->db->insertFailures, true ) ) {
					return false;
				}
				$this->db->lastID++;
				$this->db->insertedRecords[ $this->db->lastID ] = $record;
				return true;
			}
		};
	}

	public function getQuerySelector() :object {
		return new class( $this ) {
			private string $scan = '';
			private string $scopeType = 'full';
			private string $scopeKey = '';

			private StartScansFakeScansDb $db;

			public function __construct( StartScansFakeScansDb $db ) {
				$this->db = $db;
			}

			public function filterByScan( string $scan ) :self {
				$this->scan = $scan;
				return $this;
			}

			public function filterByScope( string $scopeType, string $scopeKey ) :self {
				$this->scopeType = $scopeType;
				$this->scopeKey = $scopeKey;
				$this->db->filterByScopeCalls[] = [ $scopeType, $scopeKey ];
				return $this;
			}

			public function filterByNotFinished() :self {
				return $this;
			}

			public function filterByStatus( string $status ) :self {
				unset( $status );
				return $this;
			}

			public function addWhereIn( string $column, array $values ) :self {
				unset( $column, $values );
				return $this;
			}

			public function setOrderBy( string $column, string $direction = 'DESC', bool $overwrite = false ) :self {
				unset( $column, $direction, $overwrite );
				return $this;
			}

			public function setColumnsToSelect( array $columns ) :self {
				unset( $columns );
				return $this;
			}

			public function setLimit( int $limit ) :self {
				unset( $limit );
				return $this;
			}

			public function first() {
				$this->db->duplicateIDQueries++;
				if ( $this->scan === '' ) {
					return null;
				}
				$id = $this->db->existingScanID( $this->scan, $this->scopeType, $this->scopeKey );
				if ( $id <= 0 ) {
					return null;
				}
				$record = new Record();
				$record->id = $id;
				$record->scan = $this->scan;
				$record->scope_type = $this->scopeType;
				$record->scope_key = $this->scopeKey;
				return $record;
			}

			public function count() :int {
				$this->db->duplicateCountQueries++;
				return $this->scan !== '' && $this->db->existingScanID( $this->scan, $this->scopeType, $this->scopeKey ) > 0 ? 1 : 0;
			}

			public function byId( int $id ) :Record {
				return $this->db->recordById( $id );
			}
		};
	}

	public function existingScanID( string $scan, string $scopeType = 'full', string $scopeKey = '' ) :int {
		return $this->existingScans[ $this->existingScanIndex( $scan, $scopeType, $scopeKey ) ] ?? 0;
	}

	public function removeExistingScan( string $scan, string $scopeType = 'full', string $scopeKey = '' ) :void {
		unset( $this->existingScans[ $this->existingScanIndex( $scan, $scopeType, $scopeKey ) ] );
	}

	public function recordById( int $id ) :Record {
		if ( isset( $this->insertedRecords[ $id ] ) ) {
			return $this->insertedRecords[ $id ];
		}

		foreach ( $this->existingScans as $index => $existingID ) {
			if ( $existingID === $id ) {
				[ $scan, $scopeType, $scopeKey ] = $this->splitExistingScanIndex( $index );
				$record = new Record();
				$record->id = $id;
				$record->scan = $scan;
				$record->scope_type = $scopeType;
				$record->scope_key = $scopeKey;
				return $record;
			}
		}

		$record = new Record();
		$record->id = $id;
		return $record;
	}

	private function normalizeExistingScans( array $existingScans ) :array {
		$normalized = [];
		$nextID = 500;
		foreach ( $existingScans as $key => $value ) {
			if ( \is_array( $value ) ) {
				$entry = $value;
				$scan = (string)( $entry[ 'scan' ] ?? '' );
				if ( $scan === '' ) {
					continue;
				}
				$scopeType = (string)( $entry[ 'scope_type' ] ?? 'full' );
				$scopeKey = (string)( $entry[ 'scope_key' ] ?? '' );
				$id = (int)( $entry[ 'id' ] ?? 0 );
				if ( $id <= 0 ) {
					$nextID++;
					$id = $nextID;
				}
				$normalized[ $this->existingScanIndex( $scan, $scopeType, $scopeKey ) ] = $id;
			}
			elseif ( \is_string( $key ) ) {
				$normalized[ $this->existingScanIndex( $key ) ] = (int)$value;
			}
			elseif ( \is_string( $value ) ) {
				$nextID++;
				$normalized[ $this->existingScanIndex( $value ) ] = $nextID;
			}
		}
		return $normalized;
	}

	private function existingScanIndex( string $scan, string $scopeType = 'full', string $scopeKey = '' ) :string {
		return \implode( "\0", [ $scan, $scopeType, $scopeKey ] );
	}

	private function splitExistingScanIndex( string $index ) :array {
		return \explode( "\0", $index, 3 );
	}
}

class StartScansFakeQueue {

	public int $dispatches = 0;

	public int $watchdogSchedules = 0;

	public int $staleStartBlockerChecks = 0;

	public array $staleStartBlockers;
	public array $staleStartBlockerRuns = [];
	public ?\Closure $afterStaleStartBlockerRun = null;

	public function __construct( array $staleStartBlockers = [] ) {
		$this->staleStartBlockers = $this->normalizeStaleStartBlockers( $staleStartBlockers );
	}

	public function getQueueBuilder() :object {
		return new class( $this ) {
			private StartScansFakeQueue $queue;

			public function __construct( StartScansFakeQueue $queue ) {
				$this->queue = $queue;
			}

			public function dispatch() :void {
				$this->queue->dispatches++;
			}
		};
	}

	public function getQueueWatchdog() :object {
		return new class( $this ) {
			private StartScansFakeQueue $queue;

			public function __construct( StartScansFakeQueue $queue ) {
				$this->queue = $queue;
			}

			public function scheduleIfActive() :void {
				$this->queue->watchdogSchedules++;
			}

			public function runForStaleStartBlockers( array $slugs, string $scopeType = 'full', string $scopeKey = '' ) :array {
				$this->queue->staleStartBlockerChecks++;
				$this->queue->staleStartBlockerRuns[] = [
					'slugs'      => \array_values( $slugs ),
					'scope_type' => $scopeType,
					'scope_key'  => $scopeKey,
				];
				$blockers = $this->queue->matchingStaleStartBlockers( $slugs, $scopeType, $scopeKey );
				if ( !empty( $blockers ) && $this->queue->afterStaleStartBlockerRun instanceof \Closure ) {
					( $this->queue->afterStaleStartBlockerRun )( $blockers, $slugs, $scopeType, $scopeKey );
				}
				return $blockers;
			}
		};
	}

	public function matchingStaleStartBlockers( array $slugs, string $scopeType = 'full', string $scopeKey = '' ) :array {
		$blockers = [];
		foreach ( $slugs as $slug ) {
			if ( !\is_string( $slug ) || $slug === '' ) {
				continue;
			}
			$id = $this->staleStartBlockers[ $this->staleStartBlockerIndex( $slug, $scopeType, $scopeKey ) ] ?? 0;
			if ( $id > 0 ) {
				$blockers[ $slug ] = $id;
			}
		}
		return $blockers;
	}

	private function normalizeStaleStartBlockers( array $staleStartBlockers ) :array {
		$normalized = [];
		$nextID = 500;
		foreach ( $staleStartBlockers as $key => $value ) {
			if ( \is_array( $value ) ) {
				$entry = $value;
				$scan = (string)( $entry[ 'scan' ] ?? '' );
				if ( $scan === '' ) {
					continue;
				}
				$scopeType = (string)( $entry[ 'scope_type' ] ?? 'full' );
				$scopeKey = (string)( $entry[ 'scope_key' ] ?? '' );
				$id = (int)( $entry[ 'id' ] ?? 0 );
				if ( $id <= 0 ) {
					$nextID++;
					$id = $nextID;
				}
				$normalized[ $this->staleStartBlockerIndex( $scan, $scopeType, $scopeKey ) ] = $id;
			}
			elseif ( \is_string( $key ) ) {
				$normalized[ $this->staleStartBlockerIndex( $key ) ] = (int)$value;
			}
			elseif ( \is_string( $value ) ) {
				$nextID++;
				$normalized[ $this->staleStartBlockerIndex( $value ) ] = $nextID;
			}
		}
		return $normalized;
	}

	private function staleStartBlockerIndex( string $scan, string $scopeType = 'full', string $scopeKey = '' ) :string {
		return \implode( "\0", [ $scan, $scopeType, $scopeKey ] );
	}
}

class StartScansFakeWpDb extends Db {

	public int $writeCount = 0;
	public int $queueNextChecks = 0;
	public array $writeQueries = [];

	private StartScansFakeScansDb $scansDb;

	public function __construct( StartScansFakeScansDb $scansDb ) {
		$this->scansDb = $scansDb;
	}

	public function getVar( $sql ) {
		if ( \stripos( (string)$sql, 'LAST_INSERT_ID()' ) !== false ) {
			return $this->scansDb->lastID;
		}
		if ( \stripos( (string)$sql, 'scan_items' ) !== false ) {
			$this->queueNextChecks++;
		}
		unset( $sql );
		return 0;
	}

	public function doSql( string $sqlQuery ) {
		$this->writeCount++;
		$this->writeQueries[] = $sqlQuery;
		return true;
	}
}

class StartScansFakeGeneral extends General {

	private bool $isCli;

	public function __construct( bool $isCli ) {
		$this->isCli = $isCli;
	}

	public function isWpCli() :bool {
		return $this->isCli;
	}
}
