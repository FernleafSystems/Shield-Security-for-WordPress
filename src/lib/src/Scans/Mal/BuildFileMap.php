<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;

/**
 * Class BuildFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class BuildFileMap {

	use ScanActionConsumer;

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
					$fullPath = wp_normalize_path( $item->getPathname() );
					try {
						if ( !$this->isWhitelistedPath( $fullPath ) && $item->getSize() > 0 ) {
							$files[] = $fullPath;
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

	private function isWhitelistedPath( string $path ) :bool {
		$whitelisted = false;

		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		foreach ( $oAction->paths_whitelisted as $sWlPath ) {
			if ( stripos( $path, $sWlPath ) === 0 ) {
				$whitelisted = true;
				break;
			}
		}
		return $whitelisted;
	}
}