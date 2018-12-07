<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc
 */
class Repair extends Scans\Base\BaseRepair {

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	public function repairItem( $oItem ) {
		$bSuccess = true;

		$oHashes = Services::CoreFileHashes();
		if ( $oHashes->isCoreFile( $oItem->path_fragment ) ) {
			throw new \Exception( sprintf( 'File "%s" is an official WordPress core file.', $oItem->path_fragment ) );
		}

		$oFs = Services::WpFs();
		if ( $oFs->deleteFile( $oItem->path_full ) ) {
			clearstatcache();
			$bSuccess = !$oFs->exists( $oItem->path_full );
		}

		return $bSuccess;
	}
}