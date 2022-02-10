<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route\OptionsGet;

class RestHandler extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\RestHandler {

	/**
	 * @return string[]
	 */
	protected function enumRoutes() :array {
		return [
			'options_get' => OptionsGet::class
		];
	}
}