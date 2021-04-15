<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\UserFormsController;

class Upgrade extends Base\Upgrade {

	protected function upgrade_1120() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$providers = ( new UserFormsController() )
			->setMod( $this->getMod() )
			->enumProviders();

		$enabledProviders = $opts->getUserFormProviders();
		foreach ( $providers as $provider ) {
			if ( $provider::IsProviderInstalled() ) {
				$enabledProviders[] = $provider->getHandlerSlug();
			}
		}
		$opts->setOpt( 'user_form_providers', array_unique( $enabledProviders ) );
	}
}