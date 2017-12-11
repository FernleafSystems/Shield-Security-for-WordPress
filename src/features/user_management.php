<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_UserManagement', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_UserManagement extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return string
	 */
	protected function getContentCustomActions() {
		if ( $this->canDisplayOptionsForm() ) {
			return $this->renderUserSessions();
		}
		return parent::getContentCustomActions();
	}

	protected function renderUserSessions() {

		$aActiveSessions = $this->getActiveSessionsData();

		$oWp = $this->loadWp();
		$sTimeFormat = $oWp->getTimeFormat();
		$sDateFormat = $oWp->getDateFormat();
		foreach ( $aActiveSessions as &$aSession ) {
			$aSession[ 'logged_in_at' ] = $oWp->getTimeStringForDisplay( $aSession[ 'logged_in_at' ] );
			$aSession[ 'last_activity_at' ] = $oWp->getTimeStringForDisplay( $aSession[ 'last_activity_at' ] );
		}

		$oTable = $this->getTableRendererForSessions()
					   ->setItemEntries( $aActiveSessions )
					   ->setPerPage( 5 )
					   ->prepare_items();
		ob_start();
		$oTable->display();
		$sUserSessionsTable = ob_get_clean();

		$aData = array(
			'strings'            => $this->getDisplayStrings(),
			'time_now'           => sprintf( _wpsf__( 'now: %s' ), date_i18n( $sTimeFormat.' '.$sDateFormat, $this->loadDP()->time() ) ),
			'sUserSessionsTable' => $sUserSessionsTable
		);
		return $this->renderTemplate( 'snippets/module-user_management-sessions', $aData );
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
	 * @return array[]
	 */
	protected function getActiveSessionsData() {
		/** @var ICWP_WPSF_Processor_UserManagement $oProcessor */
		$oProcessor = $this->getProcessor();
		return $this->getIsMainFeatureEnabled() ? $oProcessor->getActiveUserSessionRecords() : array();
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
		return $this->getOptIs( 'enable_user_management', 'Y' );
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		return array(
			'actions_title'   => _wpsf__( 'User Sessions' ),
			'actions_summary' => _wpsf__( 'Review user sessions' ),

			'um_current_user_settings'          => _wpsf__( 'Current User Sessions' ),
			'um_username'                       => _wpsf__( 'Username' ),
			'um_logged_in_at'                   => _wpsf__( 'Logged In At' ),
			'um_last_activity_at'               => _wpsf__( 'Last Activity At' ),
			'um_last_activity_uri'              => _wpsf__( 'Last Activity URI' ),
			'um_login_ip'                       => _wpsf__( 'Login IP' ),
			'um_login_attempts'                 => _wpsf__( 'Login Attempts' ),
			'um_need_to_enable_user_management' => _wpsf__( 'You need to enable the User Management feature to view and manage user sessions.' ),
		);
	}

	/**
	 * @return string
	 */
	public function getUserSessionsTableName() {
		return $this->prefix( $this->getDefinition( 'user_sessions_table_name' ), '_' );
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
				$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'User Management offers real user sessions, finer control over user session time-out, and ensures users have logged-in in a correct manner.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'User Management' ) ) )
				);
				$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_bypass_user_accounts_management' :
				$sTitle = _wpsf__( 'By-Pass User Accounts Management' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Compatibility with XML-RPC services such as the WordPress iPhone and Android Apps.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Keep this turned off unless you know you need it.' ) )
				);
				$sTitleShort = _wpsf__( 'By-Pass' );
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

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}