<?php

if ( !class_exists( 'ICWP_WPSF_Processor_Plugin_Tracking', false ) ):

	class ICWP_WPSF_Processor_Plugin_Tracking extends ICWP_WPSF_Processor_BaseWpsf {

		public function run() {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();
			$this->createTrackingCollectionCron();
			add_action( $oFO->getTrackingCronName(), array( $this, 'sendTrackingData' ) );
			add_action( $oFO->doPluginPrefix( 'delete_plugin' ), array( $this, 'deleteCron' ) );
			if ( isset( $_GET['test'] ) ) {
				add_action( 'init', array( $this, 'sendTrackingData' ) );
			}
		}

		/**
		 * Only done maximum once per week.
		 */
		public function sendTrackingData() {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();
			$oDP = $this->loadDataProcessor();

			if ( !$oFO->getTrackingEnabled() || ( $oDP->time() - $oFO->getLastTrackingSentAt() ) < WEEK_IN_SECONDS ) {
				return;
			}

			$aData = $this->collectTrackingData();
			if ( empty( $aData ) || !is_array( $aData ) ) {
				return;
			}

			$sUrl = $oFO->getDefinition( 'tracking_post_url' );
			$oFS = $this->loadFileSystemProcessor();
			$oResult = $oFS->requestUrl(
				$sUrl,
				array(
					'method'      => 'POST',
					'timeout'     => 20,
					'redirection' => 5,
					'httpversion' => '1.1',
					'blocking'    => true,
					'body'        => array( 'tracking_data' => $aData ),
					'user-agent'  => 'SHIELD/'.$this->getController()->getVersion().';'
				),
				true
			);
			$oFO->updateLastTrackingSentAt();
		}

		/**
		 * @return array
		 */
		protected function collectTrackingData() {
			$aData = apply_filters(
				$this->getFeatureOptions()->doPluginPrefix( 'collect_tracking_data' ),
				array( 'env' => $this->getBaseTrackingData() )
			);
			return is_array( $aData ) ? $aData : array();
		}

		/**
		 * @return array
		 */
		protected function getBaseTrackingData() {
			$oDP = $this->loadDataProcessor();
			$oWP = $this->loadWpFunctionsProcessor();

			return array(
				'php' => $oDP->getPhpVersion(),
				'wordpress' => $oWP->getWordpressVersion(),
				'version' => $this->getController()->getVersion(),
				'is_wpms' => $oWP->isMultisite() ? 1 : 0,
				'ssl' => ( $oDP->FetchServer( 'HTTPS' ) == 'on' ) ? 1 : 0,
				'locale' => get_locale(),
				'plugins' => array(
					'count_total' => count( $oWP->getPlugins() ),
					'count_active' => count( $oWP->getActivePlugins() ),
					'count_updates' => count( $oWP->getWordpressUpdates_Plugins() )
				)
			);
		}

		/**
		 */
		protected function createTrackingCollectionCron() {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();
			$sFullHookName = $oFO->getTrackingCronName();
			if ( ! wp_next_scheduled( $sFullHookName ) && ! defined( 'WP_INSTALLING' ) ) {
				$nNextRun = strtotime( 'tomorrow 3am' ) - get_option( 'gmt_offset' ) * HOUR_IN_SECONDS + rand(0,1200);
				wp_schedule_event( $nNextRun, 'daily', $sFullHookName );
			}
		}

		public function deleteCron() {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();
			wp_clear_scheduled_hook( $oFO->getTrackingCronName() );
		}
	}

endif;