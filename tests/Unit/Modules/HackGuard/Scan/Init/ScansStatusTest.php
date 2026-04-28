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
		$this->assertStringContainsString( "CASE WHEN `scans`.`status` IN ('building','built','running')", $wpdb->queries[ 0 ] );
		$this->assertStringContainsString( '`scans`.`id` ASC', $wpdb->queries[ 0 ] );
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
