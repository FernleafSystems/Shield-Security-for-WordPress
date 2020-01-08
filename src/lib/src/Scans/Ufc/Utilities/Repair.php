<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc
 */
class Repair extends Scans\Base\Utilities\BaseRepair {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function repairItem() {
		/** @var Ufc\ResultItem $oItem */
		$oItem = $this->getScanItem();
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

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function canRepair() {
		/** @var Ufc\ResultItem $oItem */
		$oItem = $this->getScanItem();
		return Services::WpFs()->exists( $oItem->path_full );
	}
}