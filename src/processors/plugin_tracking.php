<?php

if ( !class_exists( 'ICWP_WPSF_Processor_Plugin_Tracking', false ) ):

	class ICWP_WPSF_Processor_Plugin_Tracking extends ICWP_WPSF_Processor_BasePlugin {

		public function run() {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();

			if ( $oFO->getTrackingEnabled() ) {
				$this->createTrackingCollectionCron();
				add_action( $oFO->getTrackingCronName(), array( $this, 'sendTrackingData' ) );
			}
			add_action( $oFO->doPluginPrefix( 'delete_plugin' ), array( $this, 'deleteCron' ) );
		}

		/**
		 * @see autoAddToAdminNotices()
		 * @param array $aNoticeAttributes
		 */
		protected function addNotice_allow_tracking( $aNoticeAttributes ) {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();

			if ( $this->getIfShowAdminNotices() && !$oFO->getTrackingPermissionSet() ) {
				$oCon = $this->getController();
				$aRenderData = array(
					'notice_attributes' => $aNoticeAttributes,
					'strings' => array(
						'help_us' => sprintf( _wpsf__( "Will you help us to make %s even better?" ), $oCon->getHumanName() ),
						'want_to_track' =>  _wpsf__( "We're working to understand how people, just like you, use this plugin every day." ),
						'what_we_collect' => _wpsf__( "We'd like to understand better the features used most and how effective we are on a global scale." ),
						'data_anon' => _wpsf__( 'The data sent will be always completely anonymous and we will never be able to track you or your website.' ),
						'can_turn_off' => _wpsf__( 'You can easily turn it off at any time within the plugin options if you change your mind.' ),
						'click_to_see' => _wpsf__( 'Click to see the RAW data that would be sent' ),
						'learn_more' => _wpsf__( 'Learn More.' ),
						'site_url' => 'translate.icontrolwp.com',
						'yes' => _wpsf__( 'Absolutely' )
					),
					'hrefs' => array(
						'learn_more' => 'http://translate.icontrolwp.com',
						'link_to_see' => $oFO->getLinkToTrackingDataDump(),
						'link_to_moreinfo' => 'http://icwp.io/shieldtrackinginfo',

					)
				);
				$this->insertAdminNotice( $aRenderData );
			}
		}

		/**
		 */
		public function sendTrackingData() {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();
			if ( !$oFO->getTrackingEnabled() || !$oFO->readyToSendTrackingData() ) {
				return;
			}

			$aData = $this->collectTrackingData();
			if ( empty( $aData ) || !is_array( $aData ) ) {
				return;
			}

			$this->loadFileSystemProcessor()->requestUrl(
				$oFO->getDefinition( 'tracking_post_url' ),
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
			$oFO->setTrackingLastSentAt();
		}

		/**
		 * @return array
		 */
		public function collectTrackingData() {
			$aData = apply_filters(
				$this->getFeatureOptions()->doPluginPrefix( 'collect_tracking_data' ),
				$this->getBaseTrackingData()
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
				'env' =>array(
					'options' => array(
						'php' => $oDP->getPhpVersion(),
						'wordpress' => $oWP->getWordpressVersion(),
						'slug' => $this->getController()->getPluginSlug(),
						'version' => $this->getController()->getVersion(),
						'is_wpms' => $oWP->isMultisite() ? 1 : 0,
						'ssl' => ( $oDP->FetchServer( 'HTTPS' ) == 'on' ) ? 1 : 0,
						'locale' => get_locale(),
						'plugins_total' => count( $oWP->getPlugins() ),
						'plugins_active' => count( $oWP->getActivePlugins() ),
						'plugins_updates' => count( $oWP->getWordpressUpdates_Plugins() )
					)
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
				$nNextRun = strtotime( 'tomorrow 3am' ) - get_option( 'gmt_offset' ) * HOUR_IN_SECONDS + rand(0,1800);
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