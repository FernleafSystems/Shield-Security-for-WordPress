<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DynamicLoad;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageConfigForZoneComponents;

class ConfigForZoneComponents extends Base {

	public const SLUG = 'dynamic_load_config_for_zone_components';

	protected function getPageUrl() :string {
		return self::con()->plugin_urls->cfgForZoneComponent( \current( $this->action_data[ 'zone_component_slugs' ] ) );
	}

	protected function getPageTitle() :string {
		return sprintf( '%s > %s',
			__( 'Edit Settings', 'wp-simple-firewall' ),
			self::con()->comps->zones
				->getZoneComponent( \current( $this->action_data[ 'zone_component_slugs' ] ) )
				->title()
		);
	}

	protected function getContent() :string {
		return self::con()->action_router->render( PageConfigForZoneComponents::class, $this->action_data );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'zone_component_slugs',
		];
	}
}