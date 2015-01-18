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
 */

require_once( 'base.php' );

if ( !class_exists('ICWP_WPSF_FeatureHandler_UserManagement') ):

	class ICWP_WPSF_FeatureHandler_UserManagement extends ICWP_WPSF_FeatureHandler_Base {

		/**
		 * @return string
		 */
		protected function getProcessorClassName() {
			return 'ICWP_WPSF_Processor_UserManagement';
		}

		protected function doExecuteProcessor() {
			$sIp = $this->loadDataProcessor()->getVisitorIpAddress();
			$aIpWhitelist = apply_filters( $this->doPluginPrefix( 'ip_whitelist' ), array() );
			if ( is_array( $aIpWhitelist ) && ( in_array( $sIp, $aIpWhitelist )  ) ) {
				return;
			}
			parent::doExecuteProcessor();
		}

		/**
		 */
		public function doPrePluginOptionsSave() {

			$sAdminEmail = $this->getOpt( 'enable_admin_login_email_notification' );
			if ( !empty( $sAdminEmail ) && !is_email( $sAdminEmail ) ) {
				$this->getOptionsVo()->resetOptToDefault( 'enable_admin_login_email_notification' );
			}

			if ( $this->getOpt( 'session_username_concurrent_limit' ) < 0 ) {
				$this->getOptionsVo()->resetOptToDefault( 'session_username_concurrent_limit' );
			}

			if ( $this->getOpt( 'session_timeout_interval' ) < 1 ) {
				$this->getOptionsVo()->resetOptToDefault( 'session_timeout_interval' );
			}
		}

		/**
		 */
		public function displayFeatureConfigPage( ) {
			/** @var ICWP_WPSF_Processor_UserManagement $oProcessor */
			$oProcessor = $this->getProcessor();
			$aData = array(
				'aActiveSessions'		=> $this->getIsMainFeatureEnabled()? $oProcessor->getActiveUserSessionRecords() : array(),
				'aFailedSessions'		=> $this->getIsMainFeatureEnabled()? $oProcessor->getPendingOrFailedUserSessionRecordsSince() : array()
			);
			$this->display( $aData );
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {

			$sSectionSlug = $aOptionsParams['section_slug'];
			switch( $aOptionsParams['section_slug'] ) {

				case 'section_enable_plugin_feature_user_accounts_management' :
					$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
					break;

				case 'section_bypass_user_accounts_management' :
					$sTitle = _wpsf__('By-Pass User Accounts Management');
					break;

				case 'section_admin_login_notification' :
					$sTitle = _wpsf__('Admin Login Notification');
					break;

				case 'section_user_session_management' :
					$sTitle = _wpsf__('User Session Management');
					break;

				case 'section_automatic_update_email_notifications' :
					$sTitle = _wpsf__('Automatic Update Email Notifications');
					break;

				default:
					throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
			}
			$aOptionsParams['section_title'] = $sTitle;
			return $aOptionsParams;
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_Options( $aOptionsParams ) {

			$sKey = $aOptionsParams['key'];
			switch( $sKey ) {

				case 'enable_user_management' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), $this->getMainFeatureName() );
					$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), $this->getMainFeatureName() );
					$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), $this->getMainFeatureName() );
					break;

				case 'enable_xmlrpc_compatibility' :
					$sName = _wpsf__( 'XML-RPC Compatibility' );
					$sSummary = _wpsf__( 'Allow Login Through XML-RPC To By-Pass Accounts Management Rules' );
					$sDescription = _wpsf__( 'Enable this if you need XML-RPC functionality e.g. if you use the WordPress iPhone/Android App.' );
					break;

				case 'enable_admin_login_email_notification' :
					$sName = _wpsf__( 'Admin Login Notification Email' );
					$sSummary = _wpsf__( 'Send An Notification Email When Administrator Logs In' );
					$sDescription = _wpsf__( 'If you would like to be notified every time an administrator user logs into this WordPress site, enter a notification email address.' )
									.'<br />'._wpsf__( 'No email address - No Notification.' );
					break;

				case 'session_timeout_interval' :
					$sName = _wpsf__( 'Session Timeout' );
					$sSummary = _wpsf__( 'Specify How Many Days After Login To Automatically Force Re-Login' );
					$sDescription = _wpsf__( 'WordPress default is 2 days, or 14 days if you check the "Remember Me" box.' )
									.'<br />'. sprintf( _wpsf__( 'This cannot be less than %s.' ), '"<strong>1</strong>"' )
									.'<br />'. sprintf( _wpsf__( 'Default: %s.' ), '"<strong>'.$this->getOptionsVo()->getOptDefault('session_timeout_interval').'</strong>"' );
					break;

				case 'session_idle_timeout_interval' :
					$sName = _wpsf__( 'Idle Timeout' );
					$sSummary = _wpsf__( 'Specify How Many Hours After Inactivity To Automatically Logout User' );
					$sDescription = _wpsf__( 'If the user is inactive for the number of hours specified, they will be forcefully logged out next time they return.' )
									.'<br />'. sprintf( _wpsf__( 'Set to %s to turn off this option.' ), '"<strong>0</strong>"' );
					break;

				case 'session_lock_location' :
					$sName = _wpsf__( 'Lock To Location' );
					$sSummary = _wpsf__( 'Locks A User Session To IP address' );
					$sDescription = _wpsf__( 'When selected, a session is restricted to the same IP address as when the user logged in.' )
									.' '._wpsf__( "If a logged-in user's IP address changes, the session will be invalidated and they'll be forced to re-login to WordPress." );
					break;

				case 'session_username_concurrent_limit' :
					$sName = _wpsf__( 'Max Simultaneous Sessions' );
					$sSummary = _wpsf__( 'Limit Simultaneous Sessions For The Same Username' );
					$sDescription = _wpsf__( 'The number provided here is the maximum number of simultaneous, distinct, sessions allowed for any given username.' )
									.'<br />'._wpsf__( "Zero (0) will allow unlimited simultaneous sessions." );
					break;

				case 'session_check_admin_area_only' :
					$sName = _wpsf__( 'Check Admin Area Only' );
					$sSummary = _wpsf__( 'Perform Session Checking For Logged In Users Only In Admin Area' );
					$sDescription = _wpsf__( 'When selected, session timeouts will only be checked on visits to the WordPress admin area.' );
					break;

				case 'session_auto_forward_to_admin_area' :
					$sName = _wpsf__( 'Auto Redirect To Admin' );
					$sSummary = _wpsf__( 'Automatically Redirect To WP Admin When Valid Session Detected' );
					$sDescription = _wpsf__( 'When selected, users will be automatically forwarded to the WordPress admin screen when they visit wp-login.php.' )
									.'<br />'. _wpsf__( 'It removes the extra step to get to the admin screen for already-authenticated users.' );
					break;

				default:
					throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
			}

			$aOptionsParams['name'] = $sName;
			$aOptionsParams['summary'] = $sSummary;
			$aOptionsParams['description'] = $sDescription;
			return $aOptionsParams;
		}

		/**
		 * @return string
		 */
		public function getUserSessionsTableName() {
			return $this->doPluginPrefix( $this->getOpt( 'user_sessions_table_name' ), '_' );
		}

		/**
		 * @return string
		 */
		public function getUserSessionCookieName() {
			return $this->getOpt( 'user_session_cookie_name' );
		}

		/**
		 */
		protected function updateHandler() {
			parent::updateHandler();
			if ( version_compare( $this->getVersion(), '4.1.0', '<' ) ) {
				$this->setOpt( 'recreate_database_table', true );
			}
		}
	}

endif;