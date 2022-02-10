<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Route\{
	GetList
};

class RestHandler extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\RestHandler {

	protected function enumRoutes() :array {
		return [
			'list_get' => GetList::class,
		];
	}
}