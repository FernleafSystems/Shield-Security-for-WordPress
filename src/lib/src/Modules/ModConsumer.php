<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;

/**
 * Trait ModConsumer
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules
 */
trait ModConsumer {

	/**
	 * @var \ICWP_WPSF_FeatureHandler_Base
	 */
	private $oMod;

	/**
	 * @return Controller
	 */
	public function getCon() {
		return $this->getMod()->getCon();
	}

	/**
	 * @return \ICWP_WPSF_FeatureHandler_Base
	 */
	public function getMod() {
		return $this->oMod;
	}

	/**
	 * @param Controller $oCon
	 * @return $this
	 */
	public function setCon( $oCon ) {
		$this->getMod()->setCon( $oCon );
		return $this;
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