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
			[ 'apc' ],
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
		$this->assertSame( [ 101 ], $result->getStartedScanIDs() );
		$this->assertSame( [
			StartScansResult::REASON_ALREADY_EXISTS,
			StartScansResult::REASON_CREATE_FAILED,
			StartScansResult::REASON_UNKNOWN_SCAN,
			StartScansResult::REASON_SCAN_UNAVAILABLE,
		], \array_column( $result->getFailures(), 'reason' ) );
		$this->assertInsertedScanRunTrigger( $scansDb->insertedRecords[ 101 ], 'manual' );
		$this->assertSame( 1, $queue->dispatches );
		$this->assertSame( 1, $queue->watchdogSchedules );
		$this->assertSame( 0, $wpDb->writeCount );
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

	private function installController( StartScansFakeScansDb $scansDb, StartScansFakeQueue $queue ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scans'             => $scansDb,
			'scan_result_items' => new class {
				public function getTable() :string {
					return 'scan_result_items';
				}
			},
		];
		$controller->opts = new class {
			public function optGet( string $key ) {
				unset( $key );
				return false;
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

	public function __construct( array $existingScans = [], array $insertFailures = [] ) {
		$this->existingScans = $existingScans;
		$this->insertFailures = $insertFailures;
	}

	public function getRecord() :Record {
		return new Record();
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

			public function count() :int {
				return \in_array( $this->scan, $this->db->existingScans, true ) ? 1 : 0;
			}

			public function byId( int $id ) :Record {
				$record = new Record();
				$record->id = $id;
				$record->scan = $this->db->insertedRecords[ $id ]->scan ?? '';
				return $record;
			}
		};
	}
}

class StartScansFakeQueue {

	public int $dispatches = 0;

	public int $watchdogSchedules = 0;

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
		};
	}
}

class StartScansFakeWpDb extends Db {

	public int $writeCount = 0;

	private StartScansFakeScansDb $scansDb;

	public function __construct( StartScansFakeScansDb $scansDb ) {
		$this->scansDb = $scansDb;
	}

	public function getVar( $sql ) {
		unset( $sql );
		return $this->scansDb->lastID;
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
