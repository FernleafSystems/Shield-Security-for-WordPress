<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route\{
	Option,
	Options
};

class RestHandler extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\RestHandler {

	protected function enumRoutes() :array {
		return [
			'option_get'  => Option\Get::class,
			'option_set'  => Option\Set::class,
			'options_get' => Options\Get::class,
			'options_set' => Options\Set::class,
		];
	}
}