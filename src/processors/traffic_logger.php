<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ShieldProcessor;

/**
 * Class ICWP_WPSF_Processor_TrafficLogger
 * @deprecated 8.7.0
 */
class ICWP_WPSF_Processor_TrafficLogger extends ShieldProcessor {

	public function onModuleShutdown() {
		parent::onModuleShutdown();
	}

	/**
	 * @return bool
	 */
	protected function getIfLogRequest() {
		return false;
	}

	/**
	 * @return bool
	 */
	protected function isCustomExcluded() {
		return true;
	}

	/**
	 * @return bool
	 */
	protected function isServiceIp_Search() {
		return false;
	}

	/**
	 * @return bool
	 */
	protected function isServiceIp_Uptime() {
		return false;
	}

	protected function logTraffic() {
	}
}