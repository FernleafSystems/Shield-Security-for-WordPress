<?php

if ( !class_exists('ICWP_WPSF_Processor_AuditTrail_Users') ):

	require_once( dirname(__FILE__).ICWP_DS.'base_wpsf.php' );

	class ICWP_WPSF_Processor_AuditTrail_Users extends ICWP_WPSF_Processor_BaseWpsf {

		/**
		 */
		public function run() {
			if ( $this->getIsOption( 'enable_audit_context_users', 'Y' ) ) {
				add_action( 'wp_login_failed', array( $this, 'auditUserLoginFail' ) );
				add_action( 'wp_login', array( $this, 'auditUserLoginSuccess' ) );
				add_action( 'user_register', array( $this, 'auditNewUserRegistered' ) );
				add_action( 'delete_user', array( $this, 'auditDeleteUser' ), 30, 2 );
			}
		}

		/**
		 * @param string $sUsername
		 */
		public function auditUserLoginSuccess( $sUsername ) {

			if ( empty( $sUsername ) ) {
				return;
			}

			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'users',
				'login_success',
				1,
				sprintf( _wpsf__( 'Attempted user login by "%s" was successful.' ), $sUsername ),
				$sUsername
			);
		}

		/**
		 * @param string $sUsername
		 */
		public function auditUserLoginFail( $sUsername ) {

			if ( empty( $sUsername ) ) {
				return;
			}

			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'users',
				'login_failure',
				2,
				sprintf( _wpsf__( 'Attempted user login by "%s" failed.' ), $sUsername )
			);
		}

		/**
		 * @param int $nUserId
		 */
		public function auditNewUserRegistered( $nUserId ) {
			if ( empty( $nUserId ) ) {
				return;
			}

			$oNewUser = $this->loadWpUsersProcessor()->getUserById( $nUserId );

			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'users',
				'user_registered',
				1,
				_wpsf__( 'New WordPress user registered.').' '
				.sprintf(
					_wpsf__( 'New username is "%s" with email address "%s".' ),
					empty( $oNewUser ) ? 'unknown' : $oNewUser->get( 'user_login' ),
					empty( $oNewUser ) ? 'unknown' : $oNewUser->get( 'user_email' )
				)
			);
		}

		/**
		 * @param int $nUserId
		 * @param int $nReassigned
		 */
		public function auditDeleteUser( $nUserId, $nReassigned ) {
			if ( empty( $nUserId ) ) {
				return;
			}

			$oWpUsers = $this->loadWpUsersProcessor();
			$oDeletedUser = $oWpUsers->getUserById( $nUserId );
			$oReassignedUser = empty( $nReassigned ) ? null : $oWpUsers->getUserById( $nReassigned );

			// Build the audit message
			$sAuditMessage =
				_wpsf__( 'WordPress user deleted.')
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

			$oAuditTrail = $this->getAuditTrailEntries();
			$oAuditTrail->add(
				'users',
				'user_deleted',
				2,
				$sAuditMessage
			);
		}

		/**
		 * @return ICWP_WPSF_AuditTrail_Entries
		 */
		protected function getAuditTrailEntries() {
			return ICWP_WPSF_AuditTrail_Entries::GetInstance();
		}
	}

endif;