<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

/**
 * Trait OptsConsumer
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules
 */
trait OptsConsumer {

	/**
	 * @var \ICWP_WPSF_OptionsVO
	 */
	private $oOpts;

	/**
	 * @return \ICWP_WPSF_OptionsVO
	 */
	public function getOpts() {
		return $this->oOpts;
	}

	/**
	 * @param \ICWP_WPSF_OptionsVO $oOpts
	 * @return $this
	 */
	public function setOpts( $oOpts ) {
		$this->oOpts = $oOpts;
		return $this;
	}
}