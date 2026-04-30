<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Results;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\Db;

class UpdateTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( 'esc_sql' )->alias( static fn( string $value ) :string => \str_replace( "'", "\\'", $value ) );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_clear_ignored_within_scope_only_targets_unresolved_items_in_scope() :void {
		$queries = [];
		$resets = $this->installController( $queries );

		( new Update() )
			->setScanController( new class {
				public function getSlug() :string {
					return 'afs';
				}
			} )
			->clearIgnoredWithinScope( 'plugin', 'akismet/akismet.php' );

		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( "`scan`='afs'", $queries[ 0 ] );
		$this->assertStringContainsString( "`resolved_at`=0", $queries[ 0 ] );
		$this->assertStringContainsString( "`asset_type`='plugin'", $queries[ 0 ] );
		$this->assertStringContainsString( "`asset_key`='akismet/akismet.php'", $queries[ 0 ] );
		$this->assertSame( 1, $resets->resets );
	}

	private function installController( array &$queries ) :object {
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
			'service_wpdb'    => new class( $queries ) extends Db {
				public array $queries;

				public function __construct( array &$queries ) {
					$this->queries = &$queries;
				}

				public function doSql( $sql ) :bool {
					$this->queries[] = $sql;
					return true;
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
		];
		$resets = new class {
			public int $resets = 0;

			public function resetScanResultsCountMemoization() :void {
				$this->resets++;
			}
		};
		$controller->comps = (object)[
			'scans' => $resets,
		];

		PluginControllerInstaller::install( $controller );
		return $resets;
	}
}
