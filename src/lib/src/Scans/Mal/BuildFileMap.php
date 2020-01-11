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
	public function build() {
		$aFiles = [];
		$this->preBuild();

		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		foreach ( $oAction->scan_root_dirs as $sScanDir => $nDepth ) {
			try {
				$oDirIt = StandardDirectoryIterator::create( $sScanDir, (int)$nDepth, $oAction->file_exts, false );
				foreach ( $oDirIt as $oFsItem ) {
					$sFullPath = wp_normalize_path( $oFsItem->getPathname() );
					/** @var \SplFileInfo $oFsItem */
					if ( $this->isWhitelistedPath( $sFullPath ) || $oFsItem->getSize() == 0 ) {
						continue;
					}
					$aFiles[] = $sFullPath;
				}
			}
			catch ( \Exception $oE ) {
				error_log(
					sprintf( 'Shield file scanner attempted to read directory (%s) but there was error: "%s".', $sScanDir, $oE->getMessage() )
				);
			}
		}
		return $aFiles;
	}

	protected function preBuild() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		if ( empty( $oAction->scan_root_dirs ) || !is_array( $oAction->scan_root_dirs ) ) {
			$oAction->scan_root_dirs = [
				ABSPATH                          => 1,
				path_join( ABSPATH, WPINC )      => 0,
				path_join( ABSPATH, 'wp-admin' ) => 0,
				WP_CONTENT_DIR                   => 0,
			];
		}
		if ( empty( $oAction->file_exts ) ) {
			$oAction->file_exts = [ 'php', 'php5' ];
		}
		if ( !is_array( $oAction->paths_whitelisted ) ) {
			$oAction->paths_whitelisted = [];
		}
	}

	/**
	 * @param string $sThePath
	 * @return bool
	 */
	private function isWhitelistedPath( $sThePath ) {
		$bWhitelisted = false;

		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		foreach ( $oAction->paths_whitelisted as $sWlPath ) {
			if ( stripos( $sThePath, $sWlPath ) === 0 ) {
				$bWhitelisted = true;
				break;
			}
		}
		return $bWhitelisted;
	}
}