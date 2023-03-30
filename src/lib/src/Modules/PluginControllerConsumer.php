<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;

trait PluginControllerConsumer {

	/**
	 * @var Controller
	 * @deprecated 18.0
	 */
	private $oPlugCon;

	public function getCon() :Controller {
		return shield_security_get_plugin()->getController();
	}

	/**
	 * @since 18.0
	 */
	public function con() :Controller {
		return shield_security_get_plugin()->getController();
	}

	/**
	 * @param Controller $con
	 * @return $this
	 * @deprecated 17.1
	 */
	public function setCon( $con ) {
		return $this;
	}
}