<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class UI extends Base\ShieldUI {

	protected function getSectionWarnings( string $section ) :array {
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

	public function getInsightsNoticesData() :array {
		/** @var \ICWP_WPSF_FeatureHandler_AdminAccessRestriction $mod */
		$mod = $this->getMod();

		$notices = [
			'title'    => __( 'Security Admin Protection', 'wp-simple-firewall' ),
			'messages' => []
		];

		{//sec admin
			if ( !$mod->isEnabledSecurityAdmin() ) {
				$notices[ 'messages' ][ 'sec_admin' ] = [
					'title'   => __( 'Security Plugin Unprotected', 'wp-simple-firewall' ),
					'message' => sprintf(
						__( "The Security Admin protection is not active.", 'wp-simple-firewall' ),
						$this->getCon()->getHumanName()
					),
					'href'    => $mod->getUrl_AdminPage(),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options' ) ),
					'rec'     => __( 'Security Admin should be turned-on to protect your security settings.', 'wp-simple-firewall' )
				];
			}
		}

		return $notices;
	}

	public function isEnabledForUiSummary() :bool {
		/** @var \ICWP_WPSF_FeatureHandler_AdminAccessRestriction $mod */
		$mod = $this->getMod();
		return $this->getMod()->isModuleEnabled() && $mod->isEnabledSecurityAdmin();
	}
}