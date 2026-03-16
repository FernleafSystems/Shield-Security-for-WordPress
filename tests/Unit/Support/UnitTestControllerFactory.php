<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;

class UnitTestControllerFactory {

	public static function install(
		?UnitTestPluginUrls $pluginUrls = null,
		?UnitTestActionRouter $actionRouter = null,
		object $extras = null
	) :Controller {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = $pluginUrls ?? new UnitTestPluginUrls();
		$controller->svgs = new UnitTestSvgs();

		if ( $actionRouter !== null ) {
			$controller->action_router = $actionRouter;
		}

		if ( $extras !== null ) {
			foreach ( \get_object_vars( $extras ) as $property => $value ) {
				$controller->{$property} = $value;
			}
		}

		PluginControllerInstaller::install( $controller );
		return $controller;
	}
}
