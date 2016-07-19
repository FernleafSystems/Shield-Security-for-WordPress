<?php

if ( !class_exists( 'ICWP_WPSF_Processor_BaseWpsf', false ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base.php' );

	abstract class ICWP_WPSF_Processor_BaseWpsf extends ICWP_WPSF_Processor_Base {

		/**
		 * @var array
		 */
		private $aAuditEntry;

		/**
		 * @var array
		 */
		private $aStatistics;

		/**
		 * Resets the object values to be re-used anew
		 */
		public function init() {
			$oFO = $this->getFeatureOptions();
			add_filter( $oFO->doPluginPrefix( 'collect_audit_trail' ), array( $this, 'audit_Collect' ) );
			add_filter( $oFO->doPluginPrefix( 'collect_stats' ), array( $this, 'stats_Collect' ) );
			add_filter( $this->getFeatureOptions()->doPluginPrefix( 'collect_tracking_data' ), array( $this, 'data_Collect' ) );
		}

		/**
		 * Filter used to collect plugin data for tracking.  Fired from the plugin processor only if the option is enabled
		 * - it is not enabled by default.
		 *
		 * @param $aData
		 * @return array
		 */
		public function data_Collect( $aData ) {
			$oFO = $this->getFeatureOptions();
			if ( !is_array( $aData ) ) {
				$aData = array();
			}
			$aData[ $oFO->getFeatureSlug() ] = array(
				'options' => $oFO->getOptionsVo()->getAllOptionsValues()
			);
			return $aData;
		}

		/**
		 * A filter used to collect all the stats gathered in the plugin.
		 *
		 * @param array $aStats
		 * @return array
		 */
		public function stats_Collect( $aStats ) {
			if ( !is_array( $aStats ) ) {
				$aStats = array();
			}
			$aThisStats = $this->stats_Get();
			if ( !empty( $aThisStats ) && is_array( $aThisStats ) ) {
				$aStats[] = $aThisStats;
			}
			return $aStats;
		}

		/**
		 * @param string $sStatKey
		 */
		private function stats_Increment( $sStatKey ) {
			$aStats = $this->stats_Get();
			if ( !isset( $aStats[ $sStatKey ] ) ) {
				$aStats[ $sStatKey ] = 0;
			}
			$aStats[ $sStatKey ] = $aStats[ $sStatKey ] + 1;
			$this->aStatistics = $aStats;
		}

		/**
		 * @return array
		 */
		public function stats_Get() {
			if ( !isset( $this->aStatistics ) || !is_array( $this->aStatistics ) ) {
				$this->aStatistics = array();
			}
			return $this->aStatistics;
		}

		/**
		 * This is the preferred method over $this->stat_Increment() since it handles the parent stat key
		 *
		 * @param string $sStatKey
		 * @param string $sParentStatKey
		 */
		protected function doStatIncrement( $sStatKey, $sParentStatKey = '' ) {
			$this->stats_Increment( $sStatKey.':'.( empty( $sParentStatKey ) ? $this->getFeatureOptions()->getFeatureSlug() : $sParentStatKey ) );
		}

		/**
		 * @param array $aAuditEntries
		 * @return array
		 */
		public function audit_Collect( $aAuditEntries ) {
			if ( !is_array( $aAuditEntries ) ) {
				$aAuditEntries = array();
			}
			if ( isset( $this->aAuditEntry ) && is_array( $this->aAuditEntry ) ) {
				$aAuditEntries[] = $this->aAuditEntry;
			}
			return $aAuditEntries;
		}

		/**
		 * @param string $sAdditionalMessage
		 * @param int $nCategory
		 * @param string $sEvent
		 * @param string $sWpUsername
		 */
		protected function addToAuditEntry( $sAdditionalMessage = '', $nCategory = 1, $sEvent = '', $sWpUsername = '' ) {
			if ( !isset( $this->aAuditEntry ) ) {

				if ( empty( $sWpUsername ) ) {
					$oCurrentUser = $this->loadWpUsersProcessor()->getCurrentWpUser();
					$sWpUsername = empty( $oCurrentUser ) ? 'unknown' : $oCurrentUser->get( 'user_login' );
				}

				$this->aAuditEntry = array(
					'created_at' => $this->time(),
					'wp_username' => $sWpUsername,
					'context' => 'wpsf',
					'event' => $sEvent,
					'category' => $nCategory,
					'message' => array()
				);
			}

			$this->aAuditEntry['message'][] = $sAdditionalMessage;

			if ( $nCategory > $this->aAuditEntry['category'] ) {
				$this->aAuditEntry['category'] = $nCategory;
			}
			if ( !empty( $sEvent ) ) {
				$this->aAuditEntry['event'] = $sEvent;
			}
		}

		/**
		 * @param string $sSeparator
		 * @return string
		 */
		protected function getAuditMessage( $sSeparator = ' ' ) {
			return implode( $sSeparator, $this->getRawAuditMessage() );
		}

		/**
		 * @param string $sLinePrefix
		 * @return array
		 */
		protected function getRawAuditMessage( $sLinePrefix = '' ) {
			if ( isset( $this->aAuditEntry['message'] ) && is_array( $this->aAuditEntry['message'] ) && !empty( $sLinePrefix ) ) {
				$aAuditMessages = array();
				foreach( $this->aAuditEntry['message'] as $sMessage ) {
					$aAuditMessages[] = $sLinePrefix.$sMessage;
				}
				return $aAuditMessages;
			}
			return isset( $this->aAuditEntry['message'] ) ? $this->aAuditEntry['message'] : array();
		}
	}

endif;