<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf\Utilities
 */
class Repair extends Scans\Base\Utilities\BaseRepair {

	/**
	 * @return bool
	 */
	public function repairItem() {
		/** @var Wcf\ResultItem $oItem */
		$oItem = $this->getScanItem();
		$sPath = trim( wp_normalize_path( $oItem->path_fragment ), '/' );
		return ( new Scans\Helpers\WpCoreFile() )->replace( $sPath );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function canRepair() {
		/** @var Wcf\ResultItem $oItem */
		$oItem = $this->getScanItem();
		return Services::CoreFileHashes()->isCoreFile( $oItem->path_full );
	}
}