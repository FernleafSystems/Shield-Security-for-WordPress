<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

class StandardDirectoryIterator {

	/**
	 * @param string $sDir
	 * @param int    $nMaxDepth
	 * @param array  $aFileExts
	 * @param bool   $bExcludeCoreFiles
	 * @return \RecursiveIteratorIterator
	 * @throws \Exception
	 */
	public static function create( $sDir, $nMaxDepth = 0, $aFileExts = [], $bExcludeCoreFiles = false ) {

		$oDirIterator = new \RecursiveDirectoryIterator( $sDir );
		$oDirIterator->setFlags( \RecursiveDirectoryIterator::SKIP_DOTS );

		$oFilter = new ScannerRecursiveFilterIterator( $oDirIterator );
		$oFilter->setFileExts( $aFileExts )
				->setIsFilterOutWpCoreFiles( $bExcludeCoreFiles );
		$oRecurIter = new \RecursiveIteratorIterator( $oFilter );
		$oRecurIter->setMaxDepth( $nMaxDepth - 1 ); //since they start at zero.

		return $oRecurIter;
	}
}