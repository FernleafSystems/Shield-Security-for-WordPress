<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DynamicLoad;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageZone;

class Zone extends Base {

	public const SLUG = 'dynamic_load_zone';

	protected function getPageUrl() :string {
		return self::con()->plugin_urls->zone( $this->action_data[ 'zone_slug' ] );
	}

	protected function getPageTitle() :string {
		return sprintf( '%s > %s',
			__( 'Zone', 'wp-simple-firewall' ),
			self::con()->comps->zones->getZone( $this->action_data[ 'zone_slug' ] )->title()
		);
	}

	protected function getContent() :string {
		return self::con()->action_router->render( PageZone::class, $this->action_data );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'zone_slug',
		];
	}
}