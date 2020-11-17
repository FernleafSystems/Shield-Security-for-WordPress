<?php

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * @deprecated 10.1
 */
class ICWP_WPSF_Processor_Plugin_Tracking extends Shield\Modules\BaseShield\ShieldProcessor {

	public function runDailyCron() {
	}

	private function sendTrackingData() {
	}

	/**
	 * @return array
	 */
	public function collectTrackingData() {
		return [];
	}

	/**
	 * @return array
	 */
	protected function getBaseTrackingData() {
		return [];
	}
}