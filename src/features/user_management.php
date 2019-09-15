<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_UserManagement extends \ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * Should have no default email. If no email is set, no notification is sent.
	 * @return string[]
	 */
	public function getAdminLoginNotificationEmails() {
		$aEmails = [];

		$sEmails = $this->getOpt( 'enable_admin_login_email_notification', '' );
		if ( !empty( $sEmails ) ) {
			$aEmails = array_values( array_filter(
				array_map(
					function ( $sEmail ) {
						return trim( strtolower( $sEmail ) );
					},
					explode( ',', $sEmails )
				),
				function ( $sEmail ) {
					return Services::Data()->validEmail( $sEmail );
				}
			) );
			if ( !$this->isPremium() && !empty( $aEmails ) ) {
				$aEmails = array_slice( $aEmails, 0, 1 );
			}
		}

		return $aEmails;
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
		$this->setOpt( 'enable_admin_login_email_notification', implode( ', ', $this->getAdminLoginNotificationEmails() ) );

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
				   && $this->getDbHandler_Sessions()->isReady();
		}
		catch ( \Exception $oE ) {
			return false;
		}
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
			   && $this->getOptions()->isOptReqsMet( 'enable_password_policies' );
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
					'audit' => [
						'user_id' => $nUserId,
						'admin'   => $sAdminUser,
					]
				]
			);
		}
		else if ( !$bAdd && $bIdSuspended ) {
			$oMeta->hard_suspended_at = 0;
			unset( $aIds[ $nUserId ] );
			$this->getCon()->fireEvent(
				'user_hard_unsuspended',
				[
					'audit' => [
						'user_id' => $nUserId,
						'admin'   => $sAdminUser,
					]
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
					'title'   => __( 'Password Policies', 'wp-simple-firewall' ),
					'message' => __( "Strong password policies are not enforced.", 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_passwords' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
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
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'UserManagement';
	}

	/**
	 * Should have no default email. If no email is set, no notification is sent.
	 * @return string
	 * @deprecated 8.1
	 */
	public function getAdminLoginNotificationEmail() {
		return $this->getOpt( 'enable_admin_login_email_notification', '' );
	}
}