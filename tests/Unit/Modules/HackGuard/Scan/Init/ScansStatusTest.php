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

	public function test_current_and_enqueued_share_narrow_active_snapshot_query() :void {
		$wpdb = new class extends Db {
			public array $queries = [];

			public function selectCustom( $query, $format = null ) {
				unset( $format );
				$this->queries[] = (string)$query;
				return [
					[
						'scan'       => 'wpv',
						'status'     => 'built',
						'created_at' => 20,
					],
					[
						'scan'       => 'afs',
						'status'     => 'running',
						'created_at' => 30,
					],
					[
						'scan'       => 'wpv',
						'status'     => 'queued',
						'created_at' => 10,
					],
				];
			}
		};

		ServicesState::installItems( [
			'service_wpdb' => $wpdb,
		] );
		$this->installController();

		$status = new ScansStatus();

		$this->assertSame( 'wpv', $status->current() );
		$this->assertSame( [ 'wpv', 'afs' ], $status->enqueued() );
		$this->assertCount( 1, $wpdb->queries );
		$this->assertStringContainsString( 'SELECT `scans`.`scan`, `scans`.`status`, `scans`.`created_at`', $wpdb->queries[ 0 ] );
		$this->assertStringContainsString( "`scans`.`status` IN ('queued','building','built','running')", $wpdb->queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`finished_at`=0', $wpdb->queries[ 0 ] );
		$this->assertStringContainsString( "CASE WHEN `scans`.`status` IN ('building','built','running')", $wpdb->queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`id` ASC', $wpdb->queries[ 0 ] );
	}

	public function test_active_scans_returns_normalized_read_model_rows() :void {
		$wpdb = new class extends Db {
			public array $queries = [];

			public function selectCustom( $query, $format = null ) {
				unset( $format );
				$this->queries[] = (string)$query;
				return [
					[
						'id'              => 0,
						'scan'            => 'bad-id',
						'status'          => 'running',
						'scope_type'      => 'full',
						'scope_key'       => '',
						'created_at'      => 1,
						'started_at'      => 1,
						'ready_at'        => 1,
						'last_process_at' => 1,
					],
					[
						'id'              => 11,
						'scan'            => '',
						'status'          => 'running',
						'scope_type'      => 'full',
						'scope_key'       => '',
						'created_at'      => 2,
						'started_at'      => 2,
						'ready_at'        => 2,
						'last_process_at' => 2,
					],
					[
						'id'              => '12',
						'scan'            => 'wpv',
						'status'          => 'built',
						'scope_type'      => 'plugin',
						'scope_key'       => 'shield-security',
						'created_at'      => '20',
						'started_at'      => '21',
						'ready_at'        => '22',
						'last_process_at' => '23',
					],
				];
			}
		};

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
		$wpdb = new class extends Db {
			public int $queryCount = 0;

			public function selectCustom( $query, $format = null ) {
				unset( $query, $format );
				$this->queryCount++;
				return [
					[
						'id'              => 21,
						'scan'            => 'afs',
						'status'          => 'running',
						'scope_type'      => 'plugin',
						'scope_key'       => 'shield-security',
						'created_at'      => 10,
						'started_at'      => 11,
						'ready_at'        => 12,
						'last_process_at' => 13,
					],
					[
						'id'              => 22,
						'scan'            => 'afs',
						'status'          => 'queued',
						'scope_type'      => 'theme',
						'scope_key'       => 'twentytwentysix',
						'created_at'      => 20,
						'started_at'      => 0,
						'ready_at'        => 0,
						'last_process_at' => 0,
					],
				];
			}
		};

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
		$this->assertSame( 1, $wpdb->queryCount );
	}

	/**
	 * @dataProvider activeCurrentStatusProvider
	 */
	public function test_active_snapshot_reports_unfinished_active_status_as_current_before_queued_scan( string $activeStatus ) :void {
		$wpdb = new class( $activeStatus ) extends Db {
			private string $activeStatus;

			public function __construct( string $activeStatus ) {
				$this->activeStatus = $activeStatus;
			}

			public function selectCustom( $query, $format = null ) {
				unset( $query, $format );
				return [
					[
						'scan'       => 'afs',
						'status'     => $this->activeStatus,
						'created_at' => 10,
					],
					[
						'scan'       => 'wpv',
						'status'     => 'queued',
						'created_at' => 20,
					],
				];
			}
		};

		ServicesState::installItems( [
			'service_wpdb' => $wpdb,
		] );
		$this->installController();

		$status = ( new ScansStatus() )->activeSnapshot();

		$this->assertSame( 'afs', $status[ 'current' ] );
		$this->assertSame( [ 'afs', 'wpv' ], $status[ 'enqueued' ] );
	}

	public function test_active_snapshot_reports_queued_scan_when_no_started_scan_exists() :void {
		$wpdb = new class extends Db {
			public function selectCustom( $query, $format = null ) {
				unset( $query, $format );
				return [
					[
						'scan'       => 'afs',
						'status'     => 'queued',
						'created_at' => 10,
					],
				];
			}
		};

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
		$wpdb = new class extends Db {
			public int $queryCount = 0;

			public function selectCustom( $query, $format = null ) {
				unset( $query, $format );
				$this->queryCount++;
				return [
					[
						'scan'       => '',
						'status'     => 'running',
						'created_at' => 5,
					],
					[
						'scan'       => 'afs',
						'status'     => 'running',
						'created_at' => 10,
					],
					[
						'scan'       => 'wpv',
						'status'     => 'queued',
						'created_at' => 15,
					],
					[
						'scan'       => 'afs',
						'status'     => 'queued',
						'created_at' => 20,
					],
				];
			}
		};

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
		$this->assertSame( 1, $wpdb->queryCount );
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
