<?php

use FernleafSystems\Wordpress\Plugin\Shield;

class ICWP_WPSF_FeatureHandler_Events extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return false|Shield\Databases\Events\Handler
	 */
	public function getDbHandler_Events() {
		return $this->getDbH( 'events' );
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'Events';
	}

	/**
	 * @return Shield\Databases\Events\Handler
	 * @deprecated 8.1.2
	 */
	protected function loadDbHandler() {
		return new Shield\Databases\Events\Handler();
	}
}