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
			$this->getCon()->fireEvent(
				'user_hard_suspended',
				[
					'user_id' => $nUserId,
					'admin'   => $sAdminUser,
				]
			);
		}
		else if ( !$bAdd && $bIdSuspended ) {
			$oMeta->hard_suspended_at = 0;
			unset( $aIds[ $nUserId ] );
			$this->getCon()->fireEvent(
				'user_hard_unsuspended',
				[
					'user_id' => $nUserId,
					'admin'   => $sAdminUser,
				]
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
	 * @return Shield\Modules\UserManagement\Options
	 */
	protected function loadOptions() {
		return new Shield\Modules\UserManagement\Options();
	}

	/**
	 * @return Shield\Modules\UserManagement\Strings
	 */
	protected function loadStrings() {
		return new Shield\Modules\UserManagement\Strings();
	}
}