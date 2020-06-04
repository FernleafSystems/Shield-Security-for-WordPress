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

		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		$sAbsPath = wp_normalize_path( ABSPATH );
		foreach ( $this->getScanRoots() as $sScanDir ) {
			try {
				foreach ( StandardDirectoryIterator::create( $sScanDir, 0, $oAction->file_exts ) as $oFsItem ) {
					/** @var \SplFileInfo $oFsItem */
					try {
						if ( $oFsItem->getSize() > 0 ) {
							$aFiles[] = str_replace( $sAbsPath, '', wp_normalize_path( $oFsItem->getPathname() ) );
						}
					}
					catch ( \Exception $oE ) {
					}
				}
			}
			catch ( \Exception $oE ) {
				error_log(
					sprintf( 'Shield file scanner (%s) attempted to read directory (%s) but there was error: "%s".',
						$oAction->scan, $sScanDir, $oE->getMessage() )
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