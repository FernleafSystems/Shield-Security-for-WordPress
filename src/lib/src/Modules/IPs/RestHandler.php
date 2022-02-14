<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Route\IPs\{
	GetIP
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Route\Lists\{
	AddIP,
	GetList,
	GetListIP
};

class RestHandler extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\RestHandler {

	protected function enumRoutes() :array {
		return [
			'ips_get'    => GetIP::class,
			'lists_get'   => GetList::class,
			'lists_getip' => GetListIP::class,
			'lists_addip' => AddIP::class,
		];
	}
}