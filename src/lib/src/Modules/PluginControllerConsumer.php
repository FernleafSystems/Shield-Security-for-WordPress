<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;

trait PluginControllerConsumer {

	/**
	 * @deprecated 18.2
	 */
	public function getCon() :Controller {
		return shield_security_get_plugin()->getController();
	}

	/**
	 * @since 18.0
	 */
	public static function con() :Controller {
		return shield_security_get_plugin()->getController();
	}
}