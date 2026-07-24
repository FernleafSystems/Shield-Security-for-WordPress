<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Afs;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\{
	BuildScanAction,
	BuildScanItems,
	ScanActionVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	Plugins,
	Themes
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class ScanActionConfigContractTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	public const DEFAULT_EXTENSIONS = [ 'php', 'php5' ];
	private const DEFAULT_MAX_FILE_SIZE = 16777216;

	private static array $filterValues = [];

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		self::$filterValues = [];
		Functions\when( 'apply_filters' )->alias( static function ( string $hook, $value ) {
			return \array_key_exists( $hook, self::$filterValues ) ? self::$filterValues[ $hook ] : $value;
		} );
		Functions\when( 'path_join' )->alias( static fn( string $a, string $b ) :string => \rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' ) );
		Functions\when( 'wp_normalize_path' )->alias( static fn( string $path ) :string => \str_replace( '\\', '/', $path ) );

		ServicesState::installItems( [
			'service_wpplugins' => new class extends Plugins {
				public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
					unset( $file, $reload );
					return null;
				}
			},
			'service_wpthemes' => new class extends Themes {
				public function getCurrent() {
					return new class {
						public function get_stylesheet_directory() :string {
							return WP_CONTENT_DIR.'/themes/current';
						}
					};
				}
			},
		] );
		$this->installController();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	/**
	 * @dataProvider provideInvalidOuterExtensionValues
	 */
	public function test_non_array_extension_filter_values_use_documented_defaults( $filtered ) :void {
		self::$filterValues[ 'shield/scan_ptg_file_exts' ] = $filtered;

		$this->assertSame( self::DEFAULT_EXTENSIONS, ( new ExposedBuildScanAction() )->fileExts() );
	}

	public function provideInvalidOuterExtensionValues() :array {
		return [
			'null'       => [ null ],
			'false'      => [ false ],
			'true'       => [ true ],
			'integer'    => [ 12 ],
			'float'      => [ 1.5 ],
			'string'     => [ 'php' ],
			'object'     => [ new \stdClass() ],
			'stringable' => [ new class {
				public function __toString() :string {
					return 'php';
				}
			} ],
		];
	}

	public function test_mixed_extension_members_are_canonical_without_reinterpreting_dotted_values() :void {
		self::$filterValues[ 'shield/scan_ptg_file_exts' ] = [
			' PHP ',
			'.PHP',
			'',
			'   ',
			12,
			false,
			null,
			[],
			new \stdClass(),
			'php',
			'JS',
		];

		$this->assertSame( [ 'php', '.php', 'js' ], ( new ExposedBuildScanAction() )->fileExts() );
	}

	public function test_explicit_empty_extension_array_is_preserved() :void {
		self::$filterValues[ 'shield/scan_ptg_file_exts' ] = [];

		$this->assertSame( [], ( new ExposedBuildScanAction() )->fileExts() );
	}

	public function test_nonempty_all_invalid_extension_array_uses_defaults() :void {
		self::$filterValues[ 'shield/scan_ptg_file_exts' ] = [ null, false, 12, [], new \stdClass(), '  ' ];

		$this->assertSame( self::DEFAULT_EXTENSIONS, ( new ExposedBuildScanAction() )->fileExts() );
	}

	/**
	 * @dataProvider providePersistedExtensionValues
	 */
	public function test_reconstructed_action_publishes_canonical_extension_list( array $meta, array $expected ) :void {
		$action = ( new ScanActionVO() )->applyFromArray( $meta );

		$this->assertSame( $expected, $action->file_exts );
		$this->assertSame( $expected, $action->getRawData()[ 'file_exts' ] );
	}

	public function providePersistedExtensionValues() :array {
		return [
			'missing'             => [ [], [] ],
			'null'                => [ [ 'file_exts' => null ], [] ],
			'false'               => [ [ 'file_exts' => false ], [] ],
			'integer'             => [ [ 'file_exts' => 12 ], [] ],
			'string'              => [ [ 'file_exts' => 'php' ], [] ],
			'empty array'         => [ [ 'file_exts' => [] ], [] ],
			'canonical list'      => [ [ 'file_exts' => [ 'php', 'js' ] ], [ 'php', 'js' ] ],
			'associative values'  => [ [ 'file_exts' => [ 'primary' => ' PHP ', 'secondary' => 'JS' ] ], [ 'php', 'js' ] ],
			'mixed members'       => [ [ 'file_exts' => [ 12, ' PHP ', false, null, '.PHP', 'php' ] ], [ 'php', '.php' ] ],
			'all invalid members' => [ [ 'file_exts' => [ 12, false, null, [], '  ' ] ], [] ],
		];
	}

	public function test_reconstruction_respects_restricted_property_selection() :void {
		$action = ( new ScanActionVO() )->applyFromArray(
			[ 'scan' => 'afs', 'file_exts' => null ],
			[ 'scan' ]
		);

		$this->assertSame( [ 'scan' => 'afs' ], $action->getRawData() );
	}

	/**
	 * @dataProvider provideInvalidMaxFileSizes
	 */
	public function test_invalid_max_file_size_filter_values_use_default( $filtered ) :void {
		self::$filterValues[ 'shield/file_scan_size_max' ] = $filtered;
		$action = $this->prepareScanItems();

		$this->assertSame( self::DEFAULT_MAX_FILE_SIZE, $action->max_file_size );
	}

	public function provideInvalidMaxFileSizes() :array {
		return [
			'null'            => [ null ],
			'false'           => [ false ],
			'true'            => [ true ],
			'zero'            => [ 0 ],
			'negative'        => [ -1 ],
			'float'           => [ 12.5 ],
			'string'          => [ 'large' ],
			'numeric string'  => [ '1048576' ],
			'overflow string' => [ '999999999999999999999999999999999' ],
			'array'           => [ [] ],
			'object'          => [ new \stdClass() ],
			'stringable'      => [ new class {
				public function __toString() :string {
					return '1048576';
				}
			} ],
		];
	}

	public function test_positive_integer_max_file_size_and_reconstructed_action_reach_consumers() :void {
		self::$filterValues[ 'shield/scan_ptg_file_exts' ] = [ ' PHP ', 'txt' ];
		self::$filterValues[ 'shield/file_scan_size_max' ] = 2048;

		$action = $this->prepareScanItems( ( new ExposedBuildScanAction() )->fileExts() );
		$reconstructed = ( new ScanActionVO() )->applyFromArray( $action->getRawData() );

		$root = $this->createTrackedTempDir( 'shield-afs-config-contract-' );
		\file_put_contents( $root.'/one.php', '<?php' );
		\file_put_contents( $root.'/two.js', 'x' );
		$files = [];
		foreach ( StandardDirectoryIterator::create( $root, 0, $reconstructed->file_exts ) as $file ) {
			$files[] = $file->getFilename();
		}

		$this->assertSame( [ 'php', 'txt' ], $reconstructed->file_exts );
		$this->assertSame( 2048, $reconstructed->max_file_size );
		$this->assertSame( [ 'one.php' ], $files );
	}

	private function prepareScanItems( ?array $extensions = null ) :ScanActionVO {
		$action = new ScanActionVO();
		$action->scan = 'afs';
		$action->scope_type = 'plugin';
		$action->scope_key = 'missing/missing.php';
		$action->file_exts = $extensions ?? self::DEFAULT_EXTENSIONS;

		( new ExposedBuildScanItems() )
			->setScanActionVO( $action )
			->prepare();

		return $action;
	}

	private function installController() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->root_file = WP_PLUGIN_DIR.'/shield/shield.php';
		$controller->cfg = (object)[
			'configuration' => new class {
				public function def( string $key ) :array {
					return $key === 'file_scan_extensions' ? ScanActionConfigContractTest::DEFAULT_EXTENSIONS : [];
				}
			},
		];
		$controller->opts = new class {
			public function optGet( string $key ) :array {
				unset( $key );
				return [];
			}
		};
		$controller->caps = new class {
			public function canScanAllFiles() :bool {
				return true;
			}
		};
		$controller->comps = (object)[
			'license' => new class {
				public function hasValidWorkingLicense() :bool {
					return false;
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
							return [];
						}

						public function isScanEnabledWpCore() :bool {
							return false;
						}

						public function isScanEnabledPlugins() :bool {
							return true;
						}

						public function isScanEnabledThemes() :bool {
							return false;
						}

						public function isScanEnabledWpRoot() :bool {
							return false;
						}

						public function isScanEnabledWpContent() :bool {
							return false;
						}

						public function isEnabledMalwareScanPHP() :bool {
							return false;
						}
					};
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}

class ExposedBuildScanAction extends BuildScanAction {

	public function fileExts() :array {
		return $this->getFileExts();
	}

	protected function buildScanItems() {
	}
}

class ExposedBuildScanItems extends BuildScanItems {

	public function prepare() :void {
		$this->preBuild();
	}
}
