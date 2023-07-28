<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class Rest extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest {

	protected function enumRoutes() :array {
		return [
			'ips_get'     => Rest\Route\IPs\GetIP::class,
			'lists_get'   => Rest\Route\Lists\GetList::class,
			'lists_getip' => Rest\Route\Lists\GetListIP::class,
			'lists_addip' => Rest\Route\Lists\AddIP::class,
		];
	}
}