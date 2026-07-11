<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Db;

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
		$wpdb = new ScansStatusWpDbStub( [
			$this->activeScanRow( [
				'id'              => 1,
				'scan'            => 'wpv',
				'status'          => 'built',
				'created_at'      => 20,
				'started_at'      => 20,
				'ready_at'        => 20,
				'last_process_at' => 20,
			] ),
			$this->activeScanRow( [
				'id'              => 2,
				'scan'            => 'afs',
				'status'          => 'running',
				'created_at'      => 30,
				'started_at'      => 30,
				'ready_at'        => 30,
				'last_process_at' => 30,
			] ),
			$this->activeScanRow( [
				'id'         => 3,
				'scan'       => 'wpv',
				'status'     => 'queued',
				'created_at' => 10,
			] ),
		] );

		ServicesState::installItems( [
			'service_wpdb' => $wpdb,
		] );
		$this->installController();

		$status = new ScansStatus();

		$this->assertSame( 'wpv', $status->activeSnapshot()[ 'current' ] );
		$this->assertSame( [ 'wpv', 'afs' ], $status->enqueued() );
		$this->assertSame( [ 1, 2, 3 ], \array_column( $status->activeScans(), 'id' ) );
		$this->assertCount( 1, $wpdb->queries );
		$this->assertStringContainsString( 'SELECT `scans`.`id`,', $wpdb->queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`scope_type`,', $wpdb->queries[ 0 ] );
		$this->assertStringContainsString( "`scans`.`status` IN ('queued','building','built','running')", $wpdb->queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`finished_at`=0', $wpdb->queries[ 0 ] );
		$this->assertStringContainsString( "CASE WHEN `scans`.`status` IN ('building','built','running')", $wpdb->queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`id` ASC', $wpdb->queries[ 0 ] );
	}

	public function test_active_scans_returns_normalized_read_model_rows() :void {
		$wpdb = new ScansStatusWpDbStub( [
			$this->activeScanRow( [
				'id'              => 0,
				'scan'            => 'bad-id',
				'created_at'      => 1,
				'started_at'      => 1,
				'ready_at'        => 1,
				'last_process_at' => 1,
			] ),
			$this->activeScanRow( [
				'id'              => 11,
				'scan'            => '',
				'created_at'      => 2,
				'started_at'      => 2,
				'ready_at'        => 2,
				'last_process_at' => 2,
			] ),
			$this->activeScanRow( [
				'id'     => 13,
				'scan'   => 'unknown',
				'status' => 'unknown',
			] ),
			$this->activeScanRow( [
				'id'              => '12',
				'scan'            => 'wpv',
				'status'          => 'built',
				'scope_type'      => 'plugin',
				'scope_key'       => 'shield-security',
				'created_at'      => '20',
				'started_at'      => '21',
				'ready_at'        => '22',
				'last_process_at' => '23',
			] ),
		] );

		ServicesState::installItems( [
			'service_wpdb' => $wpdb,
		] );
		$this->installController();

		$status = new ScansStatus();
		$activeScans = $status->activeScans();

		$this->assertSame( [
			[
				'id'              => 12,
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
		$this->assertCount( 1, $wpdb->queries );
		$this->assertStringContainsString( 'SELECT `scans`.`id`,', $wpdb->queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`scope_type`,', $wpdb->queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`last_process_at`', $wpdb->queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`finished_at`=0', $wpdb->queries[ 0 ] );
	}

	public function test_active_scans_preserves_duplicate_scan_rows_with_distinct_ids() :void {
		$wpdb = new ScansStatusWpDbStub( [
			$this->activeScanRow( [
				'id'              => 21,
				'scan'            => 'afs',
				'status'          => 'running',
				'scope_type'      => 'plugin',
				'scope_key'       => 'shield-security',
				'created_at'      => 10,
				'started_at'      => 11,
				'ready_at'        => 12,
				'last_process_at' => 13,
			] ),
			$this->activeScanRow( [
				'id'         => 22,
				'scan'       => 'afs',
				'status'     => 'queued',
				'scope_type' => 'theme',
				'scope_key'  => 'twentytwentysix',
				'created_at' => 20,
			] ),
		] );

		ServicesState::installItems( [
			'service_wpdb' => $wpdb,
		] );
		$this->installController();

		$activeScans = ( new ScansStatus() )->activeScans();

		$this->assertCount( 2, $activeScans );
		$this->assertSame( [ 21, 22 ], \array_column( $activeScans, 'id' ) );
		$this->assertSame( [ 'afs', 'afs' ], \array_column( $activeScans, 'scan' ) );
		$this->assertSame( [ 'plugin', 'theme' ], \array_column( $activeScans, 'scope_type' ) );
		$this->assertSame( [ 'shield-security', 'twentytwentysix' ], \array_column( $activeScans, 'scope_key' ) );
		$this->assertCount( 1, $wpdb->queries );
	}

	/**
	 * @dataProvider activeCurrentStatusProvider
	 */
	public function test_active_snapshot_reports_unfinished_active_status_as_current_before_queued_scan( string $activeStatus ) :void {
		$wpdb = new ScansStatusWpDbStub( [
			$this->activeScanRow( [
				'id'         => 1,
				'scan'       => 'afs',
				'status'     => $activeStatus,
				'created_at' => 10,
			] ),
			$this->activeScanRow( [
				'id'         => 2,
				'scan'       => 'wpv',
				'status'     => 'queued',
				'created_at' => 20,
			] ),
		] );

		ServicesState::installItems( [
			'service_wpdb' => $wpdb,
		] );
		$this->installController();

		$status = ( new ScansStatus() )->activeSnapshot();

		$this->assertSame( 'afs', $status[ 'current' ] );
		$this->assertSame( [ 'afs', 'wpv' ], $status[ 'enqueued' ] );
	}

	public function test_active_snapshot_reports_queued_scan_when_no_started_scan_exists() :void {
		$wpdb = new ScansStatusWpDbStub( [
			$this->activeScanRow( [
				'id'         => 1,
				'scan'       => 'afs',
				'status'     => 'queued',
				'created_at' => 10,
			] ),
		] );

		ServicesState::installItems( [
			'service_wpdb' => $wpdb,
		] );
		$this->installController();

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
		$wpdb = new ScansStatusWpDbStub( [
			$this->activeScanRow( [
				'id'         => 1,
				'scan'       => '',
				'status'     => 'running',
				'created_at' => 5,
			] ),
			$this->activeScanRow( [
				'id'         => 2,
				'scan'       => 'afs',
				'status'     => 'running',
				'created_at' => 10,
			] ),
			$this->activeScanRow( [
				'id'         => 3,
				'scan'       => 'wpv',
				'status'     => 'queued',
				'created_at' => 15,
			] ),
			$this->activeScanRow( [
				'id'         => 4,
				'scan'       => 'afs',
				'status'     => 'queued',
				'created_at' => 20,
			] ),
		] );

		ServicesState::installItems( [
			'service_wpdb' => $wpdb,
		] );
		$this->installController();

		$status = new ScansStatus();

		$this->assertSame( [
			'current'  => 'afs',
			'enqueued' => [ 'afs', 'wpv' ],
		], $status->activeSnapshot() );
		$this->assertSame( [ 'afs', 'wpv' ], $status->enqueued() );
		$this->assertCount( 1, $wpdb->queries );
	}

	/**
	 * @return array{id:int|string,scan:string,status:string,scope_type:string,scope_key:string,created_at:int|string,started_at:int|string,ready_at:int|string,last_process_at:int|string}
	 */
	private function activeScanRow( array $overrides = [] ) :array {
		return \array_merge( [
			'id'              => 1,
			'scan'            => 'afs',
			'status'          => 'running',
			'scope_type'      => 'full',
			'scope_key'       => '',
			'created_at'      => 0,
			'started_at'      => 0,
			'ready_at'        => 0,
			'last_process_at' => 0,
		], $overrides );
	}

	private function installController() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scans' => new class {
				public function getTable() :string {
					return 'shield_scans';
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}

class ScansStatusWpDbStub extends Db {

	public array $queries = [];
	private array $rows;

	public function __construct( array $rows ) {
		$this->rows = $rows;
	}

	public function selectCustom( $query, $format = null ) {
		unset( $format );
		$this->queries[] = (string)$query;
		return $this->rows;
	}
}
