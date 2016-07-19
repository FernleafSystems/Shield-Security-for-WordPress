<?php

if ( !class_exists( 'ICWP_WPSF_Processor_Plugin_Tracking', false ) ):

	class ICWP_WPSF_Processor_Plugin_Tracking extends ICWP_WPSF_Processor_BaseWpsf {

		public function run() {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();
			$this->createTrackingCollectionCron();
			add_action( $oFO->getTrackingCronName(), array( $this, 'collectTrackingData' ) );
			add_action( $oFO->doPluginPrefix( 'delete_plugin' ), array( $this, 'deleteCron' ) );
		}

		/**
		 * Only done maximum once per week.
		 */
		public function collectTrackingData() {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();
			$oDP = $this->loadDataProcessor();
			if ( !$oFO->getTrackingEnabled() || ( $oDP->time() - $oFO->getLastTrackingSentAt() ) < WEEK_IN_SECONDS ) {
				return;
			}
			$aData = apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'collect_tracking_data' ), array() );
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