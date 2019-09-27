<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ICWP_WPSF_FeatureHandler_Statistics
 * @deprecated 8.1.2
 */
class ICWP_WPSF_FeatureHandler_Statistics extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return bool
	 */
	public function isModuleEnabled() {
		return false;
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'Statistics';
	}
}