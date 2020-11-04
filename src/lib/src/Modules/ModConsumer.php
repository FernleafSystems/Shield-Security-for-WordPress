<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;

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
	 * @return \ICWP_WPSF_FeatureHandler_Base|Modules\Base\ModCon
	 */
	public function getMod() {
		return $this->oMod;
	}

	/**
	 * @return Modules\Base\Options
	 */
	public function getOptions() {
		return $this->getMod()->getOptions();
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
	 * @param \ICWP_WPSF_FeatureHandler_Base|Modules\Base\ModCon $oMod
	 * @return $this
	 */
	public function setMod( $oMod ) {
		$this->oMod = $oMod;
		return $this;
	}
}