<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageScansRun;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\UnitTestPluginUrls;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\Db;

class PageScansRunBehaviorTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( '__' )->returnArg();
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'rawurlencode_deep' )->alias( fn( $value ) => $value );
		Functions\when( 'add_query_arg' )->alias(
			fn( array $args, string $url ) :string => $url.'?'.\http_build_query( $args )
		);
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_scan_run_page_render_data_does_not_mutate_scan_queue_state() :void {
		$dbState = (object)[
			'writes' => 0,
			'reads'  => 0,
		];
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest(),
			'service_wpdb' => new class( $dbState ) extends Db {
				private object $state;

				public function __construct( object $state ) {
					$this->state = $state;
				}

				public function doSql( string $sqlQuery ) {
					unset( $sqlQuery );
					$this->state->writes++;
					return true;
				}

				public function selectCustom( $query, $format = null ) {
					unset( $query, $format );
					$this->state->reads++;
					return [];
				}

				public function getVar( $sql ) {
					unset( $sql );
					$this->state->reads++;
					return null;
				}
			},
		] );
		$this->installController();

		$method = new \ReflectionMethod( PageScansRun::class, 'getRenderData' );
		$method->setAccessible( true );
		$renderData = $method->invoke( new PageScansRun() );

		$this->assertSame( 0, $dbState->writes );
		$this->assertSame( 0, $dbState->reads );
		$this->assertTrue( $renderData[ 'flags' ][ 'can_scan' ] );
		$this->assertSame( [ 'afs', 'wpv', 'apc' ], \array_keys( $renderData[ 'scans' ] ) );
		$this->assertSame( 'choose', $renderData[ 'vars' ][ 'opening_step' ] );
		$this->assertSame( [ true, false, false ], \array_column( $renderData[ 'vars' ][ 'steps' ], 'is_current' ) );
		$this->assertSame( '/admin/scans/run?step=choose', $renderData[ 'hrefs' ][ 'scan_again' ] );
	}

	private function installController() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new UnitTestPluginUrls();
		$scanCons = [];
		foreach ( [ 'afs', 'wpv', 'apc' ] as $slug ) {
			$scanCons[ $slug ] = new PageScansRunTestScanController( $slug );
		}
		$controller->comps = (object)[
			'scans' => new class( $scanCons ) {
				/** @var array<string,PageScansRunTestScanController> */
				private array $scanCons;

				public function __construct( array $scanCons ) {
					$this->scanCons = $scanCons;
				}

				public function getReasonsScansCantExecute() :array {
					return [];
				}

				public function getScanCon( string $slug ) :PageScansRunTestScanController {
					return $this->scanCons[ $slug ];
				}

				public function AFS() :PageScansRunTestScanController {
					return $this->scanCons[ 'afs' ];
				}
			},
			'scans_queue' => new class {
				public function hasRunningScans() :bool {
					return false;
				}
			},
			'site_query' => new class {
				public function latestCompletedScanTimestamps() :array {
					return [
						'malware'         => 0,
						'vulnerabilities' => 0,
						'abandoned'       => 0,
						'core_files'      => 0,
						'plugin_files'    => 0,
						'theme_files'     => 0,
					];
				}
			},
		];
		$controller->opts = new class {
			public function optDef( string $key ) :array {
				unset( $key );
				return [
					'value_options' => [
						[
							'text'      => 'core',
							'value_key' => 'core',
						],
					],
				];
			}

			public function optGet( string $key ) :array {
				unset( $key );
				return [ 'core' ];
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $slug ) :string {
				return $slug;
			}
		};
		PluginControllerInstaller::install( $controller );
	}
}

class PageScansRunTestScanController {

	private string $slug;

	public function __construct( string $slug ) {
		$this->slug = $slug;
	}

	public function getSlug() :string {
		return $this->slug;
	}

	public function getStrings() :array {
		return [];
	}

	public function isReady() :bool {
		return true;
	}

}
