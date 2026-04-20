<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Counts;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Db;

class CountsQueryTest extends BaseUnitTest {

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

	public function test_count_affected_plugin_assets_is_memoized_by_counts_facade() :void {
		$queries = [];
		$this->installControllerAndDb( $queries, 5 );

		$counts = new Counts();
		$this->assertSame( 5, $counts->countAffectedPluginAssets() );
		$this->assertSame( 5, $counts->countAffectedPluginAssets() );

		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( 'COUNT(DISTINCT `ri`.`asset_key`)', $queries[ 0 ] );
		$this->assertStringContainsString( "`ri`.`asset_type`='plugin'", $queries[ 0 ] );
	}

	public function test_count_distinct_vulnerable_assets_uses_db_side_distinct_item_count() :void {
		$queries = [];
		$this->installControllerAndDb( $queries, 3 );

		$count = ( new Counts() )->countDistinctVulnerableAssets();

		$this->assertSame( 3, $count );
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( 'COUNT(DISTINCT `ri`.`item_id`)', $queries[ 0 ] );
		$this->assertStringContainsString( "`ri`.`scan`='wpv'", $queries[ 0 ] );
		$this->assertStringContainsString( "`rim`.`meta_key`='is_vulnerable'", $queries[ 0 ] );
		$this->assertStringNotContainsString( 'SELECT DISTINCT', $queries[ 0 ] );
	}

	public function test_count_distinct_vulnerability_review_assets_uses_one_union_query() :void {
		$queries = [];
		$this->installControllerAndDb( $queries, 7 );

		$count = ( new Counts() )->countDistinctVulnerabilityReviewAssets();

		$this->assertSame( 7, $count );
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( 'SELECT COUNT(*) FROM (', $queries[ 0 ] );
		$this->assertStringContainsString( 'UNION', $queries[ 0 ] );
		$this->assertStringContainsString( "`ri`.`scan`='wpv'", $queries[ 0 ] );
		$this->assertStringContainsString( "`ri`.`scan`='apc'", $queries[ 0 ] );
		$this->assertStringContainsString( "`rim`.`meta_key`='is_vulnerable'", $queries[ 0 ] );
		$this->assertStringContainsString( "`rim`.`meta_key`='is_abandoned'", $queries[ 0 ] );
	}

	private function installControllerAndDb( array &$queries, int $dbResult ) :void {
		ServicesState::installItems( [
			'service_wpdb' => new class( $queries, $dbResult ) extends Db {
				public array $queries = [];
				private int $dbResult;

				public function __construct( array &$queries, int $dbResult ) {
					$this->queries = &$queries;
					$this->dbResult = $dbResult;
				}

				public function getVar( $sql ) {
					$this->queries[] = $sql;
					return $this->dbResult;
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
							$ids = [
								'afs' => 101,
								'wpv' => 202,
								'apc' => 303,
							];
							return isset( $ids[ $scanSlug ] ) ? (object)[ 'id' => $ids[ $scanSlug ] ] : null;
						}
					};
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
		$controller->comps = (object)[
			'scans' => new class {
				public function AFS() :object {
					return new class {
						public function getSlug() :string {
							return 'afs';
						}
					};
				}

				public function WPV() :object {
					return new class {
						public function getSlug() :string {
							return 'wpv';
						}
					};
				}

				public function APC() :object {
					return new class {
						public function getSlug() :string {
							return 'apc';
						}
					};
				}
			},
		];
		$controller->opts = new class {
			public function optGet( string $key ) :array {
				unset( $key );
				return [];
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
