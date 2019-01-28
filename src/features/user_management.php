<?php

use FernleafSystems\Wordpress\Plugin\Shield;

class ICWP_WPSF_FeatureHandler_UserManagement extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( $this->loadRequest()->request( 'exec' ) ) {

				case 'render_table_sessions':
					$aAjaxResponse = $this->ajaxExec_BuildTableTraffic();
					break;

				case 'session_delete':
					$aAjaxResponse = $this->ajaxExec_SessionDelete();
					break;

				case 'bulk_action':
					$aAjaxResponse = $this->ajaxExec_BulkItemAction();
					break;

				default:
					break;
			}
		}
		return parent::handleAuthAjax( $aAjaxResponse );
	}

	/**
	 * @return array
	 */
	private function ajaxExec_BulkItemAction() {
		$oReq = $this->loadRequest();
		$oProcessor = $this->getSessionsProcessor();

		$bSuccess = false;

		$aIds = $oReq->post( 'ids' );
		if ( empty( $aIds ) || !is_array( $aIds ) ) {
			$bSuccess = false;
			$sMessage = _wpsf__( 'No items selected.' );
		}
		else if ( !in_array( $oReq->post( 'bulk_action' ), [ 'delete' ] ) ) {
			$sMessage = _wpsf__( 'Not a supported action.' );
		}
		else {
			$nYourId = $oProcessor->getCurrentSession()->id;
			$bIncludesYourSession = in_array( $nYourId, $aIds );

			if ( $bIncludesYourSession && ( count( $aIds ) == 1 ) ) {
				$sMessage = _wpsf__( 'Please logout if you want to delete your own session.' );
			}
			else {
				$bSuccess = true;

				/** @var Shield\Databases\Session\Delete $oDel */
				$oDel = $oProcessor->getDbHandler()->getQueryDeleter();
				foreach ( $aIds as $nId ) {
					if ( is_numeric( $nId ) && ( $nId != $nYourId ) ) {
						$oDel->deleteById( $nId );
					}
				}
				$sMessage = _wpsf__( 'Selected items were deleted.' );
				if ( $bIncludesYourSession ) {
					$sMessage .= ' *'._wpsf__( 'Your session was retained' );
				}
			}

		}

		return array(
			'success' => $bSuccess,
			'message' => $sMessage,
		);
	}

	/**
	 * @return array
	 */
	private function ajaxExec_SessionDelete() {
		$oReq = $this->loadRequest();
		$oProcessor = $this->getSessionsProcessor();

		$bSuccess = false;
		$nId = $oReq->post( 'rid', -1 );
		if ( !is_numeric( $nId ) || $nId < 0 ) {
			$sMessage = _wpsf__( 'Invalid session selected' );
		}
		else if ( $this->getSession()->id === $nId ) {
			$sMessage = _wpsf__( 'Please logout if you want to delete your own session.' );
		}
		else if ( $oProcessor->getDbHandler()->getQueryDeleter()->deleteById( $nId ) ) {
			$sMessage = _wpsf__( 'User session deleted' );
			$bSuccess = true;
		}
		else {
			$sMessage = _wpsf__( "User session wasn't deleted" );
		}

		return array(
			'success' => $bSuccess,
			'message' => $sMessage,
		);
	}

	private function ajaxExec_BuildTableTraffic() {
		/** @var ICWP_WPSF_Processor_UserManagement $oPro */
		$oPro = $this->getProcessor();

		// first clean out the expired sessions before display
		$oPro->getProcessorSessions()->cleanExpiredSessions();

		$oTableBuilder = ( new Shield\Tables\Build\Sessions() )
			->setMod( $this )
			->setDbHandler( $this->getSessionsProcessor()->getDbHandler() );

		return array(
			'success' => true,
			'html'    => $oTableBuilder->buildTable()
		);
	}

	/**
	 * Should have no default email. If no email is set, no notification is sent.
	 * @return string
	 */
	public function getAdminLoginNotificationEmail() {
		return $this->getOpt( 'enable_admin_login_email_notification', '' );
	}

	/**
	 * @return int
	 */
	public function getIdleTimeoutInterval() {
		return $this->getOpt( 'session_idle_timeout_interval' )*HOUR_IN_SECONDS;
	}

	/**
	 * @return int
	 */
	public function getSessionTimeoutInterval() {
		return $this->getOpt( 'session_timeout_interval' )*DAY_IN_SECONDS;
	}

	/**
	 * @return bool
	 */
	public function hasSessionIdleTimeout() {
		return $this->isModuleEnabled() && ( $this->getIdleTimeoutInterval() > 0 );
	}

	/**
	 * @return bool
	 */
	public function hasSessionTimeoutInterval() {
		return $this->isModuleEnabled() && ( $this->getSessionTimeoutInterval() > 0 );
	}

	protected function doPrePluginOptionsSave() {
		if ( $this->getIdleTimeoutInterval() > $this->getSessionTimeoutInterval() ) {
			$this->setOpt( 'session_idle_timeout_interval', $this->getOpt( 'session_timeout_interval' )*24 );
		}
	}

	protected function doExtraSubmitProcessing() {
		$sAdminEmail = $this->getOpt( 'enable_admin_login_email_notification' );
		if ( !$this->loadDP()->validEmail( $sAdminEmail ) ) {
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
	public function isUserSessionsManagementEnabled() {
		try {
			return $this->isOpt( 'enable_user_management', 'Y' )
				   && $this->getSessionsProcessor()->getDbHandler()->isReady();
		}
		catch ( \Exception $oE ) {
			return false;
		}
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		return $this->loadDP()->mergeArraysRecursive(
			parent::getDisplayStrings(),
			array(
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
			$nStartedAt = $this->loadRequest()->ts();
			$this->setOpt( 'autoadd_sessions_started_at', $nStartedAt );
		}
		return ( $this->loadRequest()->ts() - $nStartedAt ) < 20;
	}

	/**
	 * @return bool
	 */
	public function isLockToIp() {
		return $this->isOpt( 'session_lock_location', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isSendAdminEmailLoginNotification() {
		return $this->loadDP()->validEmail( $this->getAdminLoginNotificationEmail() );
	}

	/**
	 * @return bool
	 */
	public function isSendUserEmailLoginNotification() {
		return $this->isPremium() && $this->isOpt( 'enable_user_login_email_notification', 'Y' );
	}

	/**
	 * @return int days
	 */
	public function getPassExpireDays() {
		return max( 0, (int)$this->getOpt( 'pass_expire' ) );
	}

	/**
	 * @return int seconds
	 */
	public function getPassExpireTimeout() {
		return $this->isPremium() ? $this->getPassExpireDays()*DAY_IN_SECONDS : 0;
	}

	/**
	 * @return int
	 */
	public function getPassMinLength() {
		return $this->isPremium() ? (int)$this->getOpt( 'pass_min_length' ) : 0;
	}

	/**
	 * @return int
	 */
	public function getPassMinStrength() {
		return $this->isPremium() ? (int)$this->getOpt( 'pass_min_strength' ) : 0;
	}

	/**
	 * @param int $nStrength
	 * @return int
	 */
	public function getPassStrengthName( $nStrength ) {
		$aMap = array(
			_wpsf__( 'Very Weak' ),
			_wpsf__( 'Weak' ),
			_wpsf__( 'Medium' ),
			_wpsf__( 'Strong' ),
			_wpsf__( 'Very Strong' ),
		);
		return $aMap[ max( 0, min( 4, $nStrength ) ) ];
	}

	/**
	 * @return bool
	 */
	public function isPasswordPoliciesEnabled() {
		return $this->isOpt( 'enable_password_policies', 'Y' )
			   && $this->getOptionsVo()->isOptReqsMet( 'enable_password_policies' );
	}

	/**
	 * @return bool
	 */
	public function isPassForceUpdateExisting() {
		return $this->isOpt( 'pass_force_existing', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isPassPreventPwned() {
		return $this->isOpt( 'pass_prevent_pwned', 'Y' );
	}

	/**
	 * @param array $aAllNotices
	 * @return array
	 */
	public function addInsightsNoticeData( $aAllNotices ) {
		$oWpUsers = $this->loadWpUsers();

		$aNotices = array(
			'title'    => _wpsf__( 'Users' ),
			'messages' => array()
		);

		{ //admin user
			$oAdmin = $oWpUsers->getUserByUsername( 'admin' );
			if ( !empty( $oAdmin ) && user_can( $oAdmin, 'manage_options' ) ) {
				$aNotices[ 'messages' ][ 'admin' ] = array(
					'title'   => 'Admin User',
					'message' => sprintf( _wpsf__( "Default 'admin' user still available." ) ),
					'href'    => '',
					'rec'     => _wpsf__( "Default 'admin' user should be disabled or removed." )
				);
			}
		}

		{//password policies
			if ( !$this->isPasswordPoliciesEnabled() ) {
				$aNotices[ 'messages' ][ 'password' ] = array(
					'title'   => 'Password Policies',
					'message' => _wpsf__( "Strong password policies are not enforced." ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_passwords' ),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Options' ) ),
					'rec'     => _wpsf__( 'Password policies should be turned-on.' )
				);
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );

		$aAllNotices[ 'users' ] = $aNotices;
		return $aAllNotices;
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		$aThis = array(
			'strings'      => array(
				'title' => _wpsf__( 'User Management' ),
				'sub'   => _wpsf__( 'Sessions Control & Password Policies' ),
			),
			'key_opts'     => array(),
			'href_options' => $this->getUrl_AdminPage()
		);

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$bHasIdle = $this->hasSessionIdleTimeout();
			$aThis[ 'key_opts' ][ 'idle' ] = array(
				'name'    => _wpsf__( 'Idle Users' ),
				'enabled' => $bHasIdle,
				'summary' => $bHasIdle ?
					sprintf( _wpsf__( 'Idle sessions are terminated after %s hours' ), $this->getOpt( 'session_idle_timeout_interval' ) )
					: _wpsf__( 'Idle sessions wont be terminated' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( 'session_idle_timeout_interval' ),
			);

			$bLocked = $this->isLockToIp();
			$aThis[ 'key_opts' ][ 'lock' ] = array(
				'name'    => _wpsf__( 'Lock To IP' ),
				'enabled' => $bLocked,
				'summary' => $bLocked ?
					_wpsf__( 'Sessions are locked to IP address' )
					: _wpsf__( "Sessions aren't locked to IP address" ),
				'weight'  => 1,
				'href'    => $this->getUrl_DirectLinkToOption( 'session_lock_location' ),
			);

			$bPolicies = $this->isPasswordPoliciesEnabled();

			$bPwned = $bPolicies && $this->isPassPreventPwned();
			$aThis[ 'key_opts' ][ 'pwned' ] = array(
				'name'    => _wpsf__( 'Pwned Passwords' ),
				'enabled' => $bPwned,
				'summary' => $bPwned ?
					_wpsf__( 'Pwned passwords are blocked on this site' )
					: _wpsf__( 'Pwned passwords are allowed on this site' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( 'pass_prevent_pwned' ),
			);

			$bIndepthPolices = $bPolicies && $this->isPremium();
			$aThis[ 'key_opts' ][ 'policies' ] = array(
				'name'    => _wpsf__( 'Password Policies' ),
				'enabled' => $bIndepthPolices,
				'summary' => $bIndepthPolices ?
					_wpsf__( 'Several password policies are active' )
					: _wpsf__( 'Limited or no password polices are active' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_passwords' ),
			);
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_user_accounts_management' :
				$sTitle = sprintf( _wpsf__( 'Enable Module: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'User Management offers real user sessions, finer control over user session time-out, and ensures users have logged-in in a correct manner.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'User Management' ) ) )
				);
				$sTitleShort = sprintf( _wpsf__( '%s/%s Module' ), _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_passwords' :
				$sTitle = _wpsf__( 'Password Policies' );
				$sTitleShort = _wpsf__( 'Password Policies' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Have full control over passwords used by users on the site.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Use of this feature is highly recommend.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Requirements' ), sprintf( 'WordPress v%s+', '4.4.0' ) ),
				);
				break;

			case 'section_admin_login_notification' :
				$sTitle = _wpsf__( 'Admin Login Notification' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'So you can be made aware of when a WordPress administrator has logged into your site when you are not expecting it.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Use of this feature is highly recommend.' ) )
				);
				$sTitleShort = _wpsf__( 'Notifications' );
				break;

			case 'section_multifactor_authentication' :
				$sTitle = _wpsf__( 'Multi-Factor User Authentication' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Verifies the identity of users who log in to your site - i.e. they are who they say they are.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Use of this feature is highly recommend.' ).' '._wpsf__( 'However, if your host blocks email sending you may lock yourself out.' ) )
				);
				$sTitleShort = _wpsf__( 'Multi-Factor Authentication' );
				break;

			case 'section_user_session_management' :
				$sTitle = _wpsf__( 'User Session Management' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Allows you to better control user sessions on your site and expire idle sessions and prevent account sharing.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Use of this feature is highly recommend.' ) )
				);
				$sTitleShort = _wpsf__( 'Session Options' );
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
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

			case 'enable_user_login_email_notification' :
				$sName = _wpsf__( 'User Login Notification Email' );
				$sSummary = _wpsf__( 'Send Email Notification To Each User Upon Successful Login' );
				$sDescription = _wpsf__( 'A notification is sent to each user when a successful login occurs for their account.' );
				break;

			case 'session_timeout_interval' :
				$sName = _wpsf__( 'Session Timeout' );
				$sSummary = _wpsf__( 'Specify How Many Days After Login To Automatically Force Re-Login' );
				$sDescription = _wpsf__( 'WordPress default is 2 days, or 14 days if you check the "Remember Me" box.' )
								.'<br />'.sprintf( _wpsf__( 'This cannot be less than %s.' ), '"<strong>1</strong>"' )
								.'<br />'.sprintf( '%s: %s', _wpsf__( 'Default' ), '"<strong>'.$this->getOptionsVo()
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
				$sSummary = _wpsf__( 'Prevent Use Of "Pwned" Passwords' );
				$sDescription = _wpsf__( 'Prevents users from using any passwords found on the public available list of "pwned" passwords.' );
				break;

			case 'pass_min_length' :
				$sName = _wpsf__( 'Minimum Length' );
				$sSummary = _wpsf__( 'Minimum Password Length' );
				$sDescription = _wpsf__( 'All passwords that a user sets must be at least this many characters in length.' )
								.'<br/>'._wpsf__( 'Set to Zero(0) to disable.' );
				break;

			case 'pass_min_strength' :
				$sName = _wpsf__( 'Minimum Strength' );
				$sSummary = _wpsf__( 'Minimum Password Strength' );
				$sDescription = _wpsf__( 'All passwords that a user sets must meet this minimum strength.' );
				break;

			case 'pass_force_existing' :
				$sName = _wpsf__( 'Apply To Existing Users' );
				$sSummary = _wpsf__( 'Apply Password Policies To Existing Users and Their Passwords' );
				$sDescription = _wpsf__( "Forces existing users to update their passwords if they don't meet requirements, after they next login." )
								.'<br/>'._wpsf__( 'Note: You may want to warn users prior to enabling this option.' );
				break;

			case 'pass_expire' :
				$sName = _wpsf__( 'Password Expiration' );
				$sSummary = _wpsf__( 'Passwords Expire After This Many Days' );
				$sDescription = _wpsf__( 'Users will be forced to reset their passwords after the number of days specified.' )
								.'<br/>'._wpsf__( 'Set to Zero(0) to disable.' );
				break;

			default:
				throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}

	/**
	 * @deprecated
	 * @return int
	 */
	public function getSessionIdleTimeoutInterval() {
		return $this->getIdleTimeoutInterval();
	}
}