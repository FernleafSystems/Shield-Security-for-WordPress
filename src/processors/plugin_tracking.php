<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Plugin_Tracking extends ICWP_WPSF_Processor_BasePlugin {

	/**
	 * @return bool
	 */
	private function sendTrackingData() {
		$bSuccess = false;
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();

		if ( $oMod->isTrackingEnabled() && $oMod->readyToSendTrackingData() ) {

			$aData = $this->collectTrackingData();
			if ( !empty( $aData ) && is_array( $aData ) ) {
				$oMod->setTrackingLastSentAt();
				$bSuccess = Services::HttpRequest()->post(
					$oMod->getDef( 'tracking_post_url' ),
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