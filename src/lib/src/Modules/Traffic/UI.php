<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	/**
	 * @param string $section
	 * @return array
	 */
	protected function getSectionWarnings( $section ) {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		$aWarnings = [];

		$oIp = Services::IP();
		if ( !$oIp->isValidIp_PublicRange( $oIp->getRequestIp() ) ) {
			$aWarnings[] = __( 'Traffic Watcher will not run because visitor IP address detection is not correctly configured.', 'wp-simple-firewall' );
		}

		switch ( $section ) {
			case 'section_traffic_limiter':
				if ( $this->getCon()->isPremiumActive() ) {
					if ( !$oOpts->isTrafficLoggerEnabled() ) {
						$aWarnings[] = sprintf( __( '%s may only be enabled if the Traffic Logger feature is also turned on.', 'wp-simple-firewall' ), __( 'Traffic Rate Limiter', 'wp-simple-firewall' ) );
					}
				}
				else {
					$aWarnings[] = sprintf( __( '%s is a Pro-only feature.', 'wp-simple-firewall' ), __( 'Traffic Rate Limiter', 'wp-simple-firewall' ) );
				}
				break;
		}

		return $aWarnings;
	}
}