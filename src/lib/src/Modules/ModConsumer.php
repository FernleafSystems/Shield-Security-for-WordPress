<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

trait ModConsumer {

	/**
	 * @var \ICWP_WPSF_FeatureHandler_Base
	 */
	private $oMod;

	/**
	 * @return \ICWP_WPSF_FeatureHandler_Base
	 */
	public function getMod() {
		return $this->oMod;
	}

	/**
	 * @param \ICWP_WPSF_FeatureHandler_Base $oMod
	 * @return $this
	 */
	public function setMod( $oMod ) {
		$this->oMod = $oMod;
		return $this;
	}

}