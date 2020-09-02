<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class UI extends Base\UI {

	/**
	 * @param string $section
	 * @return array
	 */
	protected function getSectionWarnings( $section ) {
		$aWarnings = [];

		if ( $section == 'section_brute_force_login_protection' && !$this->getCon()->isPremiumActive() ) {
			$sIntegration = $this->getPremiumOnlyIntegration();
			if ( !empty( $sIntegration ) ) {
				$aWarnings[] = sprintf( __( 'Support for login protection with %s is a Pro-only feature.', 'wp-simple-firewall' ), $sIntegration );
			}
		}

		if ( $section == 'section_2fa_email' ) {
			$aWarnings[] =
				__( '2FA by email demands that your WP site is properly configured to send email.', 'wp-simple-firewall' )
				.'<br/>'.__( 'This is a common problem and you may get locked out in the future if you ignore this.', 'wp-simple-firewall' )
				.' '.sprintf( '<a href="%s" target="_blank" class="alert-link">%s</a>', 'https://shsec.io/dd', __( 'Learn More.', 'wp-simple-firewall' ) );
		}

		return $aWarnings;
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
		foreach ( $aIntegrations as $sInt => $sName ) {
			if ( class_exists( $sInt ) ) {
				$sIntegration = $sName;
				break;
			}
		}
		return $sIntegration;
	}
}