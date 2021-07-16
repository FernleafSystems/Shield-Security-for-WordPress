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
	public function repairItem() :bool {
		/** @var Wcf\ResultItem $item */
		$item = $this->getScanItem();
		$path = trim( wp_normalize_path( $item->path_fragment ), '/' );
		return ( new Scans\Helpers\WpCoreFile() )->replace( $path );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function canRepair() :bool {
		/** @var Wcf\ResultItem $oItem */
		$oItem = $this->getScanItem();
		return Services::CoreFileHashes()->isCoreFile( $oItem->path_full );
	}
}