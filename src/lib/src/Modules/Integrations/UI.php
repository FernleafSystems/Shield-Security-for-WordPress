<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseHandler;

class UI extends Modules\BaseShield\UI {

	public function getSectionNotices( string $section ) :array {
		$notices = [];

		/** @var Modules\LoginGuard\Options $loginGuardOpts */
		$loginGuardOpts = $this->getCon()->getModule_LoginGuard()->getOptions();
		$locations = $loginGuardOpts->getBotProtectionLocations();
		$locations = array_intersect_key(
			array_merge(
				array_flip( $locations ),
				[
					'login'        => __( 'Login', 'wp-simple-firewall' ),
					'register'     => __( 'Registration', 'wp-simple-firewall' ),
					'password'     => __( 'Lost Password', 'wp-simple-firewall' ),
					'checkout_woo' => __( 'Checkout', 'wp-simple-firewall' ),
				]
			),
			array_flip( $locations )
		);
		$locations = empty( $locations ) ? __( 'None', 'wp-simple-firewall' ) : implode( ', ', $locations );

		switch ( $section ) {

			case 'section_user_forms':
				if ( $loginGuardOpts->isEnabledAntiBot() ) {
					$notices[] = sprintf( '%s: %s %s', __( 'Note', 'wp-simple-firewall' ),
						sprintf(
							__( "The following types of user forms are protected by AntiBot Detection: %s.", 'wp-simple-firewall' ),
							$locations
						),
						sprintf( '<a href="%s" target="_blank">%s</a>',
							$this->getCon()->getModule_LoginGuard()->getUrl_AdminPage(),
							__( 'Click here to review those settings.', 'wp-simple-firewall' ) )
					);
				}
				break;
		}

		return $notices;
	}

	public function getSectionWarnings( string $section ) :array {
		$warnings = [];
		$con = $this->getCon();

		/** @var Modules\LoginGuard\Options $loginGuardOpts */
		$loginGuardOpts = $con->getModule_LoginGuard()->getOptions();

		switch ( $section ) {

			case 'section_user_forms':
				if ( !$loginGuardOpts->isEnabledAntiBot() ) {
					$warnings[] = sprintf( '%s: %s %s', __( 'Important', 'wp-simple-firewall' ),
						__( "Use of the AntiBot Detection Engine for user forms isn't turned on in the Login Guard module.", 'wp-simple-firewall' ),
						sprintf( '<a href="%s" target="_blank">%s</a>',
							$con->getModule_LoginGuard()->getUrl_AdminPage(),
							__( 'Click here to review those settings.', 'wp-simple-firewall' ) )
					);
				}
				break;

			case 'section_spam':
				/** @var ModCon $mod */
				$mod = $this->getMod();
				/** @var BaseHandler[] $installedButNotEnabledProviders */
				$installedButNotEnabledProviders = array_filter(
					array_map(
						function ( $providerClass ) {
							return ( new $providerClass() )->setMod( $this->getMod() );
						},
						$mod->getController_SpamForms()->enumProviders()
					),
					function ( $provider ) {
						/** @var BaseHandler $provider */
						return !$provider->isEnabled() && $provider::IsProviderInstalled();
					}
				);

				if ( !empty( $installedButNotEnabledProviders ) ) {
					$warnings[] = sprintf( __( "%s has an integration available to protect the forms of a 3rd party plugin you're using: %s", 'wp-simple-firewall' ),
						$con->getHumanName(),
						implode( ', ', array_map(
							function ( $provider ) {
								return $provider->getHandlerName();
							}, $installedButNotEnabledProviders
						) )
					);
				}
				break;
		}

		return $warnings;
	}
}