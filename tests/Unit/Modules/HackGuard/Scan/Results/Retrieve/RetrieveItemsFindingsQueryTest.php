<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Db;

class RetrieveItemsFindingsQueryTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_retrieve_latest_for_findings_omits_state_exists_filter_when_none_are_requested() :void {
		$queries = [];
		$this->installControllerAndDb( $queries );

		$results = ( new RetrieveItems() )
			->setScanController( $this->newScanController() )
			->retrieveLatestForFindings();

		$this->assertInstanceOf( ResultsSet::class, $results );
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( "`sr`.`scan_ref`=55", $queries[ 0 ] );
		$this->assertStringContainsString( "`ri`.`item_repaired_at`=0", $queries[ 0 ] );
		$this->assertStringContainsString( "`ri`.`item_deleted_at`=0", $queries[ 0 ] );
		$this->assertStringNotContainsString( 'EXISTS (SELECT 1', $queries[ 0 ] );
	}

	public function test_retrieve_latest_for_findings_builds_exists_filters_for_unique_requested_states() :void {
		$queries = [];
		$this->installControllerAndDb( $queries );

		( new RetrieveItems() )
			->setScanController( $this->newScanController() )
			->retrieveLatestForFindings( [ 'is_vulnerable', 'is_abandoned', 'is_vulnerable', '', 'is-bad' ] );

		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( 'EXISTS (SELECT 1', $queries[ 0 ] );
		$this->assertSame( 1, \substr_count( $queries[ 0 ], "meta_key`='is_vulnerable'" ) );
		$this->assertSame( 1, \substr_count( $queries[ 0 ], "meta_key`='is_abandoned'" ) );
		$this->assertSame( 1, \substr_count( $queries[ 0 ], "meta_key`='isbad'" ) );
	}

	private function installControllerAndDb( array &$queries ) :void {
		ServicesState::installItems( [
			'service_wpdb' => new class( $queries ) extends Db {
				public array $queries = [];

				public function __construct( array &$queries ) {
					$this->queries = &$queries;
				}

				public function selectCustom( $query, $format = null ) {
					unset( $format );
					$this->queries[] = $query;
					return [];
				}
			},
		] );

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scans' => new class {
				public function getQuerySelector() {
					return new class {
						public function getLatestForScan( string $scanSlug ) {
							unset( $scanSlug );
							return (object)[ 'id' => 55 ];
						}
					};
				}

				public function getTable() :string {
					return 'shield_scans';
				}
			},
			'scan_results' => new class {
				public function getTable() :string {
					return 'shield_scan_results';
				}
			},
			'scan_result_items' => new class {
				public function getTable() :string {
					return 'shield_scan_result_items';
				}
			},
			'scan_result_item_meta' => new class {
				public function getTable() :string {
					return 'shield_scan_result_item_meta';
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}

	private function newScanController() {
		return new class {
			public function getSlug() :string {
				return 'wpv';
			}

			public function getNewResultsSet() :ResultsSet {
				return new ResultsSet();
			}
		};
	}
}
