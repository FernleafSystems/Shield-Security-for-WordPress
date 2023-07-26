<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

/**
 * @property bool $enabled
 * @property bool $pro_only
 */
class Rest extends \FernleafSystems\Wordpress\Plugin\Core\Rest\RestHandler {

	use ModConsumer;

	/**
	 * @return Rest\Route\RouteBase[]
	 */
	public function buildRoutes() :array {
		/** @var Rest\Route\RouteBase[] $routes */
		$routes = parent::buildRoutes();
		return \array_map(
			function ( $route ) {
				return $route->setMod( $this->mod() );
			},
			$routes
		);
	}

	protected function isPublishRoutes() :bool {
		return parent::isPublishRoutes() && $this->isFeatureAvailable();
	}

	/**
	 * The entire REST API is available to cap:level_2 only, but the licenses endpoints are cap:level_1.
	 */
	protected function isFeatureAvailable() :bool {
		return $this->con()->caps->canRestAPILevel2();
	}
}