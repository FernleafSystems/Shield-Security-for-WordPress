<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;

trait PluginControllerConsumer {

	/**
	 * @since 18.0
	 */
	public static function con() :Controller {
		return shield_security_get_plugin()->getController();
	}
}