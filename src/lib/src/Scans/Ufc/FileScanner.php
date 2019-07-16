<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class FileScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc
 */
class FileScanner extends Shield\Scans\Base\Files\BaseFileScanner {

	/**
	 * @param string $sFullPath
	 * @return ResultItem|null
	 */
	public function scan( $sFullPath ) {
		$oResult = null;

		$sFullPath = wp_normalize_path( $sFullPath );
		if ( !$this->isExcluded( $sFullPath ) ) {
			/** @var ResultItem $oResult */
			$oResult = $this->getScanActionVO()->getNewResultItem();
			$oResult->path_full = $sFullPath;
			$oResult->path_fragment = Services::CoreFileHashes()->getFileFragment( $sFullPath );
		};

		return $oResult;
	}

	/**
	 * @param string $sFullPath
	 * @return bool
	 */
	private function isExcluded( $sFullPath ) {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		$sFilePath = wp_normalize_path( $sFullPath );
		$sFileName = basename( $sFilePath );

		$bExcluded = false;

		foreach ( $oAction->exclusions as $sExclusion ) {

			if ( preg_match( '/^#(.+)#[a-z]*$/i', $sExclusion, $aMatches ) ) { // it's regex
				$bExcluded = @preg_match( stripslashes( $sExclusion ), $sFilePath );
			}
			else {
				$sExclusion = wp_normalize_path( $sExclusion );
				if ( strpos( $sExclusion, '/' ) === false ) { // filename only
					$bExcluded = ( $sFileName == $sExclusion );
				}
				else {
					$bExcluded = strpos( $sFilePath, $sExclusion );
				}
			}

			if ( $bExcluded ) {
				break;
			}
		}
		return (bool)$bExcluded;
	}
}