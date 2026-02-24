<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;

class PluginControllerInstaller {

	public static function install( Controller $controller ) :void {
		PluginStore::$plugin = new class( $controller ) {
			private Controller $controller;

			public function __construct( Controller $controller ) {
				$this->controller = $controller;
			}

			public function getController() :Controller {
				return $this->controller;
			}
		};
	}

	public static function reset() :void {
		PluginStore::$plugin = null;
	}
}
