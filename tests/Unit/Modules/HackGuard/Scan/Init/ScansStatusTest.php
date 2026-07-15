<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue\Support\ScanQueueLifecycleHarness;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};

class ScansStatusTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
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
