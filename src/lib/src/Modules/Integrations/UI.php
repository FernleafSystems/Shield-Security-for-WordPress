<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;

class UI extends Modules\BaseShield\UI {

	protected function getSectionNotices( string $section ) :array {
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
							__( "The following forms are protected by AntiBot Detection: %s.", 'wp-simple-firewall' ),
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

	protected function getSectionWarnings( string $section ) :array {
		$warnings = [];

		/** @var Modules\LoginGuard\Options $loginGuardOpts */
		$loginGuardOpts = $this->getCon()->getModule_LoginGuard()->getOptions();

		switch ( $section ) {

			case 'section_user_forms':
				if ( !$loginGuardOpts->isEnabledAntiBot() ) {
					$warnings[] = sprintf( '%s: %s %s', __( 'Important', 'wp-simple-firewall' ),
						__( "Use of the AntiBot Detection Engine for user forms isn't turned on in the Login Guard module.", 'wp-simple-firewall' ),
						sprintf( '<a href="%s" target="_blank">%s</a>',
							$this->getCon()->getModule_LoginGuard()->getUrl_AdminPage(),
							__( 'Click here to review those settings.', 'wp-simple-firewall' ) )
					);
				}
				break;
		}

		return $warnings;
	}
}