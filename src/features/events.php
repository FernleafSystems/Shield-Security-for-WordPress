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
	 * @return Shield\Modules\Events\AjaxHandler
	 */
	protected function loadAjaxHandler() {
		return new Shield\Modules\Events\AjaxHandler;
	}

	/**
	 * @return Shield\Modules\Events\Options
	 */
	protected function loadOptions() {
		return new Shield\Modules\Events\Options();
	}

	/**
	 * @return Shield\Modules\Events\Strings
	 */
	protected function loadStrings() {
		return new Shield\Modules\Events\Strings();
	}
}