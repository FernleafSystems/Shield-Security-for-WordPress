<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Route\IPs;

abstract class IPsBase extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Route\Base {

	public function getRoutePathPrefix() :string {
		return '/ips';
	}
}