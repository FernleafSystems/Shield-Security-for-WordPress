<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

class Rest extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest {

	protected function enumRoutes() :array {
		return [
			'debug_get'   => Rest\Route\Debug\Retrieve::class,
			'option_get'  => Rest\Route\Options\GetSingle::class,
			'option_set'  => Rest\Route\Options\SetSingle::class,
			'options_get' => Rest\Route\Options\GetAll::class,
			'options_set' => Rest\Route\Options\SetBulk::class,
		];
	}
}