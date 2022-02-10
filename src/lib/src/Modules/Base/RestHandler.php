<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route\RouteBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class RestHandler extends \FernleafSystems\Wordpress\Plugin\Core\Rest\RestHandler {

	use ModConsumer;

	/**
	 * @return Lib\Rest\Route\RouteBase[]
	 */
	public function buildRoutes() :array {
		/** @var RouteBase[] $routes */
		$routes = parent::buildRoutes();
		return array_map(
			function ( $route ) {
				return $route->setMod( $this->getMod() );
			},
			$routes
		);
	}
}