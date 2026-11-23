<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;

/**
 * Installs a bare controller whose only part is the test plugin URLs, for code that only builds links.
 */
class PluginUrlsControllerInstaller {

	public static function install() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new UnitTestPluginUrls();
		PluginControllerInstaller::install( $controller );
	}
}
