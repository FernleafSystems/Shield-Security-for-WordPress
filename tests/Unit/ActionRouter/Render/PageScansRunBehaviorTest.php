<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansProgress;
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
		Functions\when( 'sanitize_text_field' )->returnArg();
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

	public function test_asking_to_choose_scans_opens_on_choosing_without_reading_the_scans() :void {
		$db = $this->installDb( [] );
		$controller = $this->installController( [ 'wpv' ] );

		$renderData = $this->renderPage( [ 'step' => 'choose' ] );

		$this->assertSame( 0, $db->writes );
		$this->assertSame( 0, $db->reads );
		$this->assertSame( 0, $controller->db_con->scans->queries );
		$this->assertSame( 'choose', $renderData[ 'vars' ][ 'opening_step' ] );
		$this->assertSame( '', $renderData[ 'vars' ][ 'progress_html' ] );
	}

	public function test_scan_run_page_opens_on_choosing_when_nothing_is_running_or_just_finished() :void {
		$db = $this->installDb( [] );
		$controller = $this->installController();

		$renderData = $this->renderPage();

		$this->assertSame( 0, $db->writes );
		$this->assertSame( 1, $controller->db_con->scans->queries );
		$this->assertTrue( $renderData[ 'flags' ][ 'can_scan' ] );
		$this->assertSame( [ 'afs', 'wpv', 'apc' ], \array_keys( $renderData[ 'scans' ] ) );
		$this->assertSame( 'choose', $renderData[ 'vars' ][ 'opening_step' ] );
		$this->assertSame( [ true, false, false ], \array_column( $renderData[ 'vars' ][ 'steps' ], 'is_current' ) );
		$this->assertSame( '', $renderData[ 'vars' ][ 'progress_html' ] );
		$this->assertSame( [
			'initiating' => 'rendered-initiating',
			'failed'     => 'rendered-failed',
			'stale'      => 'rendered-stale',
		], $renderData[ 'vars' ][ 'page_states' ] );
		$this->assertSame( "The scan request couldn't be completed.", $controller->action_router->rendered[ 'failed' ][ 'failure_message' ] );
	}

	public function test_scan_run_page_opens_on_the_progress_of_running_scans() :void {
		$this->installDb( [
			[
				'id'              => 31,
				'scan'            => 'afs',
				'status'          => 'running',
				'scope_type'      => 'full',
				'scope_key'       => '',
				'created_at'      => 1,
				'started_at'      => 1,
				'ready_at'        => 1,
				'last_process_at' => 1,
			],
		] );
		$controller = $this->installController();

		$renderData = $this->renderPage();

		$this->assertSame( 'scanning', $renderData[ 'vars' ][ 'opening_step' ] );
		$this->assertSame( [ false, true, false ], \array_column( $renderData[ 'vars' ][ 'steps' ], 'is_current' ) );
		$this->assertSame( 'rendered-running', $renderData[ 'vars' ][ 'progress_html' ] );
		$this->assertSame( [ 31 ], \array_column( $controller->action_router->rendered[ 'running' ][ 'scan_rows' ], 'id' ) );
	}

	public function test_scan_run_page_opens_on_the_result_of_scans_that_just_finished() :void {
		$this->installDb( [] );
		$controller = $this->installController( [ 'apc', 'afs' ] );

		$renderData = $this->renderPage();

		$this->assertSame( 'results', $renderData[ 'vars' ][ 'opening_step' ] );
		$this->assertSame( [ true, true, false ], \array_column( $renderData[ 'vars' ][ 'steps' ], 'is_done' ) );
		$this->assertSame( 'rendered-completed', $renderData[ 'vars' ][ 'progress_html' ] );
		$this->assertSame( [ 'afs', 'apc' ], $controller->action_router->rendered[ 'completed' ][ 'scan_slugs' ] );
	}

	private function renderPage( array $actionData = [] ) :array {
		$method = new \ReflectionMethod( PageScansRun::class, 'getRenderData' );
		$method->setAccessible( true );
		return $method->invoke( new PageScansRun( $actionData ) );
	}

	/**
	 * @param list<array<string,mixed>> $activeScanRows
	 */
	private function installDb( array $activeScanRows ) :object {
		$db = new class( $activeScanRows ) extends Db {
			public int $writes = 0;
			public int $reads = 0;
			private array $activeScanRows;

			public function __construct( array $activeScanRows ) {
				$this->activeScanRows = $activeScanRows;
			}

			public function doSql( string $sqlQuery ) {
				unset( $sqlQuery );
				$this->writes++;
				return true;
			}

			public function selectCustom( $query, $format = null ) {
				unset( $query, $format );
				$this->reads++;
				return $this->activeScanRows;
			}

			public function getVar( $sql ) {
				unset( $sql );
				$this->reads++;
				return null;
			}
		};
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest(),
			'service_wpdb'    => $db,
		] );
		return $db;
	}

	/**
	 * @param list<string> $recentlyFinished scans run by hand that just finished
	 */
	private function installController( array $recentlyFinished = [] ) :Controller {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new UnitTestPluginUrls();
		// No scan has been run by hand recently: asking for one finds nothing, without loading a database handler.
		$controller->db_con = (object)[
			'scans' => new class {
				public function getQuerySelector() :object {
					return new class {
						public function __call( string $method, array $args ) {
							unset( $args );
							return $method === 'queryWithResult' ? [] : $this;
						}
					};
				}
			},
		];
		$scanCons = [];
		foreach ( [ 'afs', 'wpv', 'apc' ] as $slug ) {
			$scanCons[ $slug ] = new PageScansRunTestScanController( $slug );
		}
		$controller->db_con = (object)[
			'scans' => new class( $recentlyFinished ) {
				public int $queries = 0;
				private array $recentlyFinished;

				public function __construct( array $recentlyFinished ) {
					$this->recentlyFinished = $recentlyFinished;
				}

				public function getTable() :string {
					return 'shield_scans';
				}

				/**
				 * The only query the page makes here is for the scans that just finished.
				 */
				public function getQuerySelector() :object {
					return new class( $this ) {
						private object $table;

						public function __construct( object $table ) {
							$this->table = $table;
						}

						public function __call( string $name, array $args ) {
							unset( $args );
							if ( $name !== 'queryWithResult' ) {
								return $this;
							}
							$this->table->queries++;
							return \array_map( static fn( string $scan ) :object => (object)[ 'scan' => $scan ], $this->table->recentlyFinished() );
						}
					};
				}

				public function recentlyFinished() :array {
					return $this->recentlyFinished;
				}
			},
		];
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
				public function getActiveScanProgressRows( array $activeScans ) :array {
					return \array_map( static fn( array $scan ) :array => [
						'id'                   => $scan[ 'id' ],
						'scan'                 => $scan[ 'scan' ],
						'name'                 => 'Scan '.$scan[ 'scan' ],
						'scope_type'           => $scan[ 'scope_type' ],
						'scope_key'            => $scan[ 'scope_key' ],
						'raw_status'           => $scan[ 'status' ],
						'display_status'       => 'running',
						'is_current'           => true,
						'is_stale'             => false,
						'can_attempt_recovery' => false,
						'progress'             => 10,
						'total_items'          => 10,
						'unfinished'           => 9,
					], $activeScans );
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
		$controller->action_router = new class {
			/** @var array<string,array<string,mixed>> */
			public array $rendered = [];

			public function render( string $renderClass, array $data ) :string {
				if ( $renderClass !== ScansProgress::class ) {
					throw new \LogicException( 'Unexpected render: '.$renderClass );
				}
				$this->rendered[ $data[ 'state' ] ] = $data;
				return 'rendered-'.$data[ 'state' ];
			}
		};
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
		return $controller;
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

	public function getFileScanAreas() :array {
		return [ 'core' ];
	}
}
