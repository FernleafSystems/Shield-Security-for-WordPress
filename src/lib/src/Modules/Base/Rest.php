<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route\RouteBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

/**
 * @property bool $enabled
 * @property bool $pro_only
 */
class Rest extends \FernleafSystems\Wordpress\Plugin\Core\Rest\RestHandler {

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

	/**
	 * REST API is a pro-only feature. So, routes are only published if the plugin is pro,
	 * or the particular module is set to be not pro only.
	 */
	protected function isPublishRoutes() :bool {
		return parent::isPublishRoutes()
			   && ( $this->getCon()->isPremiumActive() || !( $this->pro_only ?? true ) );
	}
}