<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Rest\Route;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Route\RouteBase {

	public function getRoutePath() :string {
		return '/license';
	}

	/**
	 * The entire REST API is available to cap:level_2 only, but the licenses endpoints are cap:level_1.
	 */
	public function isRouteAvailable() :bool {
		return self::con()->caps->canRestAPILevel1();
	}
}