<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class CacheStoreTestController {

	public static function install(
		CacheStoreTestOptions $options,
		?object $cfg = null,
		?object $labels = null,
		?ModCon $plugin = null
	) :Controller {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = $cfg ?? (object)[
			'paths'      => [
				'cache' => 'shield',
			],
			'properties' => [
				'slug_parent' => 'icwp',
				'slug_plugin' => 'wpsf',
			],
		];
		$controller->labels = $labels ?? (object)[
			'Name' => 'Shield',
		];
		$controller->opts = $options;
		if ( $plugin !== null ) {
			$controller->plugin = $plugin;
		}

		PluginControllerInstaller::install( $controller );
		return $controller;
	}
}
