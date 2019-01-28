<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

trait PluginControllerConsumer {

	/**
	 * @var \ICWP_WPSF_Plugin_Controller
	 */
	static private $oPluginController;

	/**
	 * @return \ICWP_WPSF_Plugin_Controller
	 */
	public function getCon() {
		return self::$oPluginController;
	}

	/**
	 * @param \ICWP_WPSF_Plugin_Controller $oMod
	 * @return $this
	 */
	public function setCon( $oMod ) {
		self::$oPluginController = $oMod;
		return $this;
	}
}