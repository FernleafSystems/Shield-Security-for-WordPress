<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\WpCoreFileDownload;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\WpCoreHashes;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class RepairItem
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore#
 */
class RepairItem {

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	public function run( $oItem ) {
		$bSuccess = false;

		$sPath = trim( '/', wp_normalize_path( $oItem->path_fragment ) );
		$oCoreHashes = new WpCoreHashes();
		if ( !$oCoreHashes->isCoreFile( $sPath ) ) {
			throw new \Exception( sprintf( 'Core file "%s" is not an official WordPress core file.', $sPath ) );
		}

		$sFullPath = $oCoreHashes->getAbsolutePathFromFragment( $sPath );
		$sContent = ( new WpCoreFileDownload() )->run( $sPath, true );
		if ( !empty( $sContent ) && Services::WpFs()->putFileContent( $sFullPath, $sContent ) ) {
			clearstatcache();
			$bSuccess = ( $oCoreHashes->getFileHash( $sPath ) === md5_file( $sFullPath ) );
		}

		return $bSuccess;
	}
}