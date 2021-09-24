<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseBuildFileMap;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;

class BuildScanItems extends BaseBuildFileMap {

	/**
	 * @return string[]
	 */
	public function build() :array {
		$files = [];
		$this->preBuild();

		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

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
			$action->scan_root_dirs = [
				ABSPATH                          => 1,
				path_join( ABSPATH, WPINC )      => 0,
				path_join( ABSPATH, 'wp-admin' ) => 0,
				WP_CONTENT_DIR                   => 0,
			];
		}
		if ( empty( $action->file_exts ) ) {
			$action->file_exts = [ 'php', 'php5' ];
		}
		if ( !is_array( $action->paths_whitelisted ) ) {
			$action->paths_whitelisted = [];
		}
	}
}