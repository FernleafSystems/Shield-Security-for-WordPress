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
		return $this->oPlugCon;
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