<?php
/**
 * Copyright (c) 2015 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

if ( !class_exists('ICWP_WPSF_BaseProcessor_V3') ):

	abstract class ICWP_WPSF_BaseProcessor_V3 extends ICWP_WPSF_Foundation {

		const PcreDelimiter = '/';

		/**
		 * @var int
		 */
		protected static $nRequestIp;
		/**
		 * @var int
		 */
		protected static $nRequestPostId;
		/**
		 * @var integer
		 */
		protected static $nRequestTimestamp;

		/**
		 * @var array
		 */
		private $aAuditEntry;

		/**
		 * @var array
		 */
		protected $aAdminNotices;

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
			add_filter( $oFeatureOptions->doPluginPrefix( 'wpsf_audit_trail_gather' ), array( $this, 'getAuditEntry' ) );
			add_filter( $oFeatureOptions->doPluginPrefix( 'admin_notices' ), array( $this, 'fGetAdminNotices' ) );
			$this->reset();
		}

		/**
		 * @return ICWP_WPSF_Plugin_Controller
		 */
		public function getController() {
			return $this->getFeatureOptions()->getController();
		}

		public function action_doFeatureProcessorShutdown() { }

		/**
		 * Resets the object values to be re-used anew
		 */
		public function reset() { }

		/**
		 * Override to set what this processor does when it's "run"
		 */
		abstract public function run();

		/**
		 * @param array $sNotice
		 */
		public function doAddAdminNotice( $sNotice ) {
			if ( empty( $sNotice ) ) {
				return;
			}
			$aCurrentNotices = $this->getAdminNotices();
			$aCurrentNotices[] = $sNotice;
			$this->aAdminNotices = $aCurrentNotices;
		}

		/**
		 * @param array $aNotices
		 *
		 * @return array
		 */
		public function fGetAdminNotices( $aNotices ) {
			if ( is_array( $aNotices ) ) {
				$aNotices = array_merge( $aNotices, $this->getAdminNotices() );
			}
			return $aNotices;
		}

		/**
		 * @return array
		 */
		public function getAdminNotices() {
			if ( !isset( $this->aAdminNotices ) || !is_array( $this->aAdminNotices ) ) {
				$this->aAdminNotices = array();
			}
			return $this->aAdminNotices;
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
		 * @return bool|int
		 */
		public function getRequestPostId() {
			if ( !isset( self::$nRequestPostId ) ) {
				global $post;
				if ( empty( $post ) ) {
					return false;
				}
				self::$nRequestPostId = $post->ID;
			}
			return self::$nRequestPostId;
		}

		/**
		 * @param array $aAuditEntries
		 *
		 * @return array
		 */
		public function getAuditEntry( $aAuditEntries ) {
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
					$oCurrentUser = $this->loadWpFunctionsProcessor()->getCurrentWpUser();
					$sWpUsername = empty( $oCurrentUser ) ? 'unknown' : $oCurrentUser->get( 'user_login' );
				}

				$this->aAuditEntry = array(
					'created_at' => $this->loadDataProcessor()->GetRequestTime(),
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

		/**
		 * @param string $sEvent
		 * @param int $nCategory
		 * @param string $sMessage
		 */
		public function writeAuditEntry( $sEvent, $nCategory = 1, $sMessage = '' ) {
			$oWp = $this->loadWpFunctionsProcessor();
			$oCurrentUser = $oWp->getCurrentWpUser();
			$this->aAuditEntry = array(
				'created_at' => $this->loadDataProcessor()->GetRequestTime(),
				'wp_username' => empty( $oCurrentUser ) ? 'unknown' : $oCurrentUser->get( 'user_login' ),
				'context' => 'wpsf',
				'event' => $sEvent,
				'category' => $nCategory,
				'message' => $sMessage
			);
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
		 * Checks the $aData contains valid key values as laid out in $inaChecks
		 *
		 * @param array $aData
		 * @param array $inaChecks
		 * @return boolean
		 */
		protected function validateParameters( $aData, $inaChecks ) {

			if ( !is_array( $aData ) ) {
				return false;
			}

			foreach( $inaChecks as $sCheck ) {
				if ( !array_key_exists( $sCheck, $aData ) || empty( $aData[ $sCheck ] ) ) {
					return false;
				}
			}
			return true;
		}

		/**
		 * @param $sStatKey
		 */
		protected function doStatIncrement( $sStatKey ) {
			$this->getFeatureOptions()->doStatIncrement( $sStatKey );
		}

		/**
		 * @return ICWP_WPSF_FeatureHandler_Base
		 */
		protected function getFeatureOptions() {
			return $this->oFeatureOptions;
		}

		/**
		 * Provides the basic HTML template for printing a WordPress Admin Notices
		 *
		 * @param $sNotice - The message to be displayed.
		 * @param $sMessageClass - either error or updated
		 * @param $bPrint - if true, will echo. false will return the string
		 *
		 * @return boolean|string
		 */
		protected function getAdminNoticeHtml( $sNotice = '', $sMessageClass = 'updated', $bPrint = false ) {
			$sWrapper = '<div class="%s icwp-admin-notice">%s</div>';
			$sFullNotice = sprintf( $sWrapper, $sMessageClass, $sNotice );
			if ( $bPrint ) {
				echo $sFullNotice;
				return true;
			} else {
				return $sFullNotice;
			}
		}

		/**
		 * @return bool|int
		 */
		protected function ip() {
			if ( empty( self::$nRequestIp ) ) {
				self::$nRequestIp = $this->loadDataProcessor()->getVisitorIpAddress( false );
			}
			return self::$nRequestIp;
		}

		/**
		 * @return int
		 */
		protected function time() {
			if ( !isset( self::$nRequestTimestamp ) ) {
				self::$nRequestTimestamp = $this->loadDataProcessor()->GetRequestTime();
			}
			return self::$nRequestTimestamp;
		}
	}

endif;

if ( !class_exists('ICWP_WPSF_Processor_Base') ):
	abstract class ICWP_WPSF_Processor_Base extends ICWP_WPSF_BaseProcessor_V3 { }
endif;