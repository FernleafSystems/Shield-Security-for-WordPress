<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 10.1
 */
abstract class ICWP_WPSF_Processor_ScanBase extends Shield\Modules\BaseShield\ShieldProcessor {

	use Shield\Scans\Common\ScanActionConsumer;

	const SCAN_SLUG = 'base';

	/**
	 * @param int $nDelay
	 * @deprecated 10.1
	 */
	public function scheduleOnDemandScan( $nDelay = 3 ) {
	}

	/**
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 * @deprecated 10.1
	 */
	public function getScanActionVO() {
		return $this->getThisScanCon()->getScanActionVO();
	}

	/**
	 * @return HackGuard\Scan\Controller\Base|mixed
	 * @deprecated 10.1
	 */
	protected function getThisScanCon() {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		return $mod->getScanCon( static::SCAN_SLUG );
	}

	/**
	 * @deprecated 10.1
	 */
	public function hookOnDemandScan() {
	}
}