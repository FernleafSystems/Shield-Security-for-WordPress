<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_UserManagement extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	use Shield\AuditTrail\Auditor;

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( Services::Request()->request( 'exec' ) ) {

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
		$oReq = Services::Request();
		$oProcessor = $this->getSessionsProcessor();

		$bSuccess = false;

		$aIds = $oReq->post( 'ids' );
		if ( empty( $aIds ) || !is_array( $aIds ) ) {
			$bSuccess = false;
			$sMessage = __( 'No items selected.', 'wp-simple-firewall' );
		}
		else if ( !in_array( $oReq->post( 'bulk_action' ), [ 'delete' ] ) ) {
			$sMessage = __( 'Not a supported action.', 'wp-simple-firewall' );
		}
		else {
			$nYourId = $oProcessor->getCurrentSession()->id;
			$bIncludesYourSession = in_array( $nYourId, $aIds );

			if ( $bIncludesYourSession && ( count( $aIds ) == 1 ) ) {
				$sMessage = __( 'Please logout if you want to delete your own session.', 'wp-simple-firewall' );
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
				$sMessage = __( 'Selected items were deleted.', 'wp-simple-firewall' );
				if ( $bIncludesYourSession ) {
					$sMessage .= ' *'.__( 'Your session was retained', 'wp-simple-firewall' );
				}
			}
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_SessionDelete() {
		$oReq = Services::Request();
		$oProcessor = $this->getSessionsProcessor();

		$bSuccess = false;
		$nId = $oReq->post( 'rid', -1 );
		if ( !is_numeric( $nId ) || $nId < 0 ) {
			$sMessage = __( 'Invalid session selected', 'wp-simple-firewall' );
		}
		else if ( $this->getSession()->id === $nId ) {
			$sMessage = __( 'Please logout if you want to delete your own session.', 'wp-simple-firewall' );
		}
		else if ( $oProcessor->getDbHandler()->getQueryDeleter()->deleteById( $nId ) ) {
			$sMessage = __( 'User session deleted', 'wp-simple-firewall' );
			$bSuccess = true;
		}
		else {
			$sMessage = __( "User session wasn't deleted", 'wp-simple-firewall' );
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
	}

	private function ajaxExec_BuildTableTraffic() {
		/** @var ICWP_WPSF_Processor_UserManagement $oPro */
		$oPro = $this->getProcessor();

		// first clean out the expired sessions before display
		$oPro->getProcessorSessions()->cleanExpiredSessions();

		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oSecAdminMod */
		$oSecAdminMod = $this->getCon()->getModule( 'admin_access_restriction' );

		$oTableBuilder = ( new Shield\Tables\Build\Sessions() )
			->setMod( $this )
			->setDbHandler( $this->getSessionsProcessor()->getDbHandler() )
			->setSecAdminUsers( $oSecAdminMod->getSecurityAdminUsers() );

		return [
			'success' => true,
			'html'    => $oTableBuilder->buildTable()
		];
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
	public function getMaxSessionTime() {
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
	public function hasMaxSessionTimeout() {
		return $this->isModuleEnabled() && ( $this->getMaxSessionTime() > 0 );
	}

	protected function doExtraSubmitProcessing() {
		if ( !Services::Data()->validEmail( $this->getAdminLoginNotificationEmail() ) ) {
			$this->getOptionsVo()->resetOptToDefault( 'enable_admin_login_email_notification' );
		}

		if ( $this->getIdleTimeoutInterval() > $this->getMaxSessionTime() ) {
			$this->setOpt( 'session_idle_timeout_interval', $this->getOpt( 'session_timeout_interval' )*24 );
		}

		$this->setOpt( 'auto_idle_roles',
			array_unique( array_filter( array_map(
				function ( $sRole ) {
					return preg_replace( '#[^\sa-z0-9_-]#i', '', trim( strtolower( $sRole ) ) );
				},
				$this->getSuspendAutoIdleUserRoles()
			) ) )
		);
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
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getDisplayStrings(),
			[
				'um_current_user_settings'          => __( 'Current User Sessions', 'wp-simple-firewall' ),
				'um_username'                       => __( 'Username', 'wp-simple-firewall' ),
				'um_logged_in_at'                   => __( 'Logged In At', 'wp-simple-firewall' ),
				'um_last_activity_at'               => __( 'Last Activity At', 'wp-simple-firewall' ),
				'um_last_activity_uri'              => __( 'Last Activity URI', 'wp-simple-firewall' ),
				'um_login_ip'                       => __( 'Login IP', 'wp-simple-firewall' ),
				'um_need_to_enable_user_management' => __( 'You need to enable the User Management feature to view and manage user sessions.', 'wp-simple-firewall' ),
			]
		);
	}

	/**
	 * @return bool
	 */
	public function isAutoAddSessions() {
		$nStartedAt = $this->getOpt( 'autoadd_sessions_started_at', 0 );
		if ( $nStartedAt < 1 ) {
			$nStartedAt = Services::Request()->ts();
			$this->setOpt( 'autoadd_sessions_started_at', $nStartedAt );
		}
		return ( Services::Request()->ts() - $nStartedAt ) < 20;
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
		return Services::Data()->validEmail( $this->getAdminLoginNotificationEmail() );
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
		return ( $this->isPasswordPoliciesEnabled() && $this->isPremium() ) ? (int)$this->getOpt( 'pass_expire' ) : 0;
	}

	/**
	 * @return int seconds
	 */
	public function getPassExpireTimeout() {
		return $this->getPassExpireDays()*DAY_IN_SECONDS;
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
		$aMap = [
			__( 'Very Weak', 'wp-simple-firewall' ),
			__( 'Weak', 'wp-simple-firewall' ),
			__( 'Medium', 'wp-simple-firewall' ),
			__( 'Strong', 'wp-simple-firewall' ),
			__( 'Very Strong', 'wp-simple-firewall' ),
		];
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
	public function isPassExpirationEnabled() {
		return $this->isPasswordPoliciesEnabled() && ( $this->getPassExpireTimeout() > 0 );
	}

	/**
	 * @return bool
	 */
	public function isPassPreventPwned() {
		return $this->isOpt( 'pass_prevent_pwned', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isSuspendEnabled() {
		return $this->isPremium() &&
			   ( $this->isSuspendManualEnabled()
				 || $this->isSuspendAutoIdleEnabled()
				 || $this->isSuspendAutoPasswordEnabled()
			   );
	}

	/**
	 * @return bool
	 */
	public function isSuspendManualEnabled() {
		return $this->isOpt( 'manual_suspend', 'Y' );
	}

	/**
	 * @return int
	 */
	public function getSuspendAutoIdleTime() {
		return $this->getOpt( 'auto_idle_days', 0 )*DAY_IN_SECONDS;
	}

	/**
	 * @return array
	 */
	public function getSuspendAutoIdleUserRoles() {
		$aRoles = $this->getOpt( 'auto_idle_roles', [] );
		return is_array( $aRoles ) ? $aRoles : [];
	}

	/**
	 * @return bool
	 */
	public function isSuspendAutoIdleEnabled() {
		return ( $this->getSuspendAutoIdleTime() > 0 )
			   && ( count( $this->getSuspendAutoIdleUserRoles() ) > 0 );
	}

	/**
	 * @return bool
	 */
	public function isSuspendAutoPasswordEnabled() {
		return $this->isOpt( 'auto_password', 'Y' )
			   && $this->isPasswordPoliciesEnabled() && $this->getPassExpireTimeout();
	}

	/**
	 * @param int  $nUserId
	 * @param bool $bAdd - set true to add, false to remove
	 * @return $this
	 */
	public function addRemoveHardSuspendUserId( $nUserId, $bAdd = true ) {
		$sAdminUser = Services::WpUsers()->getCurrentWpUsername();

		$aIds = $this->getOpt( 'hard_suspended_userids', [] );
		if ( !is_array( $aIds ) ) {
			$aIds = [];
		}

		$bIdSuspended = isset( $aIds[ $nUserId ] );
		$oMeta = $this->getCon()->getUserMeta( Services::WpUsers()->getUserById( $nUserId ) );

		if ( $bAdd && !$bIdSuspended ) {
			$oMeta->hard_suspended_at = Services::Request()->ts();
			$aIds[ $nUserId ] = $oMeta->hard_suspended_at;
			$this->createNewAudit(
				'wpsf',
				sprintf( __( 'User ID %s suspended by admin (%s)', 'wp-simple-firewall' ), $nUserId, $sAdminUser ),
				1, 'suspend_user'
			);
		}
		else if ( !$bAdd && $bIdSuspended ) {
			$oMeta->hard_suspended_at = 0;
			unset( $aIds[ $nUserId ] );
			$this->createNewAudit(
				'wpsf',
				sprintf( __( 'User ID %s unsuspended by admin (%s)', 'wp-simple-firewall' ), $nUserId, $sAdminUser ),
				1, 'unsuspend_user'
			);
		}

		return $this->setOpt( 'hard_suspended_userids', $aIds );
	}

	/**
	 * @return array
	 */
	public function getSuspendHardUserIds() {
		$aIds = $this->getOpt( 'hard_suspended_userids', [] );
		return is_array( $aIds ) ? array_filter( $aIds, 'is_int' ) : [];
	}

	/**
	 * @param array $aAllNotices
	 * @return array
	 */
	public function addInsightsNoticeData( $aAllNotices ) {

		$aNotices = [
			'title'    => __( 'Users', 'wp-simple-firewall' ),
			'messages' => []
		];

		{ //admin user
			$oAdmin = Services::WpUsers()->getUserByUsername( 'admin' );
			if ( !empty( $oAdmin ) && user_can( $oAdmin, 'manage_options' ) ) {
				$aNotices[ 'messages' ][ 'admin' ] = [
					'title'   => 'Admin User',
					'message' => sprintf( __( "Default 'admin' user still available.", 'wp-simple-firewall' ) ),
					'href'    => '',
					'rec'     => __( "Default 'admin' user should be disabled or removed.", 'wp-simple-firewall' )
				];
			}
		}

		{//password policies
			if ( !$this->isPasswordPoliciesEnabled() ) {
				$aNotices[ 'messages' ][ 'password' ] = [
					'title'   => 'Password Policies',
					'message' => __( "Strong password policies are not enforced.", 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_passwords' ),
					'action'  => sprintf( 'Go To %s', __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Password policies should be turned-on.', 'wp-simple-firewall' )
				];
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
		$aThis = [
			'strings'      => [
				'title' => __( 'User Management', 'wp-simple-firewall' ),
				'sub'   => __( 'Sessions Control & Password Policies', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $this->getUrl_AdminPage()
		];

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$bHasIdle = $this->hasSessionIdleTimeout();
			$aThis[ 'key_opts' ][ 'idle' ] = [
				'name'    => __( 'Idle Users', 'wp-simple-firewall' ),
				'enabled' => $bHasIdle,
				'summary' => $bHasIdle ?
					sprintf( __( 'Idle sessions are terminated after %s hours', 'wp-simple-firewall' ), $this->getOpt( 'session_idle_timeout_interval' ) )
					: __( 'Idle sessions wont be terminated', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( 'session_idle_timeout_interval' ),
			];

			$bLocked = $this->isLockToIp();
			$aThis[ 'key_opts' ][ 'lock' ] = [
				'name'    => __( 'Lock To IP', 'wp-simple-firewall' ),
				'enabled' => $bLocked,
				'summary' => $bLocked ?
					__( 'Sessions are locked to IP address', 'wp-simple-firewall' )
					: __( "Sessions aren't locked to IP address", 'wp-simple-firewall' ),
				'weight'  => 1,
				'href'    => $this->getUrl_DirectLinkToOption( 'session_lock_location' ),
			];

			$bPolicies = $this->isPasswordPoliciesEnabled();

			$bPwned = $bPolicies && $this->isPassPreventPwned();
			$aThis[ 'key_opts' ][ 'pwned' ] = [
				'name'    => __( 'Pwned Passwords', 'wp-simple-firewall' ),
				'enabled' => $bPwned,
				'summary' => $bPwned ?
					__( 'Pwned passwords are blocked on this site', 'wp-simple-firewall' )
					: __( 'Pwned passwords are allowed on this site', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( 'pass_prevent_pwned' ),
			];

			$bIndepthPolices = $bPolicies && $this->isPremium();
			$aThis[ 'key_opts' ][ 'policies' ] = [
				'name'    => __( 'Password Policies', 'wp-simple-firewall' ),
				'enabled' => $bIndepthPolices,
				'summary' => $bIndepthPolices ?
					__( 'Several password policies are active', 'wp-simple-firewall' )
					: __( 'Limited or no password polices are active', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_passwords' ),
			];
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
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'User Management offers real user sessions, finer control over user session time-out, and ensures users have logged-in in a correct manner.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'User Management', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_passwords' :
				$sTitle = __( 'Password Policies', 'wp-simple-firewall' );
				$sTitleShort = __( 'Password Policies', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Have full control over passwords used by users on the site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Requirements', 'wp-simple-firewall' ), sprintf( 'WordPress v%s+', '4.4.0' ) ),
				];
				break;

			case 'section_admin_login_notification' :
				$sTitle = __( 'Admin Login Notification', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'So you can be made aware of when a WordPress administrator has logged into your site when you are not expecting it.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Notifications', 'wp-simple-firewall' );
				break;

			case 'section_multifactor_authentication' :
				$sTitle = __( 'Multi-Factor User Authentication', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies the identity of users who log in to your site - i.e. they are who they say they are.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ).' '.__( 'However, if your host blocks email sending you may lock yourself out.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Multi-Factor Authentication', 'wp-simple-firewall' );
				break;

			case 'section_user_session_management' :
				$sTitle = __( 'User Session Management', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Allows you to better control user sessions on your site and expire idle sessions and prevent account sharing.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Session Options', 'wp-simple-firewall' );
				break;

			case 'section_suspend' :
				$sTitleShort = __( 'User Suspension', 'wp-simple-firewall' );
				$sTitle = __( 'Automatic And Manual User Suspension', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Automatically suspends accounts to prevent login by certain users.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [];
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$oOptsVo = $this->getOptionsVo();
		switch ( $aOptionsParams[ 'key' ] ) {

			case 'enable_user_management' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				break;

			case 'enable_admin_login_email_notification' :
				$sName = __( 'Admin Login Notification Email', 'wp-simple-firewall' );
				$sSummary = __( 'Send An Notification Email When Administrator Logs In', 'wp-simple-firewall' );
				$sDescription = __( 'If you would like to be notified every time an administrator user logs into this WordPress site, enter a notification email address.', 'wp-simple-firewall' )
								.'<br />'.__( 'No email address - No Notification.', 'wp-simple-firewall' );
				break;

			case 'enable_user_login_email_notification' :
				$sName = __( 'User Login Notification Email', 'wp-simple-firewall' );
				$sSummary = __( 'Send Email Notification To Each User Upon Successful Login', 'wp-simple-firewall' );
				$sDescription = __( 'A notification is sent to each user when a successful login occurs for their account.', 'wp-simple-firewall' );
				break;

			case 'session_timeout_interval' :
				$sName = __( 'Session Timeout', 'wp-simple-firewall' );
				$sSummary = __( 'Specify How Many Days After Login To Automatically Force Re-Login', 'wp-simple-firewall' );
				$sDescription = __( 'WordPress default is 2 days, or 14 days if you check the "Remember Me" box.', 'wp-simple-firewall' )
								.'<br />'.__( 'Think of this as an absolute maximum possible session length.', 'wp-simple-firewall' )
								.'<br />'.sprintf( __( 'This cannot be less than %s.', 'wp-simple-firewall' ), '<strong>1</strong>' )
								.' '.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), '<strong>'.$this->getOptionsVo()
																											   ->getOptDefault( 'session_timeout_interval' ).'</strong>' );
				break;

			case 'session_idle_timeout_interval' :
				$sName = __( 'Idle Timeout', 'wp-simple-firewall' );
				$sSummary = __( 'Specify How Many Hours After Inactivity To Automatically Logout User', 'wp-simple-firewall' );
				$sDescription = __( 'If the user is inactive for the number of hours specified, they will be forcefully logged out next time they return.', 'wp-simple-firewall' )
								.'<br />'.sprintf( __( 'Set to %s to turn off this option.', 'wp-simple-firewall' ), '"<strong>0</strong>"' );
				break;

			case 'session_lock_location' :
				$sName = __( 'Lock To Location', 'wp-simple-firewall' );
				$sSummary = __( 'Locks A User Session To IP address', 'wp-simple-firewall' );
				$sDescription = __( 'When selected, a session is restricted to the same IP address as when the user logged in.', 'wp-simple-firewall' )
								.' '.__( "If a logged-in user's IP address changes, the session will be invalidated and they'll be forced to re-login to WordPress.", 'wp-simple-firewall' );
				break;

			case 'session_username_concurrent_limit' :
				$sName = __( 'Max Simultaneous Sessions', 'wp-simple-firewall' );
				$sSummary = __( 'Limit Simultaneous Sessions For The Same Username', 'wp-simple-firewall' );
				$sDescription = __( 'The number provided here is the maximum number of simultaneous, distinct, sessions allowed for any given username.', 'wp-simple-firewall' )
								.'<br />'.__( "Zero (0) will allow unlimited simultaneous sessions.", 'wp-simple-firewall' );
				break;

			case 'enable_password_policies' :
				$sName = __( 'Enable Password Policies', 'wp-simple-firewall' );
				$sSummary = __( 'Enable The Password Policies Detailed Below', 'wp-simple-firewall' );
				$sDescription = __( 'Turn on/off all password policy settings.', 'wp-simple-firewall' );
				break;

			case 'pass_prevent_pwned' :
				$sName = __( 'Prevent Pwned Passwords', 'wp-simple-firewall' );
				$sSummary = __( 'Prevent Use Of "Pwned" Passwords', 'wp-simple-firewall' );
				$sDescription = __( 'Prevents users from using any passwords found on the public available list of "pwned" passwords.', 'wp-simple-firewall' );
				break;

			case 'pass_min_length' :
				$sName = __( 'Minimum Length', 'wp-simple-firewall' );
				$sSummary = __( 'Minimum Password Length', 'wp-simple-firewall' );
				$sDescription = __( 'All passwords that a user sets must be at least this many characters in length.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Set to Zero(0) to disable.', 'wp-simple-firewall' );
				break;

			case 'pass_min_strength' :
				$sName = __( 'Minimum Strength', 'wp-simple-firewall' );
				$sSummary = __( 'Minimum Password Strength', 'wp-simple-firewall' );
				$sDescription = __( 'All passwords that a user sets must meet this minimum strength.', 'wp-simple-firewall' );
				break;

			case 'pass_force_existing' :
				$sName = __( 'Apply To Existing Users', 'wp-simple-firewall' );
				$sSummary = __( 'Apply Password Policies To Existing Users and Their Passwords', 'wp-simple-firewall' );
				$sDescription = __( "Forces existing users to update their passwords if they don't meet requirements, after they next login.", 'wp-simple-firewall' )
								.'<br/>'.__( 'Note: You may want to warn users prior to enabling this option.', 'wp-simple-firewall' );
				break;

			case 'pass_expire' :
				$sName = __( 'Password Expiration', 'wp-simple-firewall' );
				$sSummary = __( 'Passwords Expire After This Many Days', 'wp-simple-firewall' );
				$sDescription = __( 'Users will be forced to reset their passwords after the number of days specified.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Set to Zero(0) to disable.', 'wp-simple-firewall' );
				break;

			case 'manual_suspend' :
				$sName = __( 'Allow Manual User Suspension', 'wp-simple-firewall' );
				$sSummary = __( 'Manually Suspend User Accounts To Prevent Login', 'wp-simple-firewall' );
				$sDescription = __( 'Users may be suspended by administrators to prevent future login.', 'wp-simple-firewall' );
				break;

			case 'auto_password' :
				$sName = __( 'Auto-Suspend Expired Passwords', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Suspend Users With Expired Passwords', 'wp-simple-firewall' );
				$sDescription = __( 'Automatically suspends login by users and requires password reset to unsuspend.', 'wp-simple-firewall' )
								.'<br/>'.sprintf(
									'<strong>%s</strong> - %s',
									__( 'Important', 'wp-simple-firewall' ),
									__( 'Requires password expiration policy to be set.', 'wp-simple-firewall' )
								);
				break;

			case 'auto_idle_days' :
				$sName = __( 'Auto-Suspend Idle Users', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Suspend Idle User Accounts', 'wp-simple-firewall' );
				$sDescription = __( 'Automatically suspends login for idle accounts and requires password reset to unsuspend.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Specify the number of days since last login to consider a user as idle.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Set to Zero(0) to disable.', 'wp-simple-firewall' );
				break;

			case 'auto_idle_roles' :
				$sName = __( 'Auto-Suspend Idle User Roles', 'wp-simple-firewall' );
				$sSummary = __( 'Apply Automatic Suspension To Accounts With These Roles', 'wp-simple-firewall' );
				$sDescription = __( 'Automatic suspension for idle accounts applies only to the roles you specify.', 'wp-simple-firewall' )
								.'<br/>'.sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'Take a new line for each user role.', 'wp-simple-firewall' ) )
								.'<br/>'.sprintf( '%s: %s', __( 'Available Roles', 'wp-simple-firewall' ), implode( ', ', Services::WpUsers()
																																  ->getAvailableUserRoles() ) )
								.'<br/>'.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), implode( ', ', $oOptsVo->getOptDefault( 'auto_idle_roles' ) ) );
				break;

			default:
				throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $aOptionsParams[ 'key' ] ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}