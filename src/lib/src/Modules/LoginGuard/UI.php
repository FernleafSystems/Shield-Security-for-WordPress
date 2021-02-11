<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Time\WorldTimeApi;

class UI extends BaseShield\UI {

	protected function getSectionWarnings( string $section ) :array {
		$warnings = [];

		if ( $section == 'section_brute_force_login_protection' && !$this->getCon()->isPremiumActive() ) {
			$sIntegration = $this->getPremiumOnlyIntegration();
			if ( !empty( $sIntegration ) ) {
				$warnings[] = sprintf( __( 'Support for login protection with %s is a Pro-only feature.', 'wp-simple-firewall' ), $sIntegration );
			}
		}

		if ( $section == 'section_2fa_ga' ) {
			try {
				$diff = ( new WorldTimeApi() )->diffServerWithReal();
				if ( $diff > 10 ) {
					$warnings[] = __( 'It appears that your server time configuration is out of sync - Please contact your server admin, as features like Google Authenticator wont work.', 'wp-simple-firewall' );
				}
			}
			catch ( \Exception $e ) {
			}
		}

		if ( $section == 'section_2fa_email' ) {
			$warnings[] =
				__( '2FA by email demands that your WP site is properly configured to send email.', 'wp-simple-firewall' )
				.'<br/>'.__( 'This is a common problem and you may get locked out in the future if you ignore this.', 'wp-simple-firewall' )
				.' '.sprintf( '<a href="%s" target="_blank" class="alert-link">%s</a>', 'https://shsec.io/dd', __( 'Learn More.', 'wp-simple-firewall' ) );
		}

		return $warnings;
	}

	/**
	 * @return string
	 */
	private function getPremiumOnlyIntegration() {
		$aIntegrations = [
			'WooCommerce'            => 'WooCommerce',
			'Easy_Digital_Downloads' => 'Easy Digital Downloads',
			'BuddyPress'             => 'BuddyPress',
		];

		$sIntegration = '';
		foreach ( $aIntegrations as $classToIntegrate => $sName ) {
			if ( class_exists( $classToIntegrate ) ) {
				$sIntegration = $sName;
				break;
			}
		}
		return $sIntegration;
	}
}