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
			$this->assertStringContainsString( 'COUNT(*)', $query );
			$this->assertStringContainsString( "`sr`.`scan_ref`=55", $query );
			$this->assertStringContainsString( "`ri`.`auto_filtered_at`=0", $query );
			$this->assertStringContainsString( "`ri`.`ignored_at`=0", $query );
			$this->assertStringContainsString( "`ri`.`item_repaired_at`=0", $query );
			$this->assertStringContainsString( "`ri`.`item_deleted_at`=0", $query );
			$this->assertStringContainsString( "`rim`.`meta_key`='ptg_slug'", $query );
			$this->assertStringContainsString( "`rim`.`meta_value`='shield/shield.php'", $query );
			$this->assertSame( 1, \substr_count( $query, "`rim`.`meta_key`='ptg_slug'" ) );
			$this->assertSame( 1, \substr_count( $query, "`rim`.`meta_value`='shield/shield.php'" ) );
		}
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
