<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\{
	RetrieveCount,
	RetrieveGroupedAssetSummaries
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Db;

class RetrieveGroupedAssetSummariesQueryTest extends BaseUnitTest {

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

	public function test_retrieve_uses_grouped_asset_query_with_results_display_filters() :void {
		$queries = [];
		$this->installControllerAndDb( $queries, [
			[
				'slug'       => 'example-plugin/example-plugin.php',
				'file_count' => 2,
			],
		] );

		$rows = ( new RetrieveGroupedAssetSummaries() )->retrieve( 'plugin', [
			'include_ignored' => true,
			'ignored_only'    => true,
		] );

		$this->assertSame( [
			[
				'slug'       => 'example-plugin/example-plugin.php',
				'file_count' => 2,
			],
		], $rows );
		$this->assertCount( 1, $queries[ 'select' ] );
		$this->assertStringContainsString( "SELECT `ri`.`asset_key` AS `slug`, COUNT(DISTINCT `ri`.`id`) AS `file_count`", $queries[ 'select' ][ 0 ] );
		$this->assertStringContainsString( "`ri`.`asset_type`='plugin'", $queries[ 'select' ][ 0 ] );
		$this->assertStringContainsString( "`ri`.`asset_key`!=''", $queries[ 'select' ][ 0 ] );
		$this->assertStringContainsString( "`ri`.`auto_filtered_at`=0", $queries[ 'select' ][ 0 ] );
		$this->assertStringContainsString( "`ri`.`ignored_at`>0", $queries[ 'select' ][ 0 ] );
		$this->assertStringContainsString( 'GROUP BY `ri`.`asset_key`', $queries[ 'select' ][ 0 ] );
		$this->assertStringContainsString( 'ORDER BY `file_count` DESC, `ri`.`asset_key` ASC', $queries[ 'select' ][ 0 ] );
	}

	public function test_count_for_context_uses_same_afs_joins_with_context_filters() :void {
		$queries = [];
		$this->installControllerAndDb( $queries, 4 );

		$count = ( new RetrieveGroupedAssetSummaries() )
			->countForContext( 'plugin', RetrieveCount::CONTEXT_ACTIVE_PROBLEMS );

		$this->assertSame( 4, $count );
		$this->assertCount( 1, $queries[ 'count' ] );
		$this->assertStringContainsString( 'SELECT COUNT(DISTINCT `ri`.`asset_key`)', $queries[ 'count' ][ 0 ] );
		$this->assertStringContainsString( "`ri`.`asset_type`='plugin'", $queries[ 'count' ][ 0 ] );
		$this->assertStringContainsString( "`ri`.`asset_key`!=''", $queries[ 'count' ][ 0 ] );
		$this->assertStringContainsString( "`ri`.`scan`='afs'", $queries[ 'count' ][ 0 ] );
		$this->assertStringContainsString( "`ri`.`auto_filtered_at`=0", $queries[ 'count' ][ 0 ] );
		$this->assertStringContainsString( "`ri`.`ignored_at`=0", $queries[ 'count' ][ 0 ] );
		$this->assertStringNotContainsString( 'GROUP BY', $queries[ 'count' ][ 0 ] );
	}

	/**
	 * @param array{select:list<string>,count:list<string>} $queries
	 * @param list<array{slug:string,file_count:int}>|int $dbResult
	 */
	private function installControllerAndDb( array &$queries, $dbResult ) :void {
		ServicesState::installItems( [
			'service_wpdb' => new class( $queries, $dbResult ) extends Db {
				public array $queries = [];
				/** @var list<array{slug:string,file_count:int}>|int */
				private $dbResult;

				/**
				 * @param array{select:list<string>,count:list<string>} $queries
				 * @param list<array{slug:string,file_count:int}>|int $dbResult
				 */
				public function __construct( array &$queries, $dbResult ) {
					$this->queries = &$queries;
					$this->dbResult = $dbResult;
				}

				public function selectCustom( $query, $format = null ) {
					unset( $format );
					$this->queries[ 'select' ][] = $query;
					return \is_array( $this->dbResult ) ? $this->dbResult : [];
				}

				public function getVar( $sql ) {
					$this->queries[ 'count' ][] = $sql;
					return \is_int( $this->dbResult ) ? $this->dbResult : 0;
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
							return (object)[ 'id' => 101 ];
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
			},
		];
		$controller->opts = new class {
			public function optGet( string $key ) :array {
				unset( $key );
				return [];
			}
		};

		$queries = [
			'select' => [],
			'count'  => [],
		];

		PluginControllerInstaller::install( $controller );
	}
}
