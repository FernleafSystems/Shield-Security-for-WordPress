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

		try {
			$oDirIt = StandardDirectoryIterator::create( $oAction->scan_root_dir, 0, $oAction->file_exts, false );
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
				sprintf( 'Shield file scanner attempted to read directory but there was error: "%s".', $oE->getMessage() )
			);
		}
		return $aFiles;
	}

	protected function preBuild() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		if ( empty( $oAction->scan_root_dir ) ) {
			$oAction->scan_root_dir = ABSPATH;
		}
		if ( empty( $oAction->file_exts ) ) {
			$oAction->file_exts = [ 'php', 'php5' ];
		}
		if ( empty( $oAction->paths_whitelisted ) ) {
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