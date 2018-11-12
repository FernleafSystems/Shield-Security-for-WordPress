<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class RepairItem
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore#
 */
class Repair extends Scans\Base\BaseRepair {

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
		$sContent = ( new Scans\Helpers\WpCoreFileDownload() )->run( $sPath, true );
		if ( !empty( $sContent ) && Services::WpFs()->putFileContent( $sFullPath, $sContent ) ) {
			clearstatcache();
			$bSuccess = ( $oHashes->getFileHash( $sPath ) === md5_file( $sFullPath ) );
			$oItem->repaired_at = $bSuccess ? Services::Request()->ts() : 0;
		}

		return $bSuccess;
	}
}