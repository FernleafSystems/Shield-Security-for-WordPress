<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseHandler;

class ContactFormSpam extends Base {

	public const SLUG = 'contact_form_spam';

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
					$modIntegrations->getController_SpamForms()->enumProviders()
				),
				function ( $provider ) {
					/** @var BaseHandler $provider */
					return !$provider->isEnabled() && $provider::IsProviderInstalled();
				}
			)
		);
	}

	protected function isProtected() :bool {
		return count( $this->getUnprotectedProvidersByName() ) === 0;
	}

	public function href() :string {
		return $this->link( 'form_spam_providers' );
	}

	public function title() :string {
		return __( '3rd Party Contact Form SPAM', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "It appears that any contact forms you're using are protected against Bot SPAM.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return sprintf( __( "It appears that certain contact forms aren't protected against Bot SPAM: %s", 'wp-simple-firewall' ),
			implode( ', ', $this->getUnprotectedProvidersByName() )
		);
	}
}