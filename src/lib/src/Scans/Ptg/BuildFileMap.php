<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class BuildFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class BuildFileMap {

	use ScanActionConsumer;

	/**
	 * @return string[]
	 */
	public function build() {
		$aFiles = [];

		/** @var ScanActionVO $oScanAction */
		$oScanAction = $this->getScanActionVO();

		$sAbsPath = wp_normalize_path( ABSPATH );
		foreach ( $this->getScanRoots() as $sRootDir ) {
			try {
				$oDirIt = StandardDirectoryIterator::create( $sRootDir, 0, $oScanAction->file_exts );
				foreach ( $oDirIt as $oFsItem ) {
					/** @var \SplFileInfo $oFsItem */
					if ( $oFsItem->getSize() != 0 ) {
						$aFiles[] = str_replace( $sAbsPath, '', wp_normalize_path( $oFsItem->getPathname() ) );
					}
				}
			}
			catch ( \Exception $oE ) {
				error_log(
					sprintf( 'Shield file scanner attempted to read directory (%s) but there was error: "%s".', $sRootDir, $oE->getMessage() )
				);
			}
		}

		return $aFiles;
	}

	/**
	 * @return string[]
	 */
	private function getScanRoots() {
		$aRoots = [];

		$oWpP = Services::WpPlugins();
		foreach ( $oWpP->getPluginsAsVo() as $oPlugin ) {
			if ( $oPlugin->active ) {
				$aRoots[] = $oPlugin->getInstallDir();
			}
		}

		$oWpT = Services::WpThemes();
		$oCurrent = $oWpT->getCurrent();
		$aRoots[] = $oCurrent->get_stylesheet_directory();
		if ( $oWpT->isActiveThemeAChild() ) {
			$aRoots[] = $oCurrent->get_template_directory();
		}

		return $aRoots;
	}
}