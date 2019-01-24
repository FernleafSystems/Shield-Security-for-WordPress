<?php

class ICWP_WPSF_Processor_AuditTrail_Users extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
		add_action( 'wp_login_failed', array( $this, 'auditUserLoginFail' ) );
		add_action( 'wp_login', array( $this, 'auditUserLoginSuccess' ) );
		add_action( 'user_register', array( $this, 'auditNewUserRegistered' ) );
		add_action( 'delete_user', array( $this, 'auditDeleteUser' ), 30, 2 );
	}

	/**
	 * @param string $sUsername
	 */
	public function auditUserLoginSuccess( $sUsername ) {

		if ( !empty( $sUsername ) ) {
			$this->add( 'users', 'login_success', 1,
				sprintf( _wpsf__( 'Attempted user login by "%s" was successful.' ), $sUsername ),
				$sUsername
			);
		}
	}

	/**
	 * @param string $sUsername
	 */
	public function auditUserLoginFail( $sUsername ) {

		if ( !empty( $sUsername ) ) {
			$this->add( 'users', 'login_failure', 2,
				sprintf( _wpsf__( 'Attempted user login by "%s" failed.' ), $sUsername )
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
				_wpsf__( 'New WordPress user registered.' ).' '
				.sprintf(
					_wpsf__( 'New username is "%s" with email address "%s".' ),
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

		$aAuditMessage = array( _wpsf__( 'WordPress user deleted.' ) );

		$oDeletedUser = empty( $nUserId ) ? null : $oWpUsers->getUserById( $nUserId );
		if ( empty( $oDeletedUser ) ) {
			$aAuditMessage[] = _wpsf__( 'User is unknown as it could not be loaded.' );
		}
		else {
			$aAuditMessage[] = sprintf( _wpsf__( 'Username was "%s" with email address "%s".' ),
				$oDeletedUser->user_login, $oDeletedUser->user_email
			);
		}

		$oReassignedUser = empty( $nReassigned ) ? null : $oWpUsers->getUserById( $nReassigned );
		if ( empty( $oReassignedUser ) ) {
			$aAuditMessage[] = _wpsf__( 'Their posts were not reassigned to another user.' );
		}
		else {
			$aAuditMessage[] = sprintf( _wpsf__( 'Their posts were reassigned to user "%s".' ),
				$oReassignedUser->user_login
			);
		}

		$this->add( 'users', 'user_deleted', 2, implode( ' ', $aAuditMessage ) );
	}
}