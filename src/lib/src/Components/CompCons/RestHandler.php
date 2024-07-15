<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Route\RouteBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	PluginControllerConsumer,
	HackGuard\Rest\Route as ScanRoutes,
	IPs\Rest\Route as IpRoutes,
	License\Rest\Route as LicenseRoutes,
	Plugin\Rest\Route as PluginRoutes
};

class RestHandler extends \FernleafSystems\Wordpress\Plugin\Core\Rest\RestHandler {

	use PluginControllerConsumer;

	/**
	 * @return RouteBase[]
	 */
	public function buildRoutes() :array {
		/** @var RouteBase[] $routes */
		$routes = parent::buildRoutes();
		return \array_filter( $routes, function ( $route ) {
			return $route->isRouteAvailable();
		} );
	}

	protected function enumRoutes() :array {
		return [
			'license_check'  => LicenseRoutes\LicenseCheck::class,
			'license_status' => LicenseRoutes\LicenseStatus::class,

			'scan_results' => ScanRoutes\Results\GetAll::class,
			'scan_status'  => ScanRoutes\Scans\Status::class,
			'scan_start'   => ScanRoutes\Scans\Start::class,

			'ips_get'     => IpRoutes\IPs\GetIP::class,
			'lists_get'   => IpRoutes\Lists\GetList::class,
			'lists_getip' => IpRoutes\Lists\GetListIP::class,
			'lists_addip' => IpRoutes\Lists\AddIP::class,

			'debug_get'     => PluginRoutes\Debug\Retrieve::class,
			'option_get'    => PluginRoutes\Options\GetSingle::class,
			'option_set'    => PluginRoutes\Options\SetSingle::class,
			'options_get'   => PluginRoutes\Options\GetAll::class,
			'options_set'   => PluginRoutes\Options\SetBulk::class,
			'shield_action' => PluginRoutes\ShieldPluginAction::class,
		];
	}
}