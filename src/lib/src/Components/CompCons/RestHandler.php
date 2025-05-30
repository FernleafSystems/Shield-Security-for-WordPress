<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route as WorpdriveRoutes;

class RestHandler extends \FernleafSystems\Wordpress\Plugin\Core\Rest\RestHandler {

	use PluginControllerConsumer;

	/**
	 * @return Route\Base[]
	 */
	public function buildRoutes() :array {
		/** @var Route\Base[] $routes */
		$routes = parent::buildRoutes();
		return \array_filter( $routes, function ( $route ) {
			return $route->isRouteAvailable();
		} );
	}

	protected function enumRoutes() :array {
		return [
			'shield_action' => Route\ShieldPluginAction::class,

			'license_check'  => Route\LicenseCheck::class,
			'license_status' => Route\LicenseStatus::class,

			'option_get'  => Route\OptionsSingleGet::class,
			'option_set'  => Route\OptionsSingleSet::class,
			'options_get' => Route\OptionsBulkGet::class,
			'options_set' => Route\OptionsBulkSet::class,

			'scan_results' => Route\ScanResults::class,
			'scan_start'   => Route\ScansStart::class,
			'scan_status'  => Route\ScansStatus::class,

			'ips_get'     => Route\IpGetIp::class,
			'lists_getip' => Route\IpRulesGetIpOnList::class,
			'lists_addip' => Route\IpRulesAddIpRule::class,

			'debug_get' => Route\Debug::class,

			'worpdrive_signal'   => WorpdriveRoutes\Signal::class,
			'worpdrive_clean'    => WorpdriveRoutes\Clean::class,
			'worpdrive_checks'   => WorpdriveRoutes\Checks::class,
			'worpdrive_download' => WorpdriveRoutes\Download::class,
			'worpdrive_fs_map'   => WorpdriveRoutes\FilesystemMap::class,
			'worpdrive_fs_zip'   => WorpdriveRoutes\FilesystemZip::class,
			'worpdrive_db'       => WorpdriveRoutes\DatabaseSchema::class,
			'worpdrive_data'     => WorpdriveRoutes\DatabaseData::class,
		];
	}
}