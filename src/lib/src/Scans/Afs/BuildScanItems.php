<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseBuildFileMap;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Services;

class BuildScanItems extends BaseBuildFileMap {

	/**
	 * @return string[]
	 */
	public function build() :array {
		$this->preBuild();

		$files = array_unique( array_merge(
			$this->buildFilesFromDisk(),
			$this->buildFilesFromWpHashes()
		) );
		natsort( $files );

		return array_map(
			function ( $path ) {
				return base64_encode( $path );
			},
			$files
		);
	}

	private function buildFilesFromWpHashes() :array {
		$files = [];

		$coreHashes = Services::CoreFileHashes();
		if ( $coreHashes->isReady() ) {
			foreach ( array_keys( $coreHashes->getHashes() ) as $fragment ) {
				// To reduce noise, we exclude plugins and themes (by default)
				if ( strpos( $fragment, 'wp-content/' ) === false ) {
					$fullPath = wp_normalize_path( path_join( ABSPATH, $fragment ) );
					if ( !$this->isWhitelistedPath( $fullPath ) ) {
						$files[] = $fullPath;
					}
				}
			}
		}

		return $files;
	}

	private function buildFilesFromDisk() :array {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$files = [];
		foreach ( $action->scan_root_dirs as $scanDir => $depth ) {
			try {
				foreach ( StandardDirectoryIterator::create( $scanDir, (int)$depth, $action->file_exts, false ) as $item ) {
					/** @var \SplFileInfo $item */
					$path = wp_normalize_path( $item->getPathname() );
					try {
						if ( !$this->isWhitelistedPath( $path ) && !$this->isAutoFilterFile( $item ) ) {
							$files[] = $path;
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

	protected function preBuild() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		if ( empty( $action->scan_root_dirs ) || !is_array( $action->scan_root_dirs ) ) {
			$rootDirs = [
				ABSPATH                          => 1,
				path_join( ABSPATH, WPINC )      => 0,
				path_join( ABSPATH, 'wp-admin' ) => 0,
			];
			if ( $this->getCon()->isPremiumActive() ) {
				$rootDirs[ WP_CONTENT_DIR ] = 0;
			}
			$action->scan_root_dirs = $rootDirs;
		}
		if ( !is_array( $action->paths_whitelisted ) ) {
			$action->paths_whitelisted = [];
		}
	}
}