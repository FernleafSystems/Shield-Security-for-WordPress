<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops\Record as ScanRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\SetScanCompleted;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\{
	BuildScanItems,
	ScanActionVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	Fs,
	Plugins,
	Themes
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\Db;

class ScopedAfsBehaviorTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( 'esc_sql' )->alias( static fn( string $value ) :string => \str_replace( "'", "\\'", $value ) );
		Functions\when( 'path_join' )->alias( fn( string $a, string $b ) :string => $this->normalizePath( \rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' ) ) );
		Functions\when( 'trailingslashit' )->alias( fn( string $path ) :string => \rtrim( $this->normalizePath( $path ), '/' ).'/' );
		Functions\when( 'wp_normalize_path' )->alias( fn( string $path ) :string => $this->normalizePath( $path ) );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_scoped_afs_build_returns_only_the_requested_single_file_plugin() :void {
		$pluginFile = 'single-file.php';
		$pluginPath = $this->normalizePath( WP_PLUGIN_DIR.'/'.$pluginFile );
		\file_put_contents( $pluginPath, "<?php\n" );
		\file_put_contents( $this->normalizePath( WP_PLUGIN_DIR.'/other.php' ), "<?php\n" );

		ServicesState::installItems( [
			'service_wpfs'      => new class extends Fs {
				public function isAccessibleFile( string $file ) :bool {
					return \is_file( $file ) && \is_readable( $file );
				}
			},
			'service_wpplugins' => new class( $pluginFile ) extends Plugins {
				private string $pluginFile;

				public function __construct( string $pluginFile ) {
					$this->pluginFile = $pluginFile;
				}

				public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
					unset( $reload );
					return $file === $this->pluginFile ? new ScopedAfsPluginVo( $file ) : null;
				}
			},
			'service_wpthemes'  => new class extends Themes {
				public function getCurrent() {
						return new class {
							public function get_stylesheet_directory() :string {
								return \str_replace( '\\', '/', WP_CONTENT_DIR.'/themes/current-theme' );
							}
						};
					}
			},
		] );

		$this->installController();

		$action = new ScanActionVO();
		$action->scan = 'afs';
		$action->scope_type = 'plugin';
		$action->scope_key = $pluginFile;
		$action->file_exts = [ 'php' ];

		$items = ( new BuildScanItems() )
			->setScanActionVO( $action )
			->run();

		$this->assertSame( [ \base64_encode( $pluginPath ) ], $items );
	}

	public function test_set_scan_completed_resolves_only_the_matching_asset_scope_for_asset_change_runs() :void {
		$queries = [];
		$this->installController( [
			'db_con' => (object)[
				'scan_results' => new class {
					public function getQuerySelector() :object {
						return new class {
							public function filterByScan( int $scanID ) :self {
								unset( $scanID );
								return $this;
							}

							public function getDistinctForColumn( string $column ) :array {
								unset( $column );
								return [ 11, 12 ];
							}
						};
					}
				},
				'scan_result_items' => new class {
					public function getTable() :string {
						return 'shield_scan_result_items';
					}
				},
			],
		] );

		ServicesState::installItems( [
			'service_wpdb' => new class( $queries ) extends Db {
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

		$record = new ScanRecord();
		$record->scan = 'afs';
		$record->scope_type = 'plugin';
		$record->scope_key = 'akismet/akismet.php';
		$record->run_trigger = 'asset_change';

		$method = new \ReflectionMethod( SetScanCompleted::class, 'resolveStaleItemsForRun' );
		$method->setAccessible( true );
		$method->invoke( new SetScanCompleted(), 5, $record, 1700004000 );

		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( "`resolution_reason`='asset_replaced'", $queries[ 0 ] );
		$this->assertStringContainsString( "`asset_type`='plugin'", $queries[ 0 ] );
		$this->assertStringContainsString( "`asset_key`='akismet/akismet.php'", $queries[ 0 ] );
		$this->assertStringContainsString( 'NOTIN(11,12)', \str_replace( ' ', '', $queries[ 0 ] ) );
	}

	private function installController( array $overrides = [] ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->root_file = WP_PLUGIN_DIR.'/shield/shield.php';
		$controller->cfg = (object)[
			'configuration' => new class {
				public function def( string $key ) :array {
					unset( $key );
					return [];
				}
			},
		];
		$controller->opts = new class {
			public function optGet( string $key ) :array {
				unset( $key );
				return [];
			}
		};
		$controller->comps = (object)[
			'license' => new class {
				public function hasValidWorkingLicense() :bool {
					return true;
				}
			},
			'opts_lookup' => new class {
				public function isScanAutoFilterResults() :bool {
					return false;
				}
			},
			'scans' => new class {
				public function AFS() :object {
					return new class {
						public function getFileScanAreas() :array {
							return [ 'plugins', 'themes', 'wp', 'wpcontent', 'wproot', 'malware_php' ];
						}
					};
				}
			},
		];

		foreach ( $overrides as $property => $value ) {
			$controller->{$property} = $value;
		}

		PluginControllerInstaller::install( $controller );
	}

	private function normalizePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}
}

class ScopedAfsPluginVo extends WpPluginVo {

	public string $file;

	public function __construct( string $file ) {
		$this->file = $file;
	}

	public function getInstallDir() :string {
		return \str_replace( '\\', '/', \rtrim( \dirname( WP_PLUGIN_DIR.'/'.$this->file ), '/\\' ) ).'/';
	}
}
