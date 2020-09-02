<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;

/**
 * TODO: make a dedicated class that is not a processor.
 * Class ICWP_WPSF_Processor_Plugin_Tracking
 */
class ICWP_WPSF_Processor_Plugin_Tracking extends Shield\Modules\BaseShield\ShieldProcessor {

	/**
	 * Cron callback
	 */
	public function runDailyCron() {
		$this->sendTrackingData();
	}

	/**
	 * @return bool
	 */
	private function sendTrackingData() {
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();

		$bSuccess = false;

		$bCanSend = Services::Request()
							->carbon()
							->subWeek()->timestamp
					> (int)$oOpts->getOpt( 'tracking_last_sent_at', 0 );
		if ( $bCanSend && $oOpts->isTrackingEnabled() ) {

			$aData = $this->collectTrackingData();
			if ( !empty( $aData ) ) {
				$oOpts->setOpt( 'tracking_last_sent_at', Services::Request()->ts() );
				$bSuccess = Services::HttpRequest()->post(
					$oOpts->getDef( 'tracking_post_url' ),
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
		$data = apply_filters(
			$this->getCon()->prefix( 'collect_tracking_data' ),
			$this->getBaseTrackingData()
		);
		return is_array( $data ) ? $data : [];
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
}