<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;

trait PluginControllerConsumer {

	/**
	 * @var Controller
	 */
	static private $oPluginController;

	/**
	 * @return Controller
	 */
	public function getCon() {
		return self::$oPluginController;
	}

	/**
	 * @param Controller $oCon
	 * @return $this
	 */
	public function setCon( $oCon ) {
		self::$oPluginController = $oCon;
		return $this;
	}
}