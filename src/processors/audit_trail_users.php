<?php
/**
 * Copyright (c) 2015 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

require_once( 'base.php' );

if ( !class_exists('ICWP_WPSF_Processor_AuditTrail_Users') ):

	class ICWP_WPSF_Processor_AuditTrail_Users extends ICWP_WPSF_Processor_Base {

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

			$oWp = $this->loadWpFunctionsProcessor();
			$oNewUser = $oWp->getUserById( $nUserId );

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

			$oWp = $this->loadWpFunctionsProcessor();
			$oDeletedUser = $oWp->getUserById( $nUserId );
			$oReassignedUser = empty( $nReassigned ) ? null : $oWp->getUserById( $nReassigned );

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