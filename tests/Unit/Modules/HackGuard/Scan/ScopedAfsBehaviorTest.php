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
	ServicesState,
	WrittenFixtureFiles
};
use FernleafSystems\Wordpress\Services\Core\{
	Fs,
	Plugins,
	Themes
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\Db;

class ScopedAfsBehaviorTest extends BaseUnitTest {

	use WrittenFixtureFiles;

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
		$this->removeWrittenFixtureFiles();
		parent::tearDown();
	}

	public function test_scoped_afs_build_returns_only_the_requested_single_file_plugin() :void {
		$pluginFile = 'single-file.php';
		$pluginPath = $this->normalizePath( WP_PLUGIN_DIR.'/'.$pluginFile );
		$this->writeFile( $pluginPath, "<?php\n" );
		$this->writeFile( $this->normalizePath( WP_PLUGIN_DIR.'/other.php' ), "<?php\n" );

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
					public function getTable() :string {
						return 'shield_scan_results';
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
		$this->assertStringContainsString( 'NOTEXISTS', \str_replace( ' ', '', $queries[ 0 ] ) );
		$this->assertStringContainsString( 'shield_scan_results', $queries[ 0 ] );
	}

	public function test_core_scope_builds_wordpress_core_roots() :void {
		$this->installController();

		$action = new ScanActionVO();
		$action->scan = 'afs';
		$action->scope_type = 'core';
		$action->scope_key = 'core';

		$method = new \ReflectionMethod( BuildScanItems::class, 'buildScopedRootDirs' );
		$method->setAccessible( true );
		$rootDirs = $method->invoke( new BuildScanItems(), $action );

		$this->assertSame( 1, $rootDirs[ ABSPATH ] ?? null );
		$this->assertSame( 0, $rootDirs[ path_join( ABSPATH, WPINC ) ] ?? null );
		$this->assertSame( 0, $rootDirs[ path_join( ABSPATH, 'wp-admin' ) ] ?? null );
	}

	public function test_core_scope_builds_wp_roots_only_when_wproot_area_is_disabled() :void {
		$rootDirs = $this->buildCoreScopedRootDirs( [ 'wp' ], true );

		$this->assertArrayNotHasKey( ABSPATH, $rootDirs );
		$this->assertSame( 0, $rootDirs[ path_join( ABSPATH, WPINC ) ] ?? null );
		$this->assertSame( 0, $rootDirs[ path_join( ABSPATH, 'wp-admin' ) ] ?? null );
	}

	public function test_core_scope_builds_wproot_only_when_wp_area_is_disabled_and_cap_allows() :void {
		$rootDirs = $this->buildCoreScopedRootDirs( [ 'wproot' ], true );

		$this->assertSame( [ ABSPATH => 1 ], $rootDirs );
	}

	public function test_core_scope_omits_wproot_when_cap_denied() :void {
		$rootDirs = $this->buildCoreScopedRootDirs( [ 'wp', 'wproot' ], false );

		$this->assertArrayNotHasKey( ABSPATH, $rootDirs );
		$this->assertSame( 0, $rootDirs[ path_join( ABSPATH, WPINC ) ] ?? null );
		$this->assertSame( 0, $rootDirs[ path_join( ABSPATH, 'wp-admin' ) ] ?? null );
	}

	public function test_set_scan_completed_resolves_only_core_modified_or_missing_asset_scope_findings() :void {
		$queries = [];
		$this->installController( [
			'db_con' => (object)[
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
		$record->scope_type = 'core';
		$record->scope_key = 'core';
		$record->run_trigger = 'asset_change';

		$method = new \ReflectionMethod( SetScanCompleted::class, 'resolveStaleItemsForRun' );
		$method->setAccessible( true );
		$method->invoke( new SetScanCompleted(), 5, $record, 1700004000 );

		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( "`resolution_reason`='asset_replaced'", $queries[ 0 ] );
		$this->assertStringContainsString( "`asset_type`='core'", $queries[ 0 ] );
		$this->assertStringContainsString( "`asset_key`='core'", $queries[ 0 ] );
		$this->assertStringContainsString( "`rim_scope`.`meta_key` IN ('is_checksumfail','is_missing')", $queries[ 0 ] );
		$this->assertStringContainsString( "`rim_scope`.`meta_value`!=''", $queries[ 0 ] );
		$this->assertStringContainsString( "`rim_scope`.`meta_value`!='0'", $queries[ 0 ] );
		foreach ( [ 'is_in_wpcontent', 'is_in_wproot', 'is_unrecognised', 'is_unidentified', 'is_mal' ] as $unresolvedMetaKey ) {
			$this->assertStringNotContainsString( $unresolvedMetaKey, $queries[ 0 ] );
		}
		$normalizedSql = \preg_replace( '/\s+/', '', $queries[ 0 ] );
		$this->assertStringContainsString( 'ANDEXISTS(', $normalizedSql );
		$this->assertStringContainsString( 'ANDNOTEXISTS(', $normalizedSql );
	}

	private function buildCoreScopedRootDirs( array $scanAreas, bool $canScanAllFiles ) :array {
		$this->installController( [], $scanAreas, $canScanAllFiles );

		$action = new ScanActionVO();
		$action->scan = 'afs';
		$action->scope_type = 'core';
		$action->scope_key = 'core';

		$method = new \ReflectionMethod( BuildScanItems::class, 'buildScopedRootDirs' );
		$method->setAccessible( true );
		return $method->invoke( new BuildScanItems(), $action );
	}

	private function installController(
		array $overrides = [],
		array $fileScanAreas = [ 'plugins', 'themes', 'wp', 'wpcontent', 'wproot', 'malware_php' ],
		bool $canScanAllFiles = true
	) :void {
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
		$controller->caps = new class( $canScanAllFiles ) {
			private bool $canScanAllFiles;

			public function __construct( bool $canScanAllFiles ) {
				$this->canScanAllFiles = $canScanAllFiles;
			}

			public function canScanAllFiles() :bool {
				return $this->canScanAllFiles;
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
			'scans' => new class( $fileScanAreas ) {
				private array $fileScanAreas;

				public function __construct( array $fileScanAreas ) {
					$this->fileScanAreas = $fileScanAreas;
				}

				public function AFS() :object {
					return new class( $this->fileScanAreas ) {
						private array $fileScanAreas;

						public function __construct( array $fileScanAreas ) {
							$this->fileScanAreas = $fileScanAreas;
						}

						public function getFileScanAreas() :array {
							return $this->fileScanAreas;
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

	private function writeFile( string $path, string $content ) :void {
		$path = $this->normalizePath( $path );
		$dir = \dirname( $path );
		if ( !\is_dir( $dir ) ) {
			@mkdir( $dir, 0777, true );
		}
		\file_put_contents( $path, $content );
		$this->trackWrittenFixtureFile( $path );
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
