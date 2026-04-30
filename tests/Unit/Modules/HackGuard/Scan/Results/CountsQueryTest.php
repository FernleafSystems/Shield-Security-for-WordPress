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

	/**
	 * @return array{
	 *   scan_slugs:list<string>,
	 *   rim_meta_keys:list<string>,
	 *   rim_filter_meta_keys:list<string>,
	 *   ri_item_types:list<string>,
	 *   ri_asset_types:list<string>,
	 *   limit:int|null,
	 *   uses_count_distinct_item_id:bool,
	 *   uses_count_star_subquery:bool,
	 *   uses_result_meta_rim_join:bool,
	 *   references_result_meta_table:bool,
	 *   uses_select_distinct:bool,
	 *   uses_union:bool,
	 *   rim_truthy_filter_count:int,
	 *   rim_filter_truthy_filter_count:int
	 * }
	 */
	private function scanCountQueryFeatures( string $query ) :array {
		$sql = \strtolower( \preg_replace( '/\s+/', ' ', $query ) ?? $query );

		\preg_match_all( "/`ri`\.`scan`='([^']+)'/", $sql, $scanSlugMatches );
		\preg_match_all( "/`rim`\.`meta_key`='([^']+)'/", $sql, $rimMetaMatches );
		\preg_match_all( "/`rim_filter`\.`meta_key`='([^']+)'/", $sql, $rimFilterMetaMatches );
		\preg_match_all( "/`ri`\.`item_type`='([^']+)'/", $sql, $itemTypeMatches );
		\preg_match_all( "/`ri`\.`asset_type`='([^']+)'/", $sql, $assetTypeMatches );
		\preg_match( '/\blimit\s+(\d+)/', $sql, $limitMatch );

		return [
			'scan_slugs'                   => $scanSlugMatches[ 1 ] ?? [],
			'rim_meta_keys'                => $rimMetaMatches[ 1 ] ?? [],
			'rim_filter_meta_keys'         => $rimFilterMetaMatches[ 1 ] ?? [],
			'ri_item_types'                => $itemTypeMatches[ 1 ] ?? [],
			'ri_asset_types'               => $assetTypeMatches[ 1 ] ?? [],
			'limit'                        => isset( $limitMatch[ 1 ] ) ? (int)$limitMatch[ 1 ] : null,
			'uses_count_distinct_item_id'   => \strpos( $sql, 'count(distinct `ri`.`item_id`)' ) !== false,
			'uses_count_star_subquery'      => \strpos( $sql, 'select count(*) from (' ) !== false,
			'uses_result_meta_rim_join'     => (bool)\preg_match( '/inner\s+join\s+`shield_scan_result_item_meta`\s+as\s+`rim`/', $sql ),
			'references_result_meta_table'  => \strpos( $sql, 'shield_scan_result_item_meta' ) !== false,
			'uses_select_distinct'          => (bool)\preg_match( '/\bselect\s+distinct\b/', $sql ),
			'uses_union'                    => (bool)\preg_match( '/\bunion\b/', $sql ),
			'rim_truthy_filter_count'       => \substr_count( $sql, '`rim`.`meta_value`=1' ),
			'rim_filter_truthy_filter_count' => \substr_count( $sql, '`rim_filter`.`meta_value`=1' ),
		];
	}

	public function test_count_distinct_vulnerable_assets_uses_db_side_distinct_item_count() :void {
		$queries = [];
		$this->installControllerAndDb( $queries, 3 );

		$count = ( new Counts() )->countDistinctVulnerableAssets();

		$this->assertSame( 3, $count );
		$this->assertCount( 1, $queries );
		$features = $this->scanCountQueryFeatures( $queries[ 0 ] );
		$this->assertTrue( $features[ 'uses_count_distinct_item_id' ] );
		$this->assertSame( [ 'wpv' ], $features[ 'scan_slugs' ] );
		$this->assertSame( [ 'is_vulnerable' ], $features[ 'rim_meta_keys' ] );
		$this->assertSame( 1, $features[ 'rim_truthy_filter_count' ] );
		$this->assertFalse( $features[ 'uses_select_distinct' ] );
	}

	public function test_count_distinct_vulnerability_review_assets_uses_one_union_query() :void {
		$queries = [];
		$this->installControllerAndDb( $queries, 7 );

		$count = ( new Counts() )->countDistinctVulnerabilityReviewAssets();

		$this->assertSame( 7, $count );
		$this->assertCount( 1, $queries );
		$features = $this->scanCountQueryFeatures( $queries[ 0 ] );
		$this->assertTrue( $features[ 'uses_count_star_subquery' ] );
		$this->assertTrue( $features[ 'uses_union' ] );
		$this->assertSame( [ 'wpv', 'apc' ], $features[ 'scan_slugs' ] );
		$this->assertSame( [ 'is_vulnerable', 'is_abandoned' ], $features[ 'rim_meta_keys' ] );
		$this->assertSame( 2, $features[ 'rim_truthy_filter_count' ] );
	}

	public function test_file_area_counts_keep_metadata_semantics_without_result_meta_join() :void {
		$queries = [];
		$this->installControllerAndDb( $queries, 4 );

		$counts = new Counts();

		$this->assertSame( 4, $counts->countWPFiles() );
		$this->assertSame( 4, $counts->countPluginFiles() );
		$this->assertSame( 4, $counts->countThemeFiles() );

		$this->assertCount( 3, $queries );
		$features = \array_map( fn( string $query ) :array => $this->scanCountQueryFeatures( $query ), $queries );
		$this->assertSame( [ 'f' ], $features[ 0 ][ 'ri_item_types' ] );
		$this->assertSame( [ 'is_in_core' ], $features[ 0 ][ 'rim_filter_meta_keys' ] );
		$this->assertSame( [ 'is_in_plugin' ], $features[ 1 ][ 'rim_filter_meta_keys' ] );
		$this->assertSame( [ 'is_in_theme' ], $features[ 2 ][ 'rim_filter_meta_keys' ] );
		foreach ( $features as $feature ) {
			$this->assertSame( 1, $feature[ 'rim_filter_truthy_filter_count' ] );
			$this->assertFalse( $feature[ 'uses_result_meta_rim_join' ] );
		}
	}

	public function test_meta_filtered_counts_keep_result_meta_join() :void {
		$queries = [];
		$this->installControllerAndDb( $queries, 2 );

		$this->assertSame( 2, ( new Counts() )->countMalware() );

		$this->assertCount( 1, $queries );
		$features = $this->scanCountQueryFeatures( $queries[ 0 ] );
		$this->assertTrue( $features[ 'uses_result_meta_rim_join' ] );
		$this->assertSame( [ 'is_mal' ], $features[ 'rim_meta_keys' ] );
		$this->assertSame( 1, $features[ 'rim_truthy_filter_count' ] );
	}

	public function test_admin_bar_uses_bounded_count_without_result_meta_join_when_exact_counts_are_cold() :void {
		$queries = [];
		$this->installControllerAndDb( $queries, 100 );

		$summary = ( new Counts() )->adminBarScanSummary();

		$this->assertTrue( $summary[ 'is_capped' ] );
		$this->assertSame( 99, $summary[ 'total' ] );
		$this->assertSame( [], $summary[ 'counts' ] );
		$this->assertCount( 1, $queries );
		$features = $this->scanCountQueryFeatures( $queries[ 0 ] );
		$this->assertSame( 100, $features[ 'limit' ] );
		$this->assertFalse( $features[ 'references_result_meta_table' ] );
	}

	public function test_admin_bar_uses_warm_exact_counts_when_available() :void {
		$queries = [];
		$this->installControllerAndDb( $queries, 1 );

		$counts = new Counts();
		$counts->all();
		$queryCountAfterPriming = \count( $queries );
		$summary = $counts->adminBarScanSummary();

		$this->assertFalse( $summary[ 'is_capped' ] );
		$this->assertSame( 6, $summary[ 'total' ] );
		$this->assertSame( 1, $summary[ 'counts' ][ 'malware' ] );
		$this->assertCount( $queryCountAfterPriming, $queries );
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

						public function isEnabled() :bool {
							return true;
						}
					};
				}

				public function WPV() :object {
					return new class {
						public function getSlug() :string {
							return 'wpv';
						}

						public function isEnabled() :bool {
							return true;
						}
					};
				}

				public function APC() :object {
					return new class {
						public function getSlug() :string {
							return 'apc';
						}

						public function isEnabled() :bool {
							return true;
						}
					};
				}

				public function getAllScanCons() :array {
					return [
						'afs' => $this->AFS(),
						'wpv' => $this->WPV(),
						'apc' => $this->APC(),
					];
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
