<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\DynamicLoad;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Options\OptionsForm;

class Config extends Base {

	public const SLUG = 'dynamic_load_config';

	protected function getPageUrl() :string {
		$con = $this->getCon();
		return $con->plugin_urls->modOptionsCfg( $con->modules[ $this->action_data[ 'primary_mod_slug' ] ] );
	}

	protected function getPageTitle() :string {#
		return sprintf( '%s > %s',
			__( 'Configuration', 'wp-simple-firewall' ),
			$this->getCon()->getModule( $this->action_data[ 'primary_mod_slug' ] )->getMainFeatureName()
		);
	}

	protected function getContent() :string {
		return $this->getCon()
					->getModule_Insights()
					->getActionRouter()
					->render( OptionsForm::SLUG, $this->action_data );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'primary_mod_slug',
		];
	}
}