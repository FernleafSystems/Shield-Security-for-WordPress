<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	/**
	 * @param string $section
	 * @return array
	 */
	protected function getSectionWarnings( $section ) {
		$aWarnings = [];

		/** @var Options $opts */
		$opts = $this->getOptions();

		switch ( $section ) {

			case 'section_auto_black_list':
				if ( !$opts->isEnabledAutoBlackList() ) {
					$aWarnings[] = sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( "IP blocking is turned-off because the offenses limit is set to 0.", 'wp-simple-firewall' ) );
				}
				break;

			case 'section_behaviours':
			case 'section_probes':
			case 'section_logins':
				if ( !$opts->isEnabledAutoBlackList() ) {
					$aWarnings[] = __( "Since the offenses limit is set to 0, these options have no effect.", 'wp-simple-firewall' );
				}

				if ( $section == 'section_behaviours' && strlen( Services::Request()->getUserAgent() ) == 0 ) {
					$aWarnings[] = __( "Your User Agent appears to be empty. We recommend not turning on this option.", 'wp-simple-firewall' );
				}
				break;
		}

		return $aWarnings;
	}
}