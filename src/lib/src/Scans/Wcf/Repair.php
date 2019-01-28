<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
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
		if ( !empty( $sContent ) ) {
			Services::WpFs()->mkdir( dirname( $sFullPath ) );
			clearstatcache();
			$bSuccess = Services::WpFs()->putFileContent( $sFullPath, $sContent )
						&& ( $oHashes->getFileHash( $sPath ) === md5_file( $sFullPath ) );
		}

		return $bSuccess;
	}
}