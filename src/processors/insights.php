<?php

class ICWP_WPSF_Processor_Insights extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * Override to set what this processor does when it's "run"
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Insights $oFO */
		$oFO = $this->getMod();
	}
}