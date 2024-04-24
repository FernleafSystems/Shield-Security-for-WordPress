<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DynamicLoad;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsModules;

class Config extends Base {

	public const SLUG = 'dynamic_load_config';

	protected function getPageUrl() :string {
		return self::con()->plugin_urls->modCfg( $this->action_data[ 'mod_slug' ] );
	}

	protected function getPageTitle() :string {
		return sprintf( '%s > %s',
			__( 'Configuration', 'wp-simple-firewall' ),
			( new StringsModules() )->getFor( $this->action_data[ 'mod_slug' ] )[ 'name' ]
		);
	}

	protected function getContent() :string {
		return self::con()->action_router->render( PageConfig::class, $this->action_data );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'mod_slug',
		];
	}
}