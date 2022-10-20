<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\DynamicLoad;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Options\OptionsForm;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ModCon;

class Config extends Base {

	const SLUG = 'dynamic_load_config';

	protected function getPageUrl() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getUrl_SubInsightsPage( Constants::ADMIN_PAGE_CONFIG, $this->action_data[ 'primary_mod_slug' ] );
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
					->render( OptionsForm::SLUG, [
						'primary_mod_slug' => $this->action_data[ 'primary_mod_slug' ]
					] );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'primary_mod_slug',
		];
	}
}