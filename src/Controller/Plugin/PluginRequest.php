<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PluginRequest {

	use PluginControllerConsumer;

	public static function IsPluginAdminPage() :bool {
		return Services::Request()->query( 'page' ) === static::con()->plugin_urls->rootAdminPageSlug();
	}

	public static function IsNav( string $nav ) :bool {
		return Services::Request()->query( PluginNavs::FIELD_NAV ) === $nav;
	}
}