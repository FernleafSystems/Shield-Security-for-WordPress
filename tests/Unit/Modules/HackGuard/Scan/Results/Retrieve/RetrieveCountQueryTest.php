<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

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
	 *   scan_slugs:list<string>,
	 *   ri_columns:list<string>,
	 *   rim_meta_keys:list<string>,
	 *   rim_meta_values:list<string>,
	 *   uses_count_star:bool,
	 *   references_result_meta_table:bool
	 * }
	 */
	private function countQueryFeatures( string $query ) :array {
		$sql = \strtolower( \preg_replace( '/\s+/', ' ', $query ) ?? $query );

		\preg_match_all( "/`ri`\.`scan`='([^']+)'/", $sql, $scanSlugMatches );
		\preg_match_all( '/`ri`\.`([^`]+)`\s*(?:!?=|[<>]=?)/', $sql, $riColumnMatches );
		\preg_match_all( "/`rim`\.`meta_key`='([^']+)'/", $sql, $rimKeyMatches );
		\preg_match_all( "/`rim`\.`meta_value`='([^']+)'/", $sql, $rimValueMatches );

		return [
			'scan_slugs'                   => $scanSlugMatches[ 1 ] ?? [],
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
			$this->assertSame( [ 'afs' ], $features[ 'scan_slugs' ] );
			$this->assertSame( [ 'scan', 'auto_filtered_at', 'resolution_reason', 'ignored_at', 'resolved_at' ], \array_values( \array_intersect(
				[ 'scan', 'auto_filtered_at', 'resolution_reason', 'ignored_at', 'resolved_at' ],
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
