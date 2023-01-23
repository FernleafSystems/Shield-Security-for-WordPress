<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseHandler;

class LoginFormThirdParties extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'login_forms_third_parties';

	protected function getOptConfigKey() :string {
		return 'user_form_providers';
	}

	/**
	 * @return string[]
	 */
	private function getUnprotectedProvidersByName() :array {
		$modIntegrations = $this->getCon()->getModule_Integrations();
		return array_map(
			function ( $provider ) {
				return $provider->getHandlerName();
			},
			array_filter(
				array_map(
					function ( $providerClass ) use ( $modIntegrations ) {
						return ( new $providerClass() )->setMod( $modIntegrations );
					},
					$modIntegrations->getController_UserForms()->enumProviders()
				),
				function ( $provider ) {
					/** @var BaseHandler $provider */
					return !$provider->isEnabled() && $provider::IsProviderInstalled();
				}
			)
		);
	}

	protected function testIfProtected() :bool {
		return count( $this->getUnprotectedProvidersByName() ) === 0;
	}

	public function title() :string {
		return __( '3rd Party Login Forms', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "It appears that any 3rd party login forms you're using are protected against Bots.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return sprintf( __( "It appears that certain 3rd-party login forms aren't protected against Bots: %s", 'wp-simple-firewall' ),
			implode( ', ', $this->getUnprotectedProvidersByName() )
		);
	}
}