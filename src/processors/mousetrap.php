<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_MouseTrap extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * Resets the object values to be re-used anew
	 */
	public function init() {
		parent::init();
	}

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Mousetrap $oFO */
		$oFO = $this->getMod();
		echo 'ere';
	}
}