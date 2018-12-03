<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

/**
 * Class StandardDirectoryIterator
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers
 */
class StandardDirectoryIterator {

	/**
	 * @param string $sDir
	 * @param int    $nMaxDepth
	 * @param array  $aFileExts
	 * @param bool   $bExcludeCoreFiles
	 * @return \RecursiveIteratorIterator
	 * @throws \Exception
	 */
	static public function create( $sDir, $nMaxDepth = 0, $aFileExts = array(), $bExcludeCoreFiles = false ) {

		$oDirIterator = new \RecursiveDirectoryIterator( $sDir );
		if ( method_exists( $oDirIterator, 'setFlags' ) ) {
			$oDirIterator->setFlags( \RecursiveDirectoryIterator::SKIP_DOTS );
		}

		$oFilter = new ScannerRecursiveFilterIterator( new \RecursiveDirectoryIterator( $sDir ) );
		$oFilter->setFileExts( $aFileExts )
				->setIsFilterOutWpCoreFiles( $bExcludeCoreFiles );
		$oRecurIter = new \RecursiveIteratorIterator( $oFilter );
		$oRecurIter->setMaxDepth( $nMaxDepth - 1 ); //since they start at zero.

		return $oRecurIter;
	}
}