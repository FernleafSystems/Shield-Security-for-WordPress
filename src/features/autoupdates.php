<?php

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * @deprecated 10.1
 */
class ICWP_WPSF_FeatureHandler_Autoupdates extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return string
	 */
	protected function getNamespaceBase() :string {
		return 'Autoupdates';
	}
}