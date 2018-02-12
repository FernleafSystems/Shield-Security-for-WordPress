<?php

if ( class_exists( 'ICWP_WPSF_Processor_AuditTrail_Users' ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/audit_trail_auditor_base.php' );

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

		if ( !empty( $nUserId ) ) {

			$oNewUser = $this->loadWpUsers()->getUserById( $nUserId );

			$this->add( 'users', 'user_registered', 1,
				_wpsf__( 'New WordPress user registered.' ).' '
				.sprintf(
					_wpsf__( 'New username is "%s" with email address "%s".' ),
					empty( $oNewUser ) ? 'unknown' : $oNewUser->get( 'user_login' ),
					empty( $oNewUser ) ? 'unknown' : $oNewUser->get( 'user_email' )
				)
			);
		}
	}

	/**
	 * @param int $nUserId
	 * @param int $nReassigned
	 */
	public function auditDeleteUser( $nUserId, $nReassigned ) {
		if ( empty( $nUserId ) ) {
			return;
		}

		$oWpUsers = $this->loadWpUsers();
		$oDeletedUser = $oWpUsers->getUserById( $nUserId );
		$oReassignedUser = empty( $nReassigned ) ? null : $oWpUsers->getUserById( $nReassigned );

		// Build the audit message
		$sAuditMessage =
			_wpsf__( 'WordPress user deleted.' )
			.' '.sprintf(
				_wpsf__( 'Username was "%s" with email address "%s".' ),
				empty( $oDeletedUser ) ? 'unknown' : $oDeletedUser->get( 'user_login' ),
				empty( $oDeletedUser ) ? 'unknown' : $oDeletedUser->get( 'user_email' )
			).' ';
		if ( empty( $oReassignedUser ) ) {
			$sAuditMessage .= _wpsf__( 'Their posts were not reassigned to another user.' );
		}
		else {
			$sAuditMessage .= sprintf(
				_wpsf__( 'Their posts were reassigned to user "%s".' ),
				$oReassignedUser->get( 'user_login' )
			);
		}

		$this->add( 'users', 'user_deleted', 2, $sAuditMessage );
	}
}