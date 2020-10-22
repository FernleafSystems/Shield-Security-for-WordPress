<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

abstract class ICWP_WPSF_Processor_ScanBase extends Shield\Modules\BaseShield\ShieldProcessor {

	use Shield\Scans\Common\ScanActionConsumer;
	const SCAN_SLUG = 'base';

	public function run() {
		add_action(
			$this->getCon()->prefix( 'ondemand_scan_'.$this->getThisScanCon()->getSlug() ),
			function () {
				/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
				$oMod = $this->getMod();
				$oMod->getScanQueueController()
					 ->startScans( [ $this->getThisScanCon()->getSlug() ] );
			}
		);
	}

	public function hookOnDemandScan() {
		$this->scheduleOnDemandScan();
	}

	/**
	 * @param int $nDelay
	 */
	public function scheduleOnDemandScan( $nDelay = 3 ) {
		$sHook = $this->getCon()->prefix( 'ondemand_scan_'.$this->getThisScanCon()->getSlug() );
		if ( !wp_next_scheduled( $sHook ) ) {
			wp_schedule_single_event( Services::Request()->ts() + $nDelay, $sHook );
		}
	}

	/**
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 */
	public function getScanActionVO() {
		return $this->getThisScanCon()->getScanActionVO();
	}

	/**
	 * @return HackGuard\Scan\Controller\Base|mixed
	 */
	protected function getThisScanCon() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		return $oMod->getScanCon( static::SCAN_SLUG );
	}
}