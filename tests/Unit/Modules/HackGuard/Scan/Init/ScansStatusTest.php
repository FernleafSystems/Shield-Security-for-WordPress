<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue\Support\ScanQueueLifecycleHarness;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Db;

class ScansStatusTest extends BaseUnitTest {

	private array $servicesSnapshot = [];
	private bool $hadWpdb = false;
	private $wpdbSnapshot;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->hadWpdb = \array_key_exists( 'wpdb', $GLOBALS );
		$this->wpdbSnapshot = $GLOBALS[ 'wpdb' ] ?? null;
		$GLOBALS[ 'wpdb' ] = (object)[ 'last_error' => '' ];
	}

	protected function tearDown() :void {
		if ( $this->hadWpdb ) {
			$GLOBALS[ 'wpdb' ] = $this->wpdbSnapshot;
		}
		else {
			unset( $GLOBALS[ 'wpdb' ] );
		}
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	/**
	 * @dataProvider activeAfsStatusProvider
	 */
	public function test_has_active_afs_detects_every_persisted_active_status( string $status ) :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$harness->insertScan( [
			'scan'   => 'afs',
			'status' => $status,
		] );
		$harness->sql->resetQueryLog();

		$this->assertTrue( ( new ScansStatus() )->hasActiveAfs() );

		$queries = $harness->sql->queryLog();
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( "`scans`.`scan`='afs'", $queries[ 0 ] );
		$this->assertStringContainsString( "`scans`.`status` IN ('queued','building','built','running')", $queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`finished_at`=0', $queries[ 0 ] );
		$this->assertStringContainsString( 'LIMIT 1', $queries[ 0 ] );
	}

	public static function activeAfsStatusProvider() :array {
		return [
			'queued'   => [ 'queued' ],
			'building' => [ 'building' ],
			'built'    => [ 'built' ],
			'running'  => [ 'running' ],
		];
	}

	/**
	 * @dataProvider activeAfsStatusProvider
	 */
	public function test_has_active_scans_detects_every_persisted_active_status( string $status ) :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$harness->insertScan( [
			'scan'   => 'wpv',
			'status' => $status,
		] );
		$harness->sql->resetQueryLog();

		$this->assertTrue( ( new ScansStatus() )->hasActiveScans() );

		$queries = $harness->sql->queryLog();
		$this->assertCount( 1, $queries );
		$this->assertStringNotContainsString( '`scans`.`scan`=', $queries[ 0 ] );
		$this->assertStringContainsString( "`scans`.`status` IN ('queued','building','built','running')", $queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`finished_at`=0', $queries[ 0 ] );
		$this->assertStringContainsString( 'LIMIT 1', $queries[ 0 ] );
	}

	public function test_has_active_scans_is_fresh_and_distinguishes_terminal_rows() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$harness->insertScan( [
			'scan'        => 'wpv',
			'status'      => 'running',
			'finished_at' => 1,
		] );
		$harness->insertScan( [
			'scan'   => 'afs',
			'status' => 'finished',
		] );
		$harness->sql->resetQueryLog();
		$status = new ScansStatus();

		$this->assertFalse( $status->hasActiveScans() );
		$harness->insertScan( [ 'scan' => 'apc', 'status' => 'queued' ] );
		$this->assertTrue( $status->hasActiveScans() );
		$this->assertCount( 2, \array_filter(
			$harness->sql->queryLog(),
			static fn( string $query ) :bool => \strpos( $query, 'SELECT ' ) === 0
		) );
	}

	/**
	 * @dataProvider failedActiveScanQueryProvider
	 */
	public function test_has_active_scans_rejects_failed_query( $result, ?\Throwable $error, string $dbError ) :void {
		( new ScanQueueLifecycleHarness() )->install();
		ServicesState::mergeItems( [
			'service_wpdb' => new ScansStatusResultDb( $result, $error ),
		] );
		$GLOBALS[ 'wpdb' ]->last_error = $dbError;

		$this->expectException( \RuntimeException::class );
		( new ScansStatus() )->hasActiveScans();
	}

	public static function failedActiveScanQueryProvider() :array {
		return [
			'non-array'      => [ false, null, '' ],
			'exception'      => [ [], new \RuntimeException( 'Synthetic query failure.' ), '' ],
			'database error' => [ [], null, 'Synthetic database error.' ],
		];
	}

	public function test_has_active_afs_is_fresh_and_distinguishes_clean_idle() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$harness->insertScan( [
			'scan'   => 'wpv',
			'status' => 'running',
		] );
		$harness->insertScan( [
			'scan'        => 'afs',
			'status'      => 'running',
			'finished_at' => 1,
		] );
		$harness->sql->resetQueryLog();
		$status = new ScansStatus();

		$this->assertFalse( $status->hasActiveAfs() );
		$this->assertFalse( $status->hasActiveAfs() );
		$this->assertCount( 2, $harness->sql->queryLog() );
	}

	public function test_has_active_afs_rejects_non_array_query_result() :void {
		( new ScanQueueLifecycleHarness() )->install();
		ServicesState::mergeItems( [
			'service_wpdb' => new ScansStatusResultDb( false ),
		] );

		$this->expectException( \RuntimeException::class );
		( new ScansStatus() )->hasActiveAfs();
	}

	public function test_has_active_afs_rejects_query_exception() :void {
		( new ScanQueueLifecycleHarness() )->install();
		ServicesState::mergeItems( [
			'service_wpdb' => new ScansStatusResultDb( [], new \RuntimeException( 'Synthetic query failure.' ) ),
		] );

		$this->expectException( \RuntimeException::class );
		( new ScansStatus() )->hasActiveAfs();
	}

	public function test_has_active_afs_rejects_current_database_error() :void {
		( new ScanQueueLifecycleHarness() )->install();
		ServicesState::mergeItems( [
			'service_wpdb' => new ScansStatusResultDb( [] ),
		] );
		$GLOBALS[ 'wpdb' ]->last_error = 'Synthetic database error.';

		$this->expectException( \RuntimeException::class );
		( new ScansStatus() )->hasActiveAfs();
	}

	public function test_snapshot_enqueued_and_active_rows_share_one_ordered_query() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$activeWpvID = $harness->insertScan( [
			'scan'            => 'wpv',
			'status'          => 'built',
			'created_at'      => 20,
			'started_at'      => 20,
			'ready_at'        => 20,
			'last_process_at' => 20,
		] );
		$activeAfsID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'created_at'      => 30,
			'started_at'      => 30,
			'ready_at'        => 30,
			'last_process_at' => 30,
		] );
		$queuedWpvID = $harness->insertScan( [
			'scan'       => 'wpv',
			'status'     => 'queued',
			'created_at' => 10,
		] );
		$harness->insertScan( [
			'scan'        => 'apc',
			'status'      => 'running',
			'finished_at' => 40,
		] );
		$harness->insertScan( [
			'scan'   => 'invalid',
			'status' => 'unknown',
		] );
		$harness->sql->resetQueryLog();

		$status = new ScansStatus();

		$this->assertSame( 'wpv', $status->activeSnapshot()[ 'current' ] );
		$this->assertSame( [ 'wpv', 'afs' ], $status->enqueued() );
		$this->assertSame( [ $activeWpvID, $activeAfsID, $queuedWpvID ], \array_column( $status->activeScans(), 'id' ) );
		$queries = $harness->sql->queryLog();
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( 'SELECT `scans`.`id`,', $queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`scope_type`,', $queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`last_process_at`', $queries[ 0 ] );
		$this->assertStringContainsString( "`scans`.`status` IN ('queued','building','built','running')", $queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`finished_at`=0', $queries[ 0 ] );
		$this->assertStringContainsString( "CASE WHEN `scans`.`status` IN ('building','built','running')", $queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`created_at` ASC', $queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`id` ASC', $queries[ 0 ] );
	}

	public function test_active_scans_returns_normalized_read_model_rows() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$harness->insertScan( [
			'id'         => 0,
			'scan'       => 'bad-id',
			'status'     => 'running',
			'created_at' => 1,
		] );
		$harness->insertScan( [
			'scan'       => '',
			'status'     => 'running',
			'created_at' => 2,
		] );
		$harness->insertScan( [
			'scan'       => 'unknown',
			'status'     => 'unknown',
			'created_at' => 3,
		] );
		$harness->insertScan( [
			'scan'        => 'finished',
			'status'      => 'running',
			'finished_at' => 30,
			'created_at'  => 4,
		] );
		$validID = $harness->insertScan( [
			'scan'            => 'wpv',
			'status'          => 'built',
			'scope_type'      => 'plugin',
			'scope_key'       => 'shield-security',
			'created_at'      => 20,
			'started_at'      => 21,
			'ready_at'        => 22,
			'last_process_at' => 23,
		] );
		$harness->sql->resetQueryLog();

		$status = new ScansStatus();
		$activeScans = $status->activeScans();

		$this->assertSame( [
			[
				'id'              => $validID,
				'scan'            => 'wpv',
				'status'          => 'built',
				'scope_type'      => 'plugin',
				'scope_key'       => 'shield-security',
				'created_at'      => 20,
				'started_at'      => 21,
				'ready_at'        => 22,
				'last_process_at' => 23,
			],
		], $activeScans );
		$this->assertSame( $activeScans, $status->activeScans() );
		$this->assertCount( 1, $harness->sql->queryLog() );
	}

	public function test_active_scans_preserves_duplicate_scan_rows_with_distinct_ids() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$runningID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'scope_type'      => 'plugin',
			'scope_key'       => 'shield-security',
			'created_at'      => 10,
			'started_at'      => 11,
			'ready_at'        => 12,
			'last_process_at' => 13,
		] );
		$queuedID = $harness->insertScan( [
			'scan'       => 'afs',
			'status'     => 'queued',
			'scope_type' => 'theme',
			'scope_key'  => 'twentytwentysix',
			'created_at' => 20,
		] );

		$activeScans = ( new ScansStatus() )->activeScans();

		$this->assertCount( 2, $activeScans );
		$this->assertSame( [ $runningID, $queuedID ], \array_column( $activeScans, 'id' ) );
		$this->assertSame( [ 'afs', 'afs' ], \array_column( $activeScans, 'scan' ) );
		$this->assertSame( [ 'plugin', 'theme' ], \array_column( $activeScans, 'scope_type' ) );
		$this->assertSame( [ 'shield-security', 'twentytwentysix' ], \array_column( $activeScans, 'scope_key' ) );
	}

	/**
	 * @dataProvider activeCurrentStatusProvider
	 */
	public function test_active_snapshot_reports_unfinished_active_status_as_current_before_queued_scan( string $activeStatus ) :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$harness->insertScan( [
			'scan'       => 'afs',
			'status'     => $activeStatus,
			'created_at' => 10,
		] );
		$harness->insertScan( [
			'scan'       => 'wpv',
			'status'     => 'queued',
			'created_at' => 5,
		] );

		$status = ( new ScansStatus() )->activeSnapshot();

		$this->assertSame( 'afs', $status[ 'current' ] );
		$this->assertSame( [ 'afs', 'wpv' ], $status[ 'enqueued' ] );
	}

	public function test_active_snapshot_reports_queued_scan_when_no_started_scan_exists() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$harness->insertScan( [
			'scan'       => 'afs',
			'status'     => 'queued',
			'created_at' => 10,
		] );

		$status = ( new ScansStatus() )->activeSnapshot();

		$this->assertSame( 'afs', $status[ 'current' ] );
		$this->assertSame( [ 'afs' ], $status[ 'enqueued' ] );
	}

	public static function activeCurrentStatusProvider() :array {
		return [
			'building' => [ 'building' ],
			'built'    => [ 'built' ],
			'running'  => [ 'running' ],
		];
	}

	public function test_snapshot_ignores_blank_scan_rows_and_keeps_distinct_enqueued_order() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$harness->insertScan( [
			'scan'       => '',
			'status'     => 'running',
			'created_at' => 5,
		] );
		$harness->insertScan( [
			'scan'       => 'afs',
			'status'     => 'running',
			'created_at' => 10,
		] );
		$harness->insertScan( [
			'scan'       => 'wpv',
			'status'     => 'queued',
			'created_at' => 15,
		] );
		$harness->insertScan( [
			'scan'       => 'afs',
			'status'     => 'queued',
			'created_at' => 20,
		] );
		$harness->sql->resetQueryLog();

		$status = new ScansStatus();

		$this->assertSame( [
			'current'  => 'afs',
			'enqueued' => [ 'afs', 'wpv' ],
		], $status->activeSnapshot() );
		$this->assertSame( [ 'afs', 'wpv' ], $status->enqueued() );
		$this->assertCount( 1, $harness->sql->queryLog() );
	}
}

class ScansStatusResultDb extends Db {

	private $result;
	private ?\Throwable $error;

	public function __construct( $result, ?\Throwable $error = null ) {
		$this->result = $result;
		$this->error = $error;
	}

	public function selectCustom( $query, $format = null ) {
		unset( $query, $format );
		if ( $this->error !== null ) {
			throw $this->error;
		}
		return $this->result;
	}
}
