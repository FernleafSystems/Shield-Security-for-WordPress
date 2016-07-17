<?php

if ( !class_exists( 'ICWP_WPSF_Processor_Base', false ) ):

	abstract class ICWP_WPSF_Processor_Base extends ICWP_WPSF_Foundation {

		/**
		 * @var ICWP_WPSF_FeatureHandler_Base
		 */
		protected $oFeatureOptions;

		/**
		 * @param ICWP_WPSF_FeatureHandler_Base $oFeatureOptions
		 */
		public function __construct( $oFeatureOptions ) {
			$this->oFeatureOptions = $oFeatureOptions;
			add_action( $oFeatureOptions->doPluginPrefix( 'plugin_shutdown' ), array( $this, 'action_doFeatureProcessorShutdown' ) );
			add_action( $oFeatureOptions->doPluginPrefix( 'generate_admin_notices' ), array( $this, 'autoAddToAdminNotices' ) );
			if ( method_exists( $this, 'addToAdminNotices' ) ) {
				add_action( $oFeatureOptions->doPluginPrefix( 'generate_admin_notices' ), array( $this, 'addToAdminNotices' ) );
			}
			$this->init();
		}

		/**
		 * @return ICWP_WPSF_Plugin_Controller
		 */
		public function getController() {
			return $this->getFeatureOptions()->getController();
		}

		public function autoAddToAdminNotices() {
			$oCon = $this->getController();

			foreach( $this->getFeatureOptions()->getOptionsVo()->getAdminNotices() as $sNoticeId => $aNoticeAttributes ) {

				if ( !$this->getIfDisplayAdminNotice( $aNoticeAttributes ) ) {
					continue;
				}

				$sMethodName = 'addNotice_'.str_replace( '-', '_', $sNoticeId );
				if ( method_exists( $this, $sMethodName )
					&& isset( $aNoticeAttributes['valid_admin'] ) && $aNoticeAttributes['valid_admin'] && $oCon->getIsValidAdminArea() ) {

					$aNoticeAttributes[ 'notice_id' ] = $sNoticeId;
					call_user_func( array( $this, $sMethodName ), $aNoticeAttributes );
				}
			}
		}

		/**
		 * @param array $aNoticeAttributes
		 * @return bool
		 */
		protected function getIfDisplayAdminNotice( $aNoticeAttributes ) {
			$oWpNotices = $this->loadAdminNoticesProcessor();

			if ( empty( $aNoticeAttributes['schedule'] ) || !in_array( $aNoticeAttributes['schedule'], array( 'once', 'conditions', 'version' ) ) ) {
				$aNoticeAttributes[ 'schedule' ] = 'conditions';
			}

			if ( $aNoticeAttributes[ 'schedule' ] == 'once'
				&& ( !$this->loadWpUsersProcessor()->getCanAddUpdateCurrentUserMeta() || $oWpNotices->getAdminNoticeIsDismissed( $aNoticeAttributes['id'] ) )
			) {
				return false;
			}

			if ( $aNoticeAttributes['schedule'] == 'version' && ( $this->getFeatureOptions()->getVersion() == $oWpNotices->getAdminNoticeMeta( $aNoticeAttributes['id'] ) ) ) {
				return false;
			}

			if ( isset( $aNoticeAttributes['type'] ) && $aNoticeAttributes['type'] == 'promo' && $this->loadWpFunctionsProcessor()->getIsMobile() ) {
				return false;
			}

			return true;
		}

		public function action_doFeatureProcessorShutdown() {}

		/**
		 * Resets the object values to be re-used anew
		 */
		public function init() {}

		/**
		 * Override to set what this processor does when it's "run"
		 */
		abstract public function run();

		/**
		 * @param array $aNoticeData
		 */
		protected function insertAdminNotice( $aNoticeData ) {
			$sRenderedNotice = $this->getFeatureOptions()->renderAdminNotice( $aNoticeData );
			if ( !empty( $sRenderedNotice ) ) {
				$this->loadAdminNoticesProcessor()->addAdminNotice(
					$sRenderedNotice,
					$aNoticeData['notice_attributes']['notice_id']
				);
			}
		}

		/**
		 * @param $sOptionKey
		 * @param mixed $mDefault
		 * @return mixed
		 */
		public function getOption( $sOptionKey, $mDefault = false ) {
			return $this->getFeatureOptions()->getOpt( $sOptionKey, $mDefault );
		}

		/**
		 * @param string $sKey
		 * @param mixed $mValueToTest
		 * @param boolean $bStrict
		 *
		 * @return bool
		 */
		public function getIsOption( $sKey, $mValueToTest, $bStrict = false ) {
			$mOptionValue = $this->getOption( $sKey );
			return $bStrict? $mOptionValue === $mValueToTest : $mOptionValue == $mValueToTest;
		}

		/**
		 * @param array $aIpList
		 * @param integer $nIpAddress
		 * @param string $outsLabel
		 * @return boolean
		 */
		public function isIpOnlist( $aIpList, $nIpAddress = 0, &$outsLabel = '' ) {

			if ( empty( $nIpAddress ) || empty( $aIpList['ips'] ) || !is_array( $aIpList['ips'] ) ) {
				return false;
			}

			$outsLabel = '';
			foreach( $aIpList['ips'] as $mWhitelistAddress ) {

				$aIps = $this->parseIpAddress( $mWhitelistAddress );
				if ( count( $aIps ) === 1 ) { //not a range
					if ( $nIpAddress == $aIps[0] ) {
						$outsLabel = $aIpList['meta'][ md5( $mWhitelistAddress ) ];
						return true;
					}
				}
				else if ( count( $aIps ) == 2 ) {
					if ( $aIps[0] <= $nIpAddress && $nIpAddress <= $aIps[1] ) {
						$outsLabel = $aIpList['meta'][ md5( $mWhitelistAddress ) ];
						return true;
					}
				}
			}
			return false;
		}

		/**
		 * @param string $sIpAddress	- an IP or IP address range in LONG format.
		 * @return array				- with 1 ip address, or 2 addresses if it is a range.
		 */
		protected function parseIpAddress( $sIpAddress ) {

			$aIps = array();
			if ( empty( $sIpAddress ) ) {
				return $aIps;
			}

			// offset=1 in the case that it's a range and the first number is negative on 32-bit systems
			$mPos = strpos( $sIpAddress, '-', 1 );

			if ( $mPos === false ) { // plain IP address
				$aIps[] = $sIpAddress;
			}
			else {
				//we remove the first character in case this is '-'
				$aParts = array( substr( $sIpAddress, 0, 1 ), substr( $sIpAddress, 1 ) );
				list( $sStart, $sEnd ) = explode( '-', $aParts[1], 2 );
				$aIps[] = $aParts[0].$sStart;
				$aIps[] = $sEnd;
			}
			return $aIps;
		}

		/**
		 * @return mixed
		 */
		public function getPluginDefaultRecipientAddress() {
			$oWp = $this->loadWpFunctionsProcessor();
			return apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'report_email_address' ), $oWp->getSiteAdminEmail() );
		}

		/**
		 * @return ICWP_WPSF_Processor_Email
		 */
		public function getEmailProcessor() {
			return $this->getFeatureOptions()->getEmailProcessor();
		}

		/**
		 * @return ICWP_WPSF_FeatureHandler_Base
		 */
		protected function getFeatureOptions() {
			return $this->oFeatureOptions;
		}

		/**
		 * @return bool|int|string
		 */
		protected function human_ip() {
			return $this->loadDataProcessor()->getVisitorIpAddress();
		}

		/**
		 * @return bool|int
		 */
		protected function ip() {
			return $this->loadDataProcessor()->getVisitorIpAddress( false );
		}

		/**
		 * @return int
		 */
		protected function time() {
			return $this->loadDataProcessor()->GetRequestTime();
		}
	}

endif;