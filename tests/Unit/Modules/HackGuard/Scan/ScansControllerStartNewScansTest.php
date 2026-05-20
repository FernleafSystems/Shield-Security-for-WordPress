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

	public function test_active_duplicate_returns_existing_scan_as_resumed_without_side_effects() :void {
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
		$this->assertSame( 0, $queue->watchdogSchedules );
		$this->assertSame( 1, $queue->staleStartBlockerChecks );
		$this->assertSame( 0, $wpDb->queueNextChecks );
		$this->assertSame( 1, $scansDb->duplicateIDQueries );
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
		$this->assertSame( 0, $queue->watchdogSchedules );
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
		$this->assertSame( 0, $queue->watchdogSchedules );
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

			private StartScansFakeScansDb $db;

			public function __construct( StartScansFakeScansDb $db ) {
				$this->db = $db;
			}

			public function filterByScan( string $scan ) :self {
				$this->scan = $scan;
				return $this;
			}

			public function filterByScope( string $scopeType, string $scopeKey ) :self {
				unset( $scopeType, $scopeKey );
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
				if ( !\array_key_exists( $this->scan, $this->db->existingScans ) ) {
					return null;
				}
				$record = new Record();
				$record->id = $this->db->existingScans[ $this->scan ];
				$record->scan = $this->scan;
				return $record;
			}

			public function count() :int {
				$this->db->duplicateCountQueries++;
				return \array_key_exists( $this->scan, $this->db->existingScans ) ? 1 : 0;
			}

			public function byId( int $id ) :Record {
				$record = new Record();
				$record->id = $id;
				$record->scan = $this->db->insertedRecords[ $id ]->scan ?? '';
				return $record;
			}
		};
	}

	private function normalizeExistingScans( array $existingScans ) :array {
		$normalized = [];
		$nextID = 500;
		foreach ( $existingScans as $key => $value ) {
			if ( \is_string( $key ) ) {
				$normalized[ $key ] = (int)$value;
			}
			elseif ( \is_string( $value ) ) {
				$nextID++;
				$normalized[ $value ] = $nextID;
			}
		}
		return $normalized;
	}
}

class StartScansFakeQueue {

	public int $dispatches = 0;

	public int $watchdogSchedules = 0;

	public int $staleStartBlockerChecks = 0;

	public array $staleStartBlockers;

	public function __construct( array $staleStartBlockers = [] ) {
		$this->staleStartBlockers = $staleStartBlockers;
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
				unset( $scopeType, $scopeKey );
				$this->queue->staleStartBlockerChecks++;
				return \array_intersect_key( $this->queue->staleStartBlockers, \array_flip( $slugs ) );
			}
		};
	}
}

class StartScansFakeWpDb extends Db {

	public int $writeCount = 0;
	public int $queueNextChecks = 0;

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
		unset( $sqlQuery );
		$this->writeCount++;
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
