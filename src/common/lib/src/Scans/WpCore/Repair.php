<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\WpCoreFileDownload;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class RepairItem
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore#
 */
class Repair {

	/**
	 * @param ResultsSet $oResults
	 */
	public function repairResultsSet( $oResults ) {
		foreach ( $oResults->getItems() as $oItem ) {
			try {
				/** @var ResultItem $oItem */
				$this->repairItem( $oItem );
			}
			catch ( \Exception $oE ) {
			}
		}
	}

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	public function repairItem( $oItem ) {
		$bSuccess = false;

		$sPath = trim( wp_normalize_path( $oItem->path_fragment ), '/' );
		$oHashes = Services::CoreFileHashes();
		if ( !$oHashes->isCoreFile( $sPath ) ) {
			throw new \Exception( sprintf( 'Core file "%s" is not an official WordPress core file.', $sPath ) );
		}

		$sFullPath = $oHashes->getAbsolutePathFromFragment( $sPath );
		$sContent = ( new WpCoreFileDownload() )->run( $sPath, true );
		if ( !empty( $sContent ) && Services::WpFs()->putFileContent( $sFullPath, $sContent ) ) {
			clearstatcache();
			$bSuccess = ( $oHashes->getFileHash( $sPath ) === md5_file( $sFullPath ) );
			$oItem->is_repaired = $bSuccess;
		}

		return $bSuccess;
	}
}