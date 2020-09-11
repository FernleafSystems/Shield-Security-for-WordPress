<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	public function getInsightsNoticesData() :array {
		/** @var \ICWP_WPSF_FeatureHandler_Autoupdates $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$notices = [
			'title'    => __( 'Automatic Updates', 'wp-simple-firewall' ),
			'messages' => []
		];

		{ //really disabled?
			$WP = Services::WpGeneral();
			if ( $mod->isModOptEnabled() ) {
				if ( $opts->isDisableAllAutoUpdates() && !$WP->getWpAutomaticUpdater()->is_disabled() ) {
					$notices[ 'messages' ][ 'disabled_auto' ] = [
						'title'   => 'Auto Updates Not Really Disabled',
						'message' => __( 'Automatic Updates Are Not Disabled As Expected.', 'wp-simple-firewall' ),
						'href'    => $mod->getUrl_DirectLinkToOption( 'enable_autoupdate_disable_all' ),
						'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
						'rec'     => sprintf( __( 'A plugin/theme other than %s is affecting your automatic update settings.', 'wp-simple-firewall' ), $this->getCon()
																																							->getHumanName() )
					];
				}
			}
		}

		return $notices;
	}
}