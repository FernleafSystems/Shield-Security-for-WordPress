<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_UserManagement', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_UserManagement extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return array
	 */
	protected function getContentCustomActionsData() {
		return $this->getUserSessionsData();
	}

	protected function getUserSessionsData() {
		$aActiveSessions = $this->getActiveSessionsData();

		$aFormatted = array();

		$oWp = $this->loadWp();
		$sTimeFormat = $oWp->getTimeFormat();
		$sDateFormat = $oWp->getDateFormat();
		foreach ( $aActiveSessions as $oSession ) {
			$aSession = (array)$oSession->getRowData();
			$aSession[ 'logged_in_at' ] = $oWp->getTimeStringForDisplay( $oSession->getLoggedInAt() );
			$aSession[ 'last_activity_at' ] = $oWp->getTimeStringForDisplay( $oSession->getLastActivityAt() );
			$aSession[ 'is_secadmin' ] = ( $oSession->getSecAdminAt() > 0 ) ? __( 'Yes' ) : __( 'No' );
			$aFormatted[] = $aSession;
		}

		$oTable = $this->getTableRendererForSessions()
					   ->setItemEntries( $aFormatted )
					   ->setPerPage( 5 )
					   ->prepare_items();
		ob_start();
		$oTable->display();
		$sUserSessionsTable = ob_get_clean();

		return array(
			'strings'            => $this->getDisplayStrings(),
			'time_now'           => sprintf( _wpsf__( 'now: %s' ), date_i18n( $sTimeFormat.' '.$sDateFormat, $this->loadDP()
																												  ->time() ) ),
			'sUserSessionsTable' => $sUserSessionsTable
		);
	}

	/**
	 * @return SessionsTable
	 */
	protected function getTableRendererForSessions() {
		$this->requireCommonLib( 'Components/Tables/SessionsTable.php' );
		/** @var ICWP_WPSF_Processor_UserManagement $oProc */
		$oProc = $this->loadFeatureProcessor();
//		$nCount = $oProc->countAuditEntriesForContext( $sContext );

		$oTable = new SessionsTable();
		return $oTable->setTotalRecords( 10 );
	}

	/**
	 * @return ICWP_WPSF_SessionVO[]
	 */
	protected function getActiveSessionsData() {
		return $this->getSessionsProcessor()
					->queryGetActiveSessions();
	}

	/**
	 * @return bool
	 */
	protected function isReadyToExecute() {
		return parent::isReadyToExecute() && !$this->isVisitorWhitelisted();
	}

	protected function doExtraSubmitProcessing() {
		$sAdminEmail = $this->getOpt( 'enable_admin_login_email_notification' );
		if ( !$this->loadDataProcessor()->validEmail( $sAdminEmail ) ) {
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
	 * Currently no distinction between the module and user sessions.
	 * @return bool
	 */
	public function getIsUserSessionsManagementEnabled() {
		return $this->getOptIs( 'enable_user_management', 'Y' )
			   && $this->getSessionsProcessor()->getTableExists();
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		return $this->loadDP()->mergeArraysRecursive(
			parent::getDisplayStrings(),
			array(
				'btn_actions'         => _wpsf__( 'User Sessions' ),
				'btn_actions_summary' => _wpsf__( 'Review user sessions' ),

				'um_current_user_settings'          => _wpsf__( 'Current User Sessions' ),
				'um_username'                       => _wpsf__( 'Username' ),
				'um_logged_in_at'                   => _wpsf__( 'Logged In At' ),
				'um_last_activity_at'               => _wpsf__( 'Last Activity At' ),
				'um_last_activity_uri'              => _wpsf__( 'Last Activity URI' ),
				'um_login_ip'                       => _wpsf__( 'Login IP' ),
				'um_need_to_enable_user_management' => _wpsf__( 'You need to enable the User Management feature to view and manage user sessions.' ),
			)
		);
	}

	/**
	 * @return bool
	 */
	public function isAutoAddSessions() {
		$nStartedAt = $this->getOpt( 'autoadd_sessions_started_at', 0 );
		if ( $nStartedAt < 1 ) {
			$nStartedAt = $this->loadDP()->time();
			$this->setOpt( 'autoadd_sessions_started_at', $nStartedAt );
		}
		return ( $this->loadDP()->time() - $nStartedAt ) < 20;
	}

	/**
	 * @return bool
	 */
	public function isSendEmailLoginNotification() {
		return $this->loadDP()->validEmail( $this->getOpt( 'enable_admin_login_email_notification' ) );
	}

	/**
	 * @return int
	 */
	public function getPassMinLength() {
		return (int)$this->getOpt( 'pass_min_length' );
	}

	/**
	 * @return int seconds
	 */
	public function getPassExpireTimeout() {
		$nDays = max( 0, (int)$this->getOpt( 'pass_expire' ) );
		return $nDays*DAY_IN_SECONDS;
	}

	/**
	 * @return int
	 */
	public function getPassStrengthName( $nStrength ) {
		$aMap = array(
			_wpsf__( 'Weak' ),
			_wpsf__( 'Weak' ),
			_wpsf__( 'Medium' ),
			_wpsf__( 'Strong' ),
			_wpsf__( 'Very Strong' ),
		);
		return $aMap[ max( 0, min( 4, $nStrength ) ) ];
	}

	/**
	 * @return int
	 */
	public function getPassMinStrength() {
		return (int)$this->getOpt( 'pass_min_strength' );
	}

	/**
	 * @return bool
	 */
	public function isPasswordPoliciesEnabled() {
		return $this->getOptIs( 'enable_password_policies', 'Y' )
			   && $this->getOptionsVo()->isOptReqsMet( 'enable_password_policies' );
	}

	/**
	 * @return bool
	 */
	public function isPassForceUpdateExisting() {
		return $this->getOptIs( 'pass_force_existing', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isPassPreventPwned() {
		return $this->getOptIs( 'pass_prevent_pwned', 'Y' );
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_user_accounts_management' :
				$sTitle = sprintf( _wpsf__( 'Enable Module: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'User Management offers real user sessions, finer control over user session time-out, and ensures users have logged-in in a correct manner.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'User Management' ) ) )
				);
				$sTitleShort = sprintf( _wpsf__( '%s/%s Module' ), _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_passwords' :
				$sTitle = _wpsf__( 'Password Policies' );
				$sTitleShort = _wpsf__( 'Password Policies' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Have full control over passwords used by users on the site.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Use of this feature is highly recommend.' ) ),
					sprintf( _wpsf__( 'Note - %s' ), _wpsf__( 'Requires PHP v5.4 and above.' ) )
				);
				break;

			case 'section_admin_login_notification' :
				$sTitle = _wpsf__( 'Admin Login Notification' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'So you can be made aware of when a WordPress administrator has logged into your site when you are not expecting it.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Use of this feature is highly recommend.' ) )
				);
				$sTitleShort = _wpsf__( 'Notifications' );
				break;

			case 'section_multifactor_authentication' :
				$sTitle = _wpsf__( 'Multi-Factor User Authentication' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Verifies the identity of users who log in to your site - i.e. they are who they say they are.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Use of this feature is highly recommend.' ).' '._wpsf__( 'However, if your host blocks email sending you may lock yourself out.' ) )
				);
				$sTitleShort = _wpsf__( 'Multi-Factor Authentication' );
				break;

			case 'section_user_session_management' :
				$sTitle = _wpsf__( 'User Session Management' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Allows you to better control user sessions on your site and expire idle sessions and prevent account sharing.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Use of this feature is highly recommend.' ) )
				);
				$sTitleShort = _wpsf__( 'Session Options' );
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_user_management' :
				$sName = sprintf( _wpsf__( 'Enable %s Module' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Module' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Un-Checking this option will completely disable the %s module.' ), $this->getMainFeatureName() );
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
								.'<br />'.sprintf( _wpsf__( 'This cannot be less than %s.' ), '"<strong>1</strong>"' )
								.'<br />'.sprintf( _wpsf__( 'Default: %s.' ), '"<strong>'.$this->getOptionsVo()
																							   ->getOptDefault( 'session_timeout_interval' ).'</strong>"' );
				break;

			case 'session_idle_timeout_interval' :
				$sName = _wpsf__( 'Idle Timeout' );
				$sSummary = _wpsf__( 'Specify How Many Hours After Inactivity To Automatically Logout User' );
				$sDescription = _wpsf__( 'If the user is inactive for the number of hours specified, they will be forcefully logged out next time they return.' )
								.'<br />'.sprintf( _wpsf__( 'Set to %s to turn off this option.' ), '"<strong>0</strong>"' );
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

			case 'enable_password_policies' :
				$sName = _wpsf__( 'Enable Password Policies' );
				$sSummary = _wpsf__( 'Enable The Password Policies Detailed Below' );
				$sDescription = _wpsf__( 'Turn on/off all password policy settings.' );
				break;

			case 'pass_prevent_pwned' :
				$sName = _wpsf__( 'Prevent Pwned Passwords' );
				$sSummary = _wpsf__( 'Prevent Use Of Any Pwned Passwords' );
				$sDescription = _wpsf__( 'Prevents users from using any passwords found on the public available list of "pwned" passwords.' );
				break;

			case 'pass_min_length' :
				$sName = _wpsf__( 'Minimum Length' );
				$sSummary = _wpsf__( 'Minimum Password Length' );
				$sDescription = _wpsf__( 'All passwords that a user sets must be at least this many characters in length.' );
				break;

			case 'pass_min_strength' :
				$sName = _wpsf__( 'Minimum Strength' );
				$sSummary = _wpsf__( 'Minimum Password Strength' );
				$sDescription = _wpsf__( 'All passwords that a user sets must meet this minimum strength.' );
				break;

			case 'pass_force_existing' :
				$sName = _wpsf__( 'Apply To Existing' );
				$sSummary = _wpsf__( 'Apply Password Policies To Existing Users and Their Passwords' );
				$sDescription = _wpsf__( "Forces existing users to update their passwords if they don't meet requirements, after they next login." )
								.'<br/>'._wpsf__( 'Note: You may want to warn users prior to enabling this option.' );
				break;

			case 'pass_expire' :
				$sName = _wpsf__( 'Password Expiration' );
				$sSummary = _wpsf__( 'Passwords Expire After This Many Days' );
				$sDescription = _wpsf__( 'Users will be forced to reset their passwords after the number of days specified.' )
								.' '._wpsf__( 'Set to Zero(0) to disable.' );
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}