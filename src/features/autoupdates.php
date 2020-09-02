<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Autoupdates extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @param array $aAllNotices
	 * @return array
	 */
	public function addInsightsNoticeData( $aAllNotices ) {
		/** @var Autoupdates\Options $oOpts */
		$oOpts = $this->getOptions();

		$aNotices = [
			'title'    => __( 'Automatic Updates', 'wp-simple-firewall' ),
			'messages' => []
		];
		{ //really disabled?
			$oWp = Services::WpGeneral();
			if ( $this->isModOptEnabled() ) {
				if ( $oOpts->isDisableAllAutoUpdates() && !$oWp->getWpAutomaticUpdater()->is_disabled() ) {
					$aNotices[ 'messages' ][ 'disabled_auto' ] = [
						'title'   => 'Auto Updates Not Really Disabled',
						'message' => __( 'Automatic Updates Are Not Disabled As Expected.', 'wp-simple-firewall' ),
						'href'    => $this->getUrl_DirectLinkToOption( 'enable_autoupdate_disable_all' ),
						'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
						'rec'     => sprintf( __( 'A plugin/theme other than %s is affecting your automatic update settings.', 'wp-simple-firewall' ), $this->getCon()
																																							->getHumanName() )
					];
				}
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );

		$aAllNotices[ 'autoupdates' ] = $aNotices;
		return $aAllNotices;
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'Autoupdates';
	}
}