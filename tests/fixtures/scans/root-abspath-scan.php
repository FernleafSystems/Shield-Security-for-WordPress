<?php declare( strict_types=1 );

/**
 * Child process for RootAbsPathScanIntegrationTest.
 *
 * ABSPATH is a constant and vendor services read it directly, so a scan at the filesystem root can only be observed in
 * a process that defines it as WordPress would. The test writes a small site, whose wp-load.php defines ABSPATH from
 * its own directory and then includes this script. Scanning and path handling are the real code. Only the plugin
 * controller and the WordPress.org checksum fetch are replaced. Results are printed as JSON for the test to assert on.
 */

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules {

	function shield_security_get_plugin() {
		return $GLOBALS[ 'shield_root_scan_plugin' ];
	}
}

namespace {

	use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\WildCardOptions;
	use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
	use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\AssetTrustResolver;
	use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\BuildScanItems;
	use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
	use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultsSet;
	use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
	use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanFromFileMap;
	use FernleafSystems\Wordpress\Services\Core\CoreFileHashes;
	use FernleafSystems\Wordpress\Services\Core\Fs;
	use FernleafSystems\Wordpress\Services\Core\General;
	use FernleafSystems\Wordpress\Services\Core\Plugins;
	use FernleafSystems\Wordpress\Services\Core\Themes;
	use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
	use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;
	use FernleafSystems\Wordpress\Services\Services;
	use FernleafSystems\Wordpress\Services\Utilities\Data;
	use FernleafSystems\Wordpress\Services\Utilities\DataManipulation;
	use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Files as PluginFiles;
	use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Theme\Files as ThemeFiles;

	function shield_root_scan_find_config( array $node, string $key ) {
		foreach ( $node as $k => $v ) {
			if ( $k === $key ) {
				return $v;
			}
			if ( \is_array( $v ) ) {
				$found = shield_root_scan_find_config( $v, $key );
				if ( $found !== null ) {
					return $found;
				}
			}
		}
		return null;
	}

	$config = \json_decode( (string)\file_get_contents( (string)\getenv( 'SHIELD_ROOT_SCAN_CONFIG' ) ), true );

	\define( 'WPINC', 'wp-includes' );
	\define( 'WP_CONTENT_DIR', $config[ 'wp_content_dir' ] ?? ABSPATH.'wp-content' );
	\define( 'WP_PLUGIN_DIR', $config[ 'wp_plugin_dir' ] ?? WP_CONTENT_DIR.'/plugins' );

	require $config[ 'shield_root' ].'/vendor/autoload.php';

	// Core's own path functions, loaded from a full WordPress tree rather than from the site being scanned.
	require_once $config[ 'wp_tree' ].'/wp-includes/plugin.php';
	require_once $config[ 'wp_tree' ].'/wp-includes/l10n.php';
	require_once $config[ 'wp_tree' ].'/wp-includes/formatting.php';
	require_once $config[ 'wp_tree' ].'/wp-includes/functions.php';

	function get_theme_root() {
		return WP_CONTENT_DIR.'/themes';
	}

	$spec = \json_decode( (string)\file_get_contents( $config[ 'shield_root' ].'/plugin.json' ), true );

	$fs = new class extends Fs {

		public function fs() {
			return false;
		}

		protected function hasWpfs() :bool {
			return false;
		}
	};

	$general = new class( $config[ 'checksums' ] ) extends General {

		private array $checksums;

		public function __construct( array $checksums ) {
			$this->checksums = $checksums;
		}

		public function getCoreChecksums() :array {
			return $this->checksums;
		}

		public function getVersion( $ignoreClassicpress = false ) :string {
			return '6.0.0';
		}
	};

	$plugins = new class extends Plugins {

		public function getInstalledPluginFiles() :array {
			return [ 'sample/sample.php' ];
		}

		public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
			return $file !== 'sample/sample.php' ? null : new class( $file ) extends WpPluginVo {

				public string $file;

				public string $Version = '1.0.0';

				public function __construct( string $file ) {
					$this->file = $file;
				}

				public function __get( string $key ) {
					switch ( $key ) {
						case 'asset_type':
							return 'plugin';
						case 'unique_id':
							return $this->file;
						case 'version':
							return $this->Version;
						default:
							return null;
					}
				}
			};
		}
	};

	$themes = new class extends Themes {

		public function getCurrent() {
			return new class {

				public function get_stylesheet_directory() :string {
					return WP_CONTENT_DIR.'/themes/sample';
				}
			};
		}

		public function getThemes() :array {
			return [];
		}

		public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
			return null;
		}
	};

	$servicesRef = new \ReflectionClass( Services::class );
	foreach (
		[
			'items'    => [
				'service_corefilehashes'   => new CoreFileHashes(),
				'service_data'             => new Data(),
				'service_datamanipulation' => new DataManipulation(),
				'service_wpfs'             => $fs,
				'service_wpgeneral'        => $general,
				'service_wpplugins'        => $plugins,
				'service_wpthemes'         => $themes,
			],
			'services' => null,
		] as $property => $value
	) {
		$reflected = $servicesRef->getProperty( $property );
		$reflected->setAccessible( true );
		$reflected->setValue( null, $value );
	}

	$afs = new class {

		public function isEnabled() :bool {
			return true;
		}

		public function isEnabledMalwareScanPHP() :bool {
			return true;
		}

		public function isScanEnabledWpRoot() :bool {
			return true;
		}

		public function isScanEnabledPlugins() :bool {
			return true;
		}

		public function isScanEnabledThemes() :bool {
			return true;
		}

		public function isScanEnabledWpContent() :bool {
			return true;
		}

		public function getFileScanAreas() :array {
			return [ 'wp', 'wproot', 'wpcontent', 'plugins', 'themes', 'malware_php' ];
		}

		public function getNewResultsSet() :ResultsSet {
			return new ResultsSet();
		}

		public function getNewResultItem() :ResultItem {
			return new ResultItem();
		}
	};

	/** @var Controller $controller */
	$controller = ( new \ReflectionClass( new class extends Controller {

		public function __construct() {
		}

		public function isPremiumActive() :bool {
			return true;
		}

		public function getRootDir() :string {
			return WP_PLUGIN_DIR.'/wp-simple-firewall/';
		}
	} ) )->newInstanceWithoutConstructor();

	$controller->cfg = new class( $spec ) {

		public array $properties = [ 'slug_parent' => 'icwp', 'slug_plugin' => 'wpsf' ];

		public object $configuration;

		public function __construct( array $spec ) {
			$this->configuration = new class( $spec ) {

				private array $spec;

				public function __construct( array $spec ) {
					$this->spec = $spec;
				}

				public function def( string $key ) {
					return shield_root_scan_find_config( $this->spec, $key ) ?? [];
				}
			};
		}

		public function version() :string {
			return '0.0.0';
		}
	};
	$controller->opts = new class {

		public function optGet( string $key ) {
			return [];
		}
	};
	$controller->cache_dir_handler = new class {

		public function exists() :bool {
			return false;
		}
	};
	$controller->comps = (object)[
		'opts_lookup' => new class {

			public function isScanAutoFilterResults() :bool {
				return true;
			}
		},
		'scans'       => new class( $afs ) {

			private object $afs;

			public function __construct( object $afs ) {
				$this->afs = $afs;
			}

			public function AFS() :object {
				return $this->afs;
			}
		},
	];
	$GLOBALS[ 'shield_root_scan_plugin' ] = new class( $controller ) {

		private Controller $controller;

		public function __construct( Controller $controller ) {
			$this->controller = $controller;
		}

		public function getController() :Controller {
			return $this->controller;
		}
	};

	$action = new ScanActionVO();
	$action->scan = 'afs';
	$action->scope_type = 'full';
	$action->file_exts = shield_root_scan_find_config( $spec, 'file_scan_extensions' );
	$action->max_file_size = ScanActionVO::DEFAULT_MAX_FILE_SIZE;
	$action->patterns_raw = [ 'SHIELD_ROOT_SCAN_MARKER_THAT_NO_FIXTURE_FILE_CONTAINS' ];
	$action->patterns_iraw = [];
	$action->patterns_regex = [];
	$action->patterns_functions = [];
	$action->patterns_keywords = [];

	$output = [
		'abspath'      => ABSPATH,
		'items'        => [],
		'findings'     => [],
		'exception'    => null,
		'path_full'    => [],
		'legacy_path_full' => [],
		'exclusions'   => [],
		'asset_owners' => [],
	];

	if ( isset( $config[ 'queued_paths' ] ) ) {
		$items = \array_map( '\base64_encode', $config[ 'queued_paths' ] );
	}
	else {
		$items = ( new BuildScanItems() )->setScanActionVO( $action )->run();
	}
	$output[ 'items' ] = \array_map( '\base64_decode', $items );

	foreach ( $config[ 'delete_after_build' ] ?? [] as $path ) {
		\unlink( $path );
	}

	$action->items = $items;
	try {
		foreach ( ( new ScanFromFileMap() )->setScanActionVO( $action )->run()->getAllItems() as $item ) {
			$flags = \array_values( \array_filter(
				[ 'is_unrecognised', 'is_missing', 'is_checksumfail', 'is_unidentified', 'is_mal' ],
				static fn( string $flag ) :bool => $item->{$flag}
			) );
			$output[ 'findings' ][ $item->path_fragment ] = $flags;
		}
	}
	catch ( \Throwable $e ) {
		$output[ 'exception' ] = \get_class( $e ).': '.$e->getMessage();
	}

	// A result rebuilt from its stored fragment must still identify the same core file.
	foreach ( [ 'wp-admin/about.php', 'wp-includes/version.php', 'index.php' ] as $fragment ) {
		$result = new ResultItem();
		$result->path_fragment = $fragment;
		$output[ 'path_full' ][ $fragment ] = [
			'path_full' => $result->path_full,
			'is_core'   => Services::CoreFileHashes()->isCoreFile( $result->path_full ),
		];
	}

	// Pre-upgrade findings can hold a one-slash absolute path as their item ID.
	foreach ( [ 'wp-admin/about.php', 'wp-includes/version.php', 'index.php' ] as $fragment ) {
		$result = new ResultItem();
		$result->path_fragment = '/'.\ltrim( ABSPATH.$fragment, '/' );
		$output[ 'legacy_path_full' ][ $fragment ] = [
			'path_full' => $result->path_full,
			'is_core'   => Services::CoreFileHashes()->isCoreFile( $result->path_full ),
		];
	}

	// The same arguments OptionsCorrections uses when an admin saves scan exclusions.
	$output[ 'exclusions' ] = ( new WildCardOptions() )->clean(
		[ '*', 'wp-admin/*', 'wp-includes/*', 'wp-content/*', 'wp-content/custom/*' ],
		\array_map( 'trailingslashit', [
			ABSPATH,
			path_join( ABSPATH, 'wp-admin' ),
			path_join( ABSPATH, 'wp-includes' ),
			untrailingslashit( WP_CONTENT_DIR ),
			path_join( WP_CONTENT_DIR, 'plugins' ),
			path_join( WP_CONTENT_DIR, 'themes' ),
		] ),
		WildCardOptions::FILE_PATH_REL
	);

	// Absolute paths in either spelling and repair's stored fragments must resolve to the same owner.
	$pluginFile = 'wp-content/plugins/sample/sample.php';
	foreach (
		[
			'abspath'     => ABSPATH.$pluginFile,
			'single'      => '/'.\ltrim( ABSPATH.$pluginFile, '/' ),
			'fragment'    => $pluginFile,
			'unowned'     => 'wp-content/plugins-other/sample/sample.php',
		] as $case => $path
	) {
		AssetTrustResolver::resetMemoization();
		try {
			$context = ( new AssetTrustResolver() )->resolveContext( $path );
			$output[ 'asset_owners' ][ $case ] = $context->assetKey.'|'.$context->relativePath;
		}
		catch ( \Exception $e ) {
			$output[ 'asset_owners' ][ $case ] = null;
		}
	}

	// Repair and diffs reach the vendor file utilities with the path a result item gives them.
	$pluginResult = new ResultItem();
	$pluginResult->path_fragment = $pluginFile;
	$pluginResult->is_in_plugin = true;
	$plugin = ( new PluginFiles() )->findPluginFromFile( $pluginResult->path_full );
	$themeResult = new ResultItem();
	$themeResult->path_fragment = 'wp-content/themes/sample/style.css';
	$themeResult->is_in_theme = true;
	$output[ 'vendor_lookups' ] = [
		'plugin'          => $plugin === null ? null : $plugin->file,
		'plugin_relative' => ( new PluginFiles() )->getRelativeFilePathFromItsInstallDir( $pluginResult->path_full ),
		'theme_fragment'  => ( new ThemeFiles() )->getThemePathFragmentFromPath( $themeResult->path_full ),
	];

	echo \json_encode( $output );
}
