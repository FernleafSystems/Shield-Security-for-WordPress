<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class UI extends Base\UI {

	/**
	 * @param string $section
	 * @return array
	 */
	protected function getSectionWarnings( $section ) {
		/** @var \ICWP_WPSF_FeatureHandler_AdminAccessRestriction $mod */
		$mod = $this->getMod();
		$aWarnings = [];

		switch ( $section ) {
			case 'section_whitelabel':
				if ( !$mod->isEnabledSecurityAdmin() ) {
					$aWarnings[] = __( 'Please also supply a Security Admin PIN, as whitelabel settings are only applied when the Security Admin feature is active.', 'wp-simple-firewall' );
				}
				break;
		}

		return $aWarnings;
	}
}