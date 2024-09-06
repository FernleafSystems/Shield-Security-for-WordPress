<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\WildCardOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Services;

class BuildScanItems {

	use PluginControllerConsumer;

	protected function preBuild() {
		$con = self::con();
		/** @var ScanActionVO $action */
		$action = $con->comps->scans->AFS()->getScanActionVO();

		$pluginsDir = \dirname( $con->getRootDir() );
		$themesDir = \dirname( Services::WpThemes()->getCurrent()->get_stylesheet_directory() );

		$rootDirs = [];
		foreach (
			[
				ABSPATH                          => [
					'depth' => 1,
					'areas' => [
						'wproot',
						'malware_php',
					],
				],
				path_join( ABSPATH, WPINC )      => [
					'depth' => 0,
					'areas' => [
						'wp',
						'malware_php',
					],
				],
				path_join( ABSPATH, 'wp-admin' ) => [
					'depth' => 0,
					'areas' => [
						'wp',
						'malware_php',
					],
				],
				WP_CONTENT_DIR                   => [
					'depth' => 0,
					'areas' => [
						'wpcontent',
						'malware_php',
					],
				],
				$pluginsDir                      => [
					'depth' => 0,
					'areas' => [
						'plugins',
						'malware_php',
					],
				],
				$themesDir                       => [
					'depth' => 0,
					'areas' => [
						'themes',
						'malware_php',
					],
				],
			] as $dir => $dirAttr
		) {
			if ( \count( \array_intersect( $dirAttr[ 'areas' ], $con->comps->scans->AFS()
																				  ->getFileScanAreas() ) ) > 0 ) {
				// we don't include the plugins and themes if WP Content Dir is already included.
				if ( !\in_array( $dir, [ $pluginsDir, $themesDir ] ) || !isset( $rootDirs[ WP_CONTENT_DIR ] ) ) {
					$rootDirs[ $dir ] = $dirAttr[ 'depth' ];
				}
			}
		}

		$action->scan_root_dirs = $rootDirs;

		$paths = $con->cfg->configuration->def( 'default_whitelist_paths' );
		if ( $con->isPremiumActive() ) {
			$paths = \array_merge( $con->opts->optGet( 'scan_path_exclusions' ), $paths );
		}
		$action->paths_whitelisted = \array_map(
			function ( $value ) {
				return ( new WildCardOptions() )->buildFullRegexValue( $value, WildCardOptions::FILE_PATH_REL );
			},
			$paths
		);
		$action->max_file_size = apply_filters( 'shield/file_scan_size_max', 16*1024*1024 );
	}

	public function run() :array {
		$this->preBuild();

		$files = \array_filter(
			\array_unique( \array_merge(
				$this->buildFilesFromDisk(),
				$this->buildFilesFromWpHashes()
			) ),
			function ( $path ) {
				return !$this->isWhitelistedPath( $path );
			}
		);

		\natsort( $files );

		return \array_map(
			function ( $path ) {
				return \base64_encode( $path );
			},
			\array_values( $files )
		);
	}

	private function buildFilesFromWpHashes() :array {
		$files = [];

		$coreHashes = Services::CoreFileHashes();
		if ( $coreHashes->isReady() ) {
			foreach ( \array_keys( $coreHashes->getHashes() ) as $fragment ) {
				// To reduce noise, we exclude plugins and themes (by default)
				if ( \strpos( $fragment, 'wp-content/' ) === false ) {
					$files[] = wp_normalize_path( path_join( ABSPATH, $fragment ) );
				}
			}
		}

		return $files;
	}

	private function buildFilesFromDisk() :array {
		/** @var ScanActionVO $action */
		$action = self::con()->comps->scans->AFS()->getScanActionVO();

		$files = [];
		foreach ( $action->scan_root_dirs as $scanDir => $depth ) {
			try {
				foreach ( StandardDirectoryIterator::create( $scanDir, (int)$depth, \is_array( $action->file_exts ) ? $action->file_exts : [] ) as $item ) {
					/** @var \SplFileInfo $item */
					try {
						if ( !$this->isAutoFilterFile( $item ) ) {
							$files[] = wp_normalize_path( $item->getPathname() );
						}
					}
					catch ( \Exception $e ) {
					}
				}
			}
			catch ( \Exception $e ) {
				error_log(
					sprintf( 'Shield file scanner (%s) attempted to read directory (%s) but there was error: "%s".',
						$action->scan, $scanDir, $e->getMessage() )
				);
			}
		}
		return $files;
	}

	private function isAutoFilterFile( \SplFileInfo $file ) :bool {
		/**
		 * Remove anything in wp-content as this is only relevant for Plugins/Themes/Malware
		 * and this is PRO-only anyway.
		 */
		return (
				   !self::con()->isPremiumActive()
				   && \strpos( wp_normalize_path( $file->getPathname() ), '/wp-content/' ) !== false
			   )
			   ||
			   ( self::con()->comps->opts_lookup->isScanAutoFilterResults() && $file->getSize() === 0 );
	}

	private function isWhitelistedPath( string $path ) :bool {
		$whitelisted = false;

		/** @var ScanActionVO $action */
		$action = self::con()->comps->scans->AFS()->getScanActionVO();
		foreach ( $action->paths_whitelisted as $wlPathRegEx ) {
			if ( \preg_match( $wlPathRegEx, $path ) ) {
				$whitelisted = true;
				break;
			}
		}
		return $whitelisted;
	}
}