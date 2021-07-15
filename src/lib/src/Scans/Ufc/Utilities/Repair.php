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
	public function repairItem() :bool {
		/** @var Ufc\ResultItem $item */
		$item = $this->getScanItem();
		$success = true;

		$oHashes = Services::CoreFileHashes();
		if ( $oHashes->isCoreFile( $item->path_fragment ) ) {
			throw new \Exception( sprintf( 'File "%s" is an official WordPress core file.', $item->path_fragment ) );
		}

		$FS = Services::WpFs();
		if ( $FS->deleteFile( $item->path_full ) ) {
			clearstatcache();
			$success = !$FS->exists( $item->path_full );
		}

		return $success;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function canRepair() :bool {
		/** @var Ufc\ResultItem $item */
		$item = $this->getScanItem();
		return (bool)Services::WpFs()->exists( $item->path_full );
	}
}