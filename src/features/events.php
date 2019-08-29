<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Events extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return Shield\Databases\Events\Handler
	 */
	protected function loadDbHandler() {
		return new Shield\Databases\Events\Handler();
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'Events';
	}
}