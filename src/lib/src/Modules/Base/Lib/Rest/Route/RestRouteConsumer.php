<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route;

trait RestRouteConsumer {

	/**
	 * @var RouteBase|mixed
	 */
	private $restRoute;

	/**
	 * @return RouteBase|mixed
	 */
	public function getRestRoute() {
		return $this->restRoute;
	}

	/**
	 * @param RouteBase|mixed $route
	 * @return $this
	 */
	public function setRestRoute( $route ) {
		$this->restRoute = $route;
		return $this;
	}
}