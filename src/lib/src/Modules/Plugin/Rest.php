<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Route\{
	Debug,
	Options,
	ShieldPluginAction
};

class Rest extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest {

	protected function enumRoutes() :array {
		return [
			'debug_get'     => Debug\Retrieve::class,
			'option_get'    => Options\GetSingle::class,
			'option_set'    => Options\SetSingle::class,
			'options_get'   => Options\GetAll::class,
			'options_set'   => Options\SetBulk::class,
			'shield_action' => ShieldPluginAction::class,
		];
	}
}