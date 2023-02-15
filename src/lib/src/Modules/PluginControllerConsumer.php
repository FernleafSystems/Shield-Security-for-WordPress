<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;

trait PluginControllerConsumer {

	/**
	 * @var Controller
	 */
	private $oPlugCon;

	/**
	 * @return Controller
	 */
	public function getCon() {
		return $this->oPlugCon ?? shield_security_get_plugin()->getController();
	}

	/**
	 * @param Controller $con
	 * @return $this
	 */
	public function setCon( $con ) {
		$this->oPlugCon = $con;
		return $this;
	}
}