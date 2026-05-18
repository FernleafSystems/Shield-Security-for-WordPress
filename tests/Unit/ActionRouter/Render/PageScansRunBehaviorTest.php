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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Db;

class PageScansRunBehaviorTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( '__' )->returnArg();
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
		$this->assertTrue( $renderData[ 'flags' ][ 'can_scan' ] ?? false );
		$this->assertSame( [ 'afs', 'wpv' ], \array_keys( $renderData[ 'scans' ] ?? [] ) );
	}

	private function installController() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$afs = new PageScansRunTestScanController( 'afs' );
		$wpv = new PageScansRunTestScanController( 'wpv' );
		$controller->comps = (object)[
			'scans' => new class( $afs, $wpv ) {
				private PageScansRunTestScanController $afs;
				private PageScansRunTestScanController $wpv;

				public function __construct( PageScansRunTestScanController $afs, PageScansRunTestScanController $wpv ) {
					$this->afs = $afs;
					$this->wpv = $wpv;
				}

				public function getReasonsScansCantExecute() :array {
					return [];
				}

				public function getAllScanCons() :array {
					return [ $this->afs, $this->wpv ];
				}

				public function AFS() :PageScansRunTestScanController {
					return $this->afs;
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

	public function isRestricted() :bool {
		return false;
	}

	public function isEnabled() :bool {
		return true;
	}
}
