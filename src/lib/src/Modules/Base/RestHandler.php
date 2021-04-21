<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class RestHandler {

	use ModConsumer;

	/**
	 * @var Lib\Rest\Route\RouteBase[]
	 */
	private $routes;

	public function init() {
		if ( $this->getIfPublishRoutes() ) {
			foreach ( $this->getRoutes() as $route ) {
				$route->register_routes();
			}
		}
	}

	/**
	 * @return Lib\Rest\Route\RouteBase[]
	 */
	protected function enumRoutes() :array {
		return [];
	}

	protected function getIfPublishRoutes() :bool {
		return true;
	}

	/**
	 * @return Lib\Rest\Route\RouteBase[]
	 */
	public function getRoutes() :array {
		if ( !isset( $this->routes ) ) {
			$this->routes = array_map(
				function ( $route ) {
					return $route->setMod( $this->getMod() );
				},
				$this->enumRoutes()
			);
		}
		return $this->routes;
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function getWorkingDir() :string {
		$base = $this->getMod()->getWorkingDir();
		if ( !empty( $base ) ) {
			$dir = path_join( $base, 'api' );
			Services::WpFs()->mkdir( $dir );
			if ( !empty( realpath( $dir ) ) ) {
				return $dir;
			}
		}
		throw new \Exception( 'Working directory not available' );
	}
}