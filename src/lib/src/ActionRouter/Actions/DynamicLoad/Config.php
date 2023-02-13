<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DynamicLoad;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageConfig;

class Config extends Base {

	public const SLUG = 'dynamic_load_config';

	protected function getPageUrl() :string {
		$con = $this->getCon();
		return $con->plugin_urls->modCfg( $con->modules[ $this->action_data[ 'mod_slug' ] ] );
	}

	protected function getPageTitle() :string {#
		return sprintf( '%s > %s',
			__( 'Configuration', 'wp-simple-firewall' ),
			$this->getCon()->modules[ $this->action_data[ 'mod_slug' ] ]->getMainFeatureName()
		);
	}

	protected function getContent() :string {
		return $this->getCon()->action_router->render( PageConfig::SLUG, $this->action_data );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'mod_slug',
		];
	}
}