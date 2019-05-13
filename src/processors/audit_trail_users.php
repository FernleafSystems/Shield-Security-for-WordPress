<?php

class ICWP_WPSF_Processor_AuditTrail_Users extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
		add_action( 'wp_login', [ $this, 'auditUserLoginSuccess' ] );
		add_action( 'user_register', [ $this, 'auditNewUserRegistered' ] );
		add_action( 'delete_user', [ $this, 'auditDeleteUser' ], 30, 2 );
	}

	/**
	 * @param string $sUsername
	 */
	public function auditUserLoginSuccess( $sUsername ) {
		if ( !empty( $sUsername ) ) {
			$this->add( 'users', 'login_success', 1,
				sprintf( __( 'Attempted user login by "%s" was successful.', 'wp-simple-firewall' ), $sUsername ),
				$sUsername
			);
		}
	}

	/**
	 * @param int $nUserId
	 */
	public function auditNewUserRegistered( $nUserId ) {

		$oNewUser = empty( $nUserId ) ? null : $this->loadWpUsers()->getUserById( $nUserId );
		if ( !empty( $oNewUser ) ) {
			$this->add( 'users', 'user_registered', 1,
				__( 'New WordPress user registered.', 'wp-simple-firewall' ).' '
				.sprintf(
					__( 'New username is "%s" with email address "%s".', 'wp-simple-firewall' ),
					$oNewUser->user_login, $oNewUser->user_email
				)
			);
		}
	}

	/**
	 * @param int $nUserId
	 * @param int $nReassigned
	 */
	public function auditDeleteUser( $nUserId, $nReassigned ) {
		$oWpUsers = $this->loadWpUsers();

		$aAuditMessage = [ __( 'WordPress user deleted.', 'wp-simple-firewall' ) ];

		$oDeletedUser = empty( $nUserId ) ? null : $oWpUsers->getUserById( $nUserId );
		if ( empty( $oDeletedUser ) ) {
			$aAuditMessage[] = __( 'User is unknown as it could not be loaded.', 'wp-simple-firewall' );
		}
		else {
			$aAuditMessage[] = sprintf( __( 'Username was "%s" with email address "%s".', 'wp-simple-firewall' ),
				$oDeletedUser->user_login, $oDeletedUser->user_email
			);
		}

		$oReassignedUser = empty( $nReassigned ) ? null : $oWpUsers->getUserById( $nReassigned );
		if ( empty( $oReassignedUser ) ) {
			$aAuditMessage[] = __( 'Their posts were not reassigned to another user.', 'wp-simple-firewall' );
		}
		else {
			$aAuditMessage[] = sprintf( __( 'Their posts were reassigned to user "%s".', 'wp-simple-firewall' ),
				$oReassignedUser->user_login
			);
		}

		$this->add( 'users', 'user_deleted', 2, implode( ' ', $aAuditMessage ) );
	}
}