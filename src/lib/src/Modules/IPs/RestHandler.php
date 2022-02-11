<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Route\{
	AddIP,
	GetList,
	GetListIP
};

class RestHandler extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\RestHandler {

	protected function enumRoutes() :array {
		return [
			'lists_get'   => GetList::class,
			'lists_getip' => GetListIP::class,
			'lists_addip' => AddIP::class,
		];
	}
}