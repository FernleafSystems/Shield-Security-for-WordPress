<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route\RouteBase;

abstract class Base extends RouteBase {

	public function getRoutePathPrefix() :string {
		return '/ip_list';
	}
}