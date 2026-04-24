<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Db;

class RetrieveCountQueryTest extends BaseUnitTest {

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
	 *   scan_refs:list<int>,
	 *   ri_columns:list<string>,
	 *   rim_meta_keys:list<string>,
	 *   rim_meta_values:list<string>,
	 *   uses_count_star:bool,
	 *   references_result_meta_table:bool
	 * }
	 */
	private function countQueryFeatures( string $query ) :array {
		$sql = \strtolower( \preg_replace( '/\s+/', ' ', $query ) ?? $query );

		\preg_match_all( '/`sr`\.`scan_ref`=(\d+)/', $sql, $scanRefMatches );
		\preg_match_all( '/`ri`\.`([^`]+)`=/', $sql, $riColumnMatches );
		\preg_match_all( "/`rim`\.`meta_key`='([^']+)'/", $sql, $rimKeyMatches );
		\preg_match_all( "/`rim`\.`meta_value`='([^']+)'/", $sql, $rimValueMatches );

		return [
			'scan_refs'                    => \array_map( '\intval', $scanRefMatches[ 1 ] ?? [] ),
			'ri_columns'                   => \array_values( \array_unique( $riColumnMatches[ 1 ] ?? [] ) ),
			'rim_meta_keys'                => $rimKeyMatches[ 1 ] ?? [],
			'rim_meta_values'              => $rimValueMatches[ 1 ] ?? [],
			'uses_count_star'              => \strpos( $sql, 'count(*)' ) !== false,
			'references_result_meta_table' => \strpos( $sql, 'shield_scan_result_item_meta' ) !== false,
		];
	}

	public function test_count_preserves_existing_wheres_without_accumulating_duplicates() :void {
		$queries = [];
		$this->installControllerAndDb( $queries );

		$retriever = ( new RetrieveCount() )
			->setScanController( $this->newScanController() )
			->addWheres( [
				"`rim`.`meta_key`='ptg_slug'",
				"`rim`.`meta_value`='shield/shield.php'",
			] );

		$this->assertSame( 3, $retriever->count( RetrieveCount::CONTEXT_RESULTS_DISPLAY ) );
		$this->assertSame( 3, $retriever->count( RetrieveCount::CONTEXT_RESULTS_DISPLAY ) );

		$this->assertCount( 2, $queries );
		foreach ( $queries as $query ) {
			$features = $this->countQueryFeatures( $query );
			$this->assertTrue( $features[ 'uses_count_star' ] );
			$this->assertSame( [ 55 ], $features[ 'scan_refs' ] );
			$this->assertSame( [ 'auto_filtered_at', 'ignored_at', 'item_repaired_at', 'item_deleted_at' ], \array_values( \array_intersect(
				[ 'auto_filtered_at', 'ignored_at', 'item_repaired_at', 'item_deleted_at' ],
				$features[ 'ri_columns' ]
			) ) );
			$this->assertSame( [ 'ptg_slug' ], $features[ 'rim_meta_keys' ] );
			$this->assertSame( [ 'shield/shield.php' ], $features[ 'rim_meta_values' ] );
		}
	}

	public function test_count_omits_result_item_meta_join_when_wheres_do_not_reference_meta_alias() :void {
		$queries = [];
		$this->installControllerAndDb( $queries );

		$retriever = ( new RetrieveCount() )
			->setScanController( $this->newScanController() )
			->addWheres( [
				"`ri`.`item_type`='f'",
				"`ri`.`item_id`!=''",
			] );

		$this->assertSame( 3, $retriever->count( RetrieveCount::CONTEXT_RESULTS_DISPLAY ) );

		$this->assertCount( 1, $queries );
		$features = $this->countQueryFeatures( $queries[ 0 ] );
		$this->assertTrue( $features[ 'uses_count_star' ] );
		$this->assertFalse( $features[ 'references_result_meta_table' ] );
		$this->assertContains( 'item_type', $features[ 'ri_columns' ] );
	}

	private function installControllerAndDb( array &$queries ) :void {
		ServicesState::installItems( [
			'service_wpdb' => new class( $queries ) extends Db {
				public array $queries = [];

				public function __construct( array &$queries ) {
					$this->queries = &$queries;
				}

				public function getVar( $query, $x = 0, $y = 0 ) {
					unset( $x, $y );
					$this->queries[] = $query;
					return 3;
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
		$controller->opts = new class {
			public function optGet( string $key ) :array {
				unset( $key );
				return [];
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	private function newScanController() {
		return new class {
			public function getSlug() :string {
				return 'afs';
			}
		};
	}
}
