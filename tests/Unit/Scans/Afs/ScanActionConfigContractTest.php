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

	/**
	 * @dataProvider providePersistedMaxFileSizes
	 */
	public function test_reconstructed_action_publishes_canonical_max_file_size( array $meta, int $expected ) :void {
		$action = ( new ScanActionVO() )->applyFromArray( $meta );

		$this->assertSame( $expected, $action->max_file_size );
		$this->assertSame( $expected, $action->getRawData()[ 'max_file_size' ] );
	}

	public function providePersistedMaxFileSizes() :array {
		return [
			'missing'        => [ [], ScanActionVO::DEFAULT_MAX_FILE_SIZE ],
			'null'           => [ [ 'max_file_size' => null ], ScanActionVO::DEFAULT_MAX_FILE_SIZE ],
			'false'          => [ [ 'max_file_size' => false ], ScanActionVO::DEFAULT_MAX_FILE_SIZE ],
			'zero'           => [ [ 'max_file_size' => 0 ], ScanActionVO::DEFAULT_MAX_FILE_SIZE ],
			'negative'       => [ [ 'max_file_size' => -1 ], ScanActionVO::DEFAULT_MAX_FILE_SIZE ],
			'float'          => [ [ 'max_file_size' => 2048.0 ], ScanActionVO::DEFAULT_MAX_FILE_SIZE ],
			'numeric string' => [ [ 'max_file_size' => '2048' ], ScanActionVO::DEFAULT_MAX_FILE_SIZE ],
			'array'          => [ [ 'max_file_size' => [ 2048 ] ], ScanActionVO::DEFAULT_MAX_FILE_SIZE ],
			'object'         => [ [ 'max_file_size' => new \stdClass() ], ScanActionVO::DEFAULT_MAX_FILE_SIZE ],
			'positive int'   => [ [ 'max_file_size' => 2048 ], 2048 ],
		];
	}

	public function test_reconstruction_respects_restricted_property_selection() :void {
		$action = ( new ScanActionVO() )->applyFromArray(
			[ 'scan' => 'afs', 'file_exts' => null, 'max_file_size' => null ],
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

		$this->assertSame( ScanActionVO::DEFAULT_MAX_FILE_SIZE, $action->max_file_size );
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

	public function test_snapshot_eligibility_preserves_exact_valid_contract_and_exposes_eligible_tuples() :void {
		$eligibility = [
			'theme'  => [
				'2024' => [
					'comparison_eligible' => true,
					'version'             => '2.0',
				],
			],
			'plugin' => [
				'Vendor/Plugin.php' => [
					'comparison_eligible' => true,
					'version'             => '0',
				],
			],
		];

		$direct = new ScanActionVO();
		$direct->scope_type = 'full';
		$direct->asset_snapshot_eligibility = $eligibility;
		$hydrated = ( new ScanActionVO() )->applyFromArray( [
			'scope_type'                => 'full',
			'asset_snapshot_eligibility' => $eligibility,
		] );

		foreach ( [ $direct, $hydrated ] as $action ) {
			$this->assertTrue( $action->hasValidAssetSnapshotEligibility() );
			$this->assertSame( [
				'plugin' => [
					'Vendor/Plugin.php' => [
						'version'             => '0',
						'comparison_eligible' => true,
					],
				],
				'theme'  => [
					2024 => [
						'version'             => '2.0',
						'comparison_eligible' => true,
					],
				],
			], $action->asset_snapshot_eligibility );
			$this->assertTrue( $action->isAssetSnapshotComparisonEligible( 'plugin', 'Vendor/Plugin.php', '0' ) );
			$this->assertFalse( $action->isAssetSnapshotComparisonEligible( 'plugin', 'vendor/plugin.php', '0' ) );
			$this->assertFalse( $action->isAssetSnapshotComparisonEligible( 'plugin', 'Vendor/Plugin.php', '0.0' ) );
			$this->assertTrue( $action->isAssetSnapshotComparisonEligible( 'theme', '2024', '2.0' ) );
			$this->assertSame( [
				[ 'plugin', 'Vendor/Plugin.php', '0' ],
				[ 'theme', '2024', '2.0' ],
			], $action->getComparisonEligibleAssetTuples() );
		}
	}

	public function test_scoped_scan_comparison_does_not_require_full_scan_eligibility() :void {
		$action = new ScanActionVO();
		$action->scope_type = 'plugin';
		$action->asset_comparison_incomplete = [ 'malformed' ];

		$this->assertFalse( $action->hasValidAssetSnapshotEligibility() );
		$this->assertFalse( $action->hasValidAssetComparisonIncomplete() );
		$this->assertTrue( $action->isAssetSnapshotComparisonEligible( 'plugin', 'target/plugin.php', '1.0.0' ) );
	}

	public function test_asset_comparison_incomplete_is_exact_monotonic_and_rehydrated() :void {
		$eligibility = [
			'plugin' => [
				'Vendor/Plugin.php' => [
					'version'             => '1.0',
					'comparison_eligible' => true,
				],
			],
			'theme'  => [
				'theme' => [
					'version'             => '2.0',
					'comparison_eligible' => true,
				],
			],
		];
		$action = ( new ScanActionVO() )->applyFromArray( [
			'scope_type'                 => 'full',
			'asset_snapshot_eligibility' => $eligibility,
		] );

		$this->assertTrue( $action->hasValidAssetComparisonIncomplete() );
		$this->assertSame( [ 'plugin' => [], 'theme' => [] ], $action->getAssetComparisonIncomplete() );
		$this->assertArrayNotHasKey( 'asset_comparison_incomplete', $action->getRawData() );
		$this->assertTrue( $action->markAssetComparisonIncomplete( 'plugin', 'Vendor/Plugin.php' ) );
		$this->assertFalse( $action->markAssetComparisonIncomplete( 'plugin', 'Vendor/Plugin.php' ) );
		$this->assertTrue( $action->markAssetComparisonIncomplete( 'theme', 'theme' ) );
		$this->assertTrue( $action->isAssetComparisonIncomplete( 'plugin', 'Vendor/Plugin.php' ) );
		$this->assertFalse( $action->isAssetComparisonIncomplete( 'plugin', 'vendor/plugin.php' ) );
		$this->assertSame( [
			'plugin' => [ 'Vendor/Plugin.php' ],
			'theme'  => [ 'theme' ],
		], $action->getAssetComparisonIncomplete() );
		$this->assertSame( $eligibility, $action->asset_snapshot_eligibility );
		$this->assertFalse( $action->isAssetSnapshotComparisonEligible( 'plugin', 'Vendor/Plugin.php', '1.0' ) );
		$this->assertSame( [], $action->getComparisonEligibleAssetTuples() );

		$rehydrated = ( new ScanActionVO() )->applyFromArray( $action->getRawData() );
		$this->assertTrue( $rehydrated->hasValidAssetComparisonIncomplete() );
		$this->assertSame( $action->getAssetComparisonIncomplete(), $rehydrated->getAssetComparisonIncomplete() );
		$this->assertFalse( $rehydrated->markAssetComparisonIncomplete( 'theme', 'theme' ) );
	}

	/**
	 * @dataProvider provideInvalidAssetComparisonIncomplete
	 */
	public function test_malformed_asset_comparison_incomplete_is_preserved_and_fails_full_scan_closed( $invalid ) :void {
		$eligibility = [
			'plugin' => [
				'vendor/plugin.php' => [
					'version'             => '1.0',
					'comparison_eligible' => true,
				],
			],
			'theme'  => [],
		];
		$direct = new ScanActionVO();
		$direct->scope_type = 'full';
		$direct->asset_snapshot_eligibility = $eligibility;
		$direct->asset_comparison_incomplete = $invalid;
		$hydrated = ( new ScanActionVO() )->applyFromArray( [
			'scope_type'                  => 'full',
			'asset_snapshot_eligibility'  => $eligibility,
			'asset_comparison_incomplete' => $invalid,
		] );

		foreach ( [ $direct, $hydrated ] as $action ) {
			$this->assertFalse( $action->hasValidAssetComparisonIncomplete() );
			$this->assertSame( $invalid, $action->getRawData()[ 'asset_comparison_incomplete' ] );
			$this->assertFalse( $action->isAssetSnapshotComparisonEligible( 'plugin', 'vendor/plugin.php', '1.0' ) );
			$this->assertSame( [], $action->getComparisonEligibleAssetTuples() );
		}
	}

	public function provideInvalidAssetComparisonIncomplete() :array {
		return [
			'not array'        => [ 'plugin' ],
			'missing group'    => [ [ 'plugin' => [] ] ],
			'extra group'      => [ [ 'plugin' => [], 'theme' => [], 'core' => [] ] ],
			'associative list' => [ [ 'plugin' => [ 'key' => 'vendor/plugin.php' ], 'theme' => [] ] ],
			'blank key'        => [ [ 'plugin' => [ ' ' ], 'theme' => [] ] ],
			'nul key'          => [ [ 'plugin' => [ "bad\0key" ], 'theme' => [] ] ],
			'non-string key'   => [ [ 'plugin' => [ 1 ], 'theme' => [] ] ],
			'duplicate key'    => [ [ 'plugin' => [ 'vendor/plugin.php', 'vendor/plugin.php' ], 'theme' => [] ] ],
		];
	}

	public function test_asset_comparison_incomplete_respects_restricted_property_selection() :void {
		$marker = [
			'plugin' => [ 'vendor/plugin.php' ],
			'theme'  => [],
		];
		$withoutMarker = ( new ScanActionVO() )->applyFromArray( [
			'scan'                        => 'afs',
			'asset_comparison_incomplete' => $marker,
		], [ 'scan' ] );
		$onlyMarker = ( new ScanActionVO() )->applyFromArray( [
			'scan'                        => 'afs',
			'asset_comparison_incomplete' => $marker,
		], [ 'asset_comparison_incomplete' ] );

		$this->assertSame( [ 'scan' => 'afs' ], $withoutMarker->getRawData() );
		$this->assertTrue( $withoutMarker->hasValidAssetComparisonIncomplete() );
		$this->assertSame( [ 'asset_comparison_incomplete' => $marker ], $onlyMarker->getRawData() );
		$this->assertSame( $marker, $onlyMarker->getAssetComparisonIncomplete() );
	}

	public function test_valid_empty_snapshot_eligibility_is_distinct_from_invalid_or_missing_contract() :void {
		$validEmpty = ( new ScanActionVO() )->applyFromArray( [
			'asset_snapshot_eligibility' => [
				'plugin' => [],
				'theme'  => [],
			],
		] );
		$invalid = ( new ScanActionVO() )->applyFromArray( [
			'asset_snapshot_eligibility' => [
				'plugin' => [],
			],
		] );
		$missing = new ScanActionVO();

		$this->assertTrue( $validEmpty->hasValidAssetSnapshotEligibility() );
		$this->assertArrayHasKey( 'asset_snapshot_eligibility', $validEmpty->getRawData() );
		$this->assertFalse( $invalid->hasValidAssetSnapshotEligibility() );
		$this->assertArrayNotHasKey( 'asset_snapshot_eligibility', $invalid->getRawData() );
		$this->assertFalse( $missing->hasValidAssetSnapshotEligibility() );
		$this->assertArrayNotHasKey( 'asset_snapshot_eligibility', $missing->getRawData() );
	}

	/**
	 * @dataProvider provideInvalidSnapshotEligibility
	 */
	public function test_invalid_snapshot_eligibility_is_rejected_for_direct_and_hydrated_actions( $invalid ) :void {
		$direct = new ScanActionVO();
		$direct->asset_snapshot_eligibility = $invalid;
		$hydrated = ( new ScanActionVO() )->applyFromArray( [
			'asset_snapshot_eligibility' => $invalid,
		] );

		foreach ( [ $direct, $hydrated ] as $action ) {
			$this->assertFalse( $action->hasValidAssetSnapshotEligibility() );
			$this->assertArrayNotHasKey( 'asset_snapshot_eligibility', $action->getRawData() );
			$this->assertSame( [], $action->getComparisonEligibleAssetTuples() );
		}
	}

	public function provideInvalidSnapshotEligibility() :array {
		$entry = [
			'version'             => '1.0',
			'comparison_eligible' => true,
		];
		return [
			'not array'          => [ 'plugin' ],
			'missing group'      => [ [ 'plugin' => [] ] ],
			'extra group'        => [ [ 'plugin' => [], 'theme' => [], 'core' => [] ] ],
			'list group'         => [ [ 'plugin' => [ $entry ], 'theme' => [] ] ],
			'blank asset key'    => [ [ 'plugin' => [ ' ' => $entry ], 'theme' => [] ] ],
			'nul asset key'      => [ [ 'plugin' => [ "bad\0key" => $entry ], 'theme' => [] ] ],
			'missing field'      => [ [ 'plugin' => [ 'a/a.php' => [ 'version' => '1.0' ] ], 'theme' => [] ] ],
			'extra field'        => [ [ 'plugin' => [ 'a/a.php' => $entry + [ 'extra' => true ] ], 'theme' => [] ] ],
			'blank version'      => [ [ 'plugin' => [ 'a/a.php' => \array_merge( $entry, [ 'version' => ' ' ] ) ], 'theme' => [] ] ],
			'nul version'        => [ [ 'plugin' => [ 'a/a.php' => \array_merge( $entry, [ 'version' => "1\0.0" ] ) ], 'theme' => [] ] ],
			'numeric version'    => [ [ 'plugin' => [ 'a/a.php' => \array_merge( $entry, [ 'version' => 1 ] ) ], 'theme' => [] ] ],
			'integer boolean'    => [ [ 'plugin' => [ 'a/a.php' => \array_merge( $entry, [ 'comparison_eligible' => 1 ] ) ], 'theme' => [] ] ],
			'non-array entry'    => [ [ 'plugin' => [ 'a/a.php' => 'invalid' ], 'theme' => [] ] ],
			'integer plugin key' => [ [ 'plugin' => [ 2024 => $entry ], 'theme' => [] ] ],
		];
	}

	public function test_snapshot_eligibility_respects_restricted_property_selection() :void {
		$eligibility = [
			'plugin' => [],
			'theme'  => [],
		];
		$withoutEligibility = ( new ScanActionVO() )->applyFromArray( [
			'scan'                       => 'afs',
			'asset_snapshot_eligibility' => $eligibility,
		], [ 'scan' ] );
		$onlyEligibility = ( new ScanActionVO() )->applyFromArray( [
			'scan'                       => 'afs',
			'asset_snapshot_eligibility' => $eligibility,
		], [ 'asset_snapshot_eligibility' ] );

		$this->assertSame( [ 'scan' => 'afs' ], $withoutEligibility->getRawData() );
		$this->assertSame(
			[ 'asset_snapshot_eligibility' => $eligibility ],
			$onlyEligibility->getRawData()
		);
	}

	public function test_invalid_direct_assignment_and_rehydration_clear_previous_valid_contract() :void {
		$valid = [
			'plugin' => [],
			'theme'  => [],
		];
		$action = new ScanActionVO();
		$action->asset_snapshot_eligibility = $valid;
		$action->asset_snapshot_eligibility = [ 'plugin' => [] ];
		$this->assertFalse( $action->hasValidAssetSnapshotEligibility() );

		$action->asset_snapshot_eligibility = $valid;
		$action->applyFromArray( [
			'asset_snapshot_eligibility' => [ 'plugin' => [] ],
		] );
		$this->assertFalse( $action->hasValidAssetSnapshotEligibility() );
		$this->assertArrayNotHasKey( 'asset_snapshot_eligibility', $action->getRawData() );
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
