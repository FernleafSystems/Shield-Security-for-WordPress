<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;

trait PluginControllerConsumer {

	/**
	 * @var Controller
	 */
	private $oPlugCon;

	/**
	 * @var Controller
	 * @deprecated 8.2
	 */
	static private $oPluginController;

	/**
	 * @return Controller
	 */
	public function getCon() {
		return isset( $this->oPlugCon ) ? $this->oPlugCon : self::$oPluginController;
	}

	/**
	 * @param Controller $oCon
	 * @return $this
	 */
	public function setCon( $oCon ) {
		$this->oPlugCon = $oCon;
		return $this;
	}
}