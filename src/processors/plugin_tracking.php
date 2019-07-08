<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Plugin_Tracking extends ICWP_WPSF_Processor_BasePlugin {

	/**
	 * @param array $aNoticeAttributes
	 * @see autoAddToAdminNotices()
	 */
	protected function addNotice_allow_tracking( $aNoticeAttributes ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		if ( $this->getIfShowAdminNotices() && !$oFO->isTrackingPermissionSet() ) {
			$oCon = $this->getCon();
			$aRenderData = [
				'notice_attributes' => $aNoticeAttributes,
				'strings'           => [
					'title'           => sprintf( __( "Make %s even better by sharing usage info?", 'wp-simple-firewall' ), $oCon->getHumanName() ),
					'want_to_track'   => sprintf( __( "We're hoping to understand how %s is configured and used.", 'wp-simple-firewall' ), $oCon->getHumanName() ),
					'what_we_collect' => __( "We'd like to understand how effective it is on a global scale.", 'wp-simple-firewall' ),
					'data_anon'       => __( 'The data sent is always completely anonymous and we can never track you or your site.', 'wp-simple-firewall' ),
					'can_turn_off'    => __( 'It can be turned-off at any time within the plugin options.', 'wp-simple-firewall' ),
					'click_to_see'    => __( 'Click to see the RAW data that would be sent', 'wp-simple-firewall' ),
					'learn_more'      => __( 'Learn More.', 'wp-simple-firewall' ),
					'site_url'        => 'translate.icontrolwp.com',
					'yes'             => __( 'Absolutely', 'wp-simple-firewall' ),
					'yes_i_share'     => __( "Yes, I'd be happy share this info", 'wp-simple-firewall' ),
					'hmm_learn_more'  => __( "I'd like to learn more, please", 'wp-simple-firewall' ),
					'no_help'         => __( "No, I don't want to help", 'wp-simple-firewall' ),
				],
				'ajax'              => [
					'set_plugin_tracking' => $oFO->getAjaxActionData( 'set_plugin_tracking', true ),
				],
				'hrefs'             => [
					'learn_more'       => 'http://translate.icontrolwp.com',
					'link_to_see'      => $oFO->getLinkToTrackingDataDump(),
					'link_to_moreinfo' => 'https://icwp.io/shieldtrackinginfo',

				]
			];
			$this->insertAdminNotice( $aRenderData );
		}
	}

	/**
	 * @return bool
	 */
	private function sendTrackingData() {
		$bSuccess = false;
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();

		if ( $oFO->isTrackingEnabled() && $oFO->readyToSendTrackingData() ) {

			$aData = $this->collectTrackingData();
			if ( !empty( $aData ) && is_array( $aData ) ) {
				$oFO->setTrackingLastSentAt();
				$bSuccess = Services::HttpRequest()->post(
					$oFO->getDef( 'tracking_post_url' ),
					[
						'timeout'     => 20,
						'redirection' => 5,
						'httpversion' => '1.1',
						'blocking'    => true,
						'body'        => [ 'tracking_data' => $aData ],
						'user-agent'  => 'SHIELD/'.$this->getCon()->getVersion().';'
					]
				);
			}
		}

		return $bSuccess;
	}

	/**
	 * @return array
	 */
	public function collectTrackingData() {
		$aData = apply_filters(
			$this->getMod()->prefix( 'collect_tracking_data' ),
			$this->getBaseTrackingData()
		);
		return is_array( $aData ) ? $aData : [];
	}

	/**
	 * @return array
	 */
	protected function getBaseTrackingData() {
		$oWP = Services::WpGeneral();
		$oWpPlugins = Services::WpPlugins();
		return [
			'env' => [
				'options' => [
					'php'             => Services::Data()->getPhpVersionCleaned(),
					'wordpress'       => $oWP->getVersion(),
					'slug'            => $this->getCon()->getPluginSlug(),
					'version'         => $this->getCon()->getVersion(),
					'is_wpms'         => $oWP->isMultisite() ? 1 : 0,
					'is_cp'           => $oWP->isClassicPress() ? 1 : 0,
					'ssl'             => is_ssl() ? 1 : 0,
					'locale'          => get_locale(),
					'plugins_total'   => count( $oWpPlugins->getPlugins() ),
					'plugins_active'  => count( $oWpPlugins->getActivePlugins() ),
					'plugins_updates' => count( $oWpPlugins->getUpdates() )
				]
			]
		];
	}

	/**
	 * Cron callback
	 */
	public function runDailyCron() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isTrackingEnabled() ) {
			$this->sendTrackingData();
		}
	}
}