<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes\ZoneReportUsers;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\DiffVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper\SnapUsers;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;

/**
 * There are a few pathways to updating User passwords, so we try to capture them all, but not duplicate logs.
 */
class Users extends Base {

	use WpLoginCapture;

	private array $passwordUpdatedUserIDs = [];

	private array $passwordResetUserIDs = [];

	private array $activePasswordResetUserIDs = [];

	private array $activePasswordResetRequests = [];

	protected function initAuditHooks() :void {
		$this->setupLoginCaptureHooks();
		$this->setToCaptureApplicationLogin();

		add_action( 'user_register', [ $this, 'auditNewUserRegistered' ] );
		add_action( 'delete_user', [ $this, 'auditDeleteUser' ], 30, 2 );

		if ( Services::WpGeneral()->getWordpressIsAtLeastVersion( '5.6' ) ) {
			add_action( 'application_password_failed_authentication', function ( $wpError ) {
				/** @var \WP_Error $wpError */
				if ( is_wp_error( $wpError ) && $wpError->has_errors() ) {
					$this->auditFailedAppPassword( $wpError );
				}
			} );
			add_action( 'application_password_did_authenticate', function ( $user ) {
				if ( $user instanceof \WP_User ) {
					$this->auditSuccessAppPassword( $user );
				}
			} );
		}

		add_action( 'wp_create_application_password', [ $this, 'auditAppPasswordNew' ], 30, 2 );

		add_action( 'profile_update', [ $this, 'captureProfileUpdate' ], \PHP_INT_MAX, 3 );

		add_filter( 'lostpassword_errors', [ $this, 'capturePasswordResetRequestErrors' ], \PHP_INT_MAX, 2 );
		add_filter( 'allow_password_reset', [ $this, 'capturePasswordResetDisallowed' ], \PHP_INT_MAX, 2 );
		add_filter( 'retrieve_password_message', [ $this, 'capturePasswordResetRequest' ], \PHP_INT_MAX, 4 );
		add_action( 'password_reset', [ $this, 'captureUserPasswordResetStarted' ], \PHP_INT_MAX, 2 );

		add_filter( 'send_password_change_email', [ $this, 'captureUserPasswordUpdate' ], \PHP_INT_MAX, 2 );
		add_action( 'wp_set_password', [ $this, 'captureUserPasswordSet' ], \PHP_INT_MAX, 2 );
		add_action( 'after_password_reset', [ $this, 'captureUserPasswordReset' ], \PHP_INT_MAX, 2 );
	}

	public function auditAppPasswordNew( $userID, $appPassItem = [] ) {
		if ( \is_numeric( $userID ) && !empty( $appPassItem ) && !empty( $appPassItem[ 'name' ] ) ) {
			self::con()->comps->events->fireEvent(
				'app_pass_created',
				[
					'audit_params' => [
						'user_login'    => Services::WpUsers()->getUserById( $userID )->user_login,
						'app_pass_name' => $appPassItem[ 'name' ],
					]
				]
			);
		}
	}

	protected function captureLogin( \WP_User $user ) {
		$this->auditUserLoginSuccess( $user );
	}

	public function auditUserLoginSuccess( \WP_User $user ) {
		self::con()->comps->events->fireEvent(
			Services::WpUsers()->isAppPasswordAuth() ? 'user_login_app' : 'user_login',
			[
				'audit_params' => [
					'user_login' => $user->user_login,
				]
			]
		);
	}

	public function auditNewUserRegistered( int $userID ) {
		$user = empty( $userID ) ? null : Services::WpUsers()->getUserById( $userID );
		if ( $user instanceof \WP_User ) {
			$this->fireAuditEvent( 'user_registered', [
				'user_login' => sanitize_user( $user->user_login ),
				'email'      => $user->user_email,
			] );

			$this->updateSnapshotItem( $user );
		}
	}

	/**
	 * @param int $userID
	 * @param int $reassignedID
	 */
	public function auditDeleteUser( $userID, $reassignedID ) {

		$user = empty( $userID ) ? null : Services::WpUsers()->getUserById( $userID );
		if ( $user instanceof \WP_User ) {
			$this->fireAuditEvent( 'user_deleted', [
				'user_login' => sanitize_user( $user->user_login ),
				'email'      => $user->user_email,
			] );

			$this->removeSnapshotItem( $user );
		}

		$reassigned = empty( $reassignedID ) ? null : Services::WpUsers()->getUserById( $reassignedID );
		if ( $reassigned instanceof \WP_User ) {
			self::con()->comps->events->fireEvent(
				'user_deleted_reassigned',
				[
					'audit_params' => [
						'user_login' => sanitize_user( $reassigned->user_login ),
					]
				]
			);
		}
	}

	private function auditSuccessAppPassword( \WP_User $user ) {
		self::con()->comps->events->fireEvent(
			'user_login_app',
			[
				'audit_params' => [
					'user_login' => $user->user_login,
				]
			]
		);
	}

	private function auditFailedAppPassword( \WP_Error $error ) {

		$wpErrorToEventMap = [
			'invalid_email'                           => 'app_invalid_email',
			'invalid_username'                        => 'app_invalid_username',
			'incorrect_password'                      => 'app_incorrect_password',
			'application_passwords_disabled'          => 'app_passwords_disabled',
			'application_passwords_disabled_for_user' => 'app_passwords_disabled_user',
		];

		foreach ( $error->get_error_codes() as $code ) {
			if ( isset( $wpErrorToEventMap[ $code ] ) ) {
				self::con()->comps->events->fireEvent( $wpErrorToEventMap[ $code ] );
			}
		}
	}

	public function captureUserPasswordUpdate( $sendEmail, $userData ) {
		if ( \is_array( $userData ) && isset( $userData[ 'ID' ] ) ) {
			$user = Services::WpUsers()->getUserById( $userData[ 'ID' ] );
			if ( $user instanceof \WP_User ) {
				$this->fireEventUserPasswordUpdated( $user );
			}
		}
		return $sendEmail;
	}

	public function captureUserPasswordSet( $password, $user_id ) {
		unset( $password );

		$user = Services::WpUsers()->getUserById( $user_id );
		if ( $user instanceof \WP_User && !$this->isPasswordResetActive( $user ) ) {
			$this->fireEventUserPasswordUpdated( $user );
		}
	}

	public function captureUserPasswordResetStarted( $user, $newPass = null ) :void {
		unset( $newPass );

		if ( $user instanceof \WP_User ) {
			$this->activePasswordResetUserIDs[ (int)$user->ID ] = true;
		}
	}

	public function captureUserPasswordReset( $user, $newPass = null ) {
		unset( $newPass );

		if ( $user instanceof \WP_User ) {
			unset( $this->activePasswordResetUserIDs[ (int)$user->ID ] );
			$this->fireEventUserPasswordReset( $user );
		}
	}

	public function capturePasswordResetRequestErrors( $errors, $userData ) {
		if ( is_wp_error( $errors ) ) {
			if ( $errors->has_errors() ) {
				$this->firePasswordResetRequestFailedEvent(
					$this->getPasswordResetRequestedLogin(),
					$this->getPasswordResetFailureReason( $errors )
				);
			}
			elseif ( $userData instanceof \WP_User ) {
				$this->activePasswordResetRequests[ (int)$userData->ID ] = $this->getPasswordResetRequestedLogin( $userData );
			}
			else {
				$this->firePasswordResetRequestFailedEvent(
					$this->getPasswordResetRequestedLogin(),
					'invalidcombo'
				);
			}
		}
		return $errors;
	}

	public function capturePasswordResetDisallowed( $allow, $userID ) {
		$userID = (int)$userID;
		if ( isset( $this->activePasswordResetRequests[ $userID ] )
			 && ( $allow === false || is_wp_error( $allow ) ) ) {
			$this->firePasswordResetRequestFailedEvent(
				$this->activePasswordResetRequests[ $userID ],
				is_wp_error( $allow ) ? $this->getPasswordResetFailureReason( $allow ) : 'no_password_reset'
			);
			unset( $this->activePasswordResetRequests[ $userID ] );
		}
		return $allow;
	}

	public function capturePasswordResetRequest( $message, $key, $userLogin, $userData ) {
		unset( $key, $userLogin );

		if ( $userData instanceof \WP_User ) {
			unset( $this->activePasswordResetRequests[ (int)$userData->ID ] );
			$this->fireAuditEvent( 'user_password_reset_requested', [
				'user_login' => $userData->user_login,
			] );
		}
		return $message;
	}

	public function captureProfileUpdate( $userID, $oldUser, $userdata = null ) :void {
		if ( !empty( $userID ) && \is_array( $userdata ) && $oldUser instanceof \WP_User ) {
			$user = Services::WpUsers()->getUserById( $userID );
			if ( empty( $user ) ) {
				// Bail out.
				error_log( sprintf( __( 'Inconsistency: A user ID was passed to "profile_update" but no such user was found: %s', 'wp-simple-firewall' ), $userID ) );
				return;
			}

			if ( $oldUser->user_email !== $user->user_email ) {
				$this->fireAuditEvent( 'user_email_updated', [
					'user_login' => $user->user_login,
				] );
			}
			if ( $oldUser->user_pass !== $user->user_pass ) {
				$this->fireEventUserPasswordUpdated( $user );
			}

			if ( !\in_array( 'administrator', $oldUser->roles ) && \in_array( 'administrator', $user->roles ) ) {
				$this->fireAuditEvent( 'user_promoted', [
					'user_login' => $user->user_login,
				] );
			}
			elseif ( \in_array( 'administrator', $oldUser->roles ) && !\in_array( 'administrator', $user->roles ) ) {
				$this->fireAuditEvent( 'user_demoted', [
					'user_login' => $user->user_login,
				] );
			}

			$this->updateSnapshotItem( $user );
		}
	}

	private function fireEventUserPasswordUpdated( \WP_User $user ) {
		if ( !$this->isPasswordResetActive( $user ) && !isset( $this->passwordUpdatedUserIDs[ (int)$user->ID ] ) ) {
			$this->passwordUpdatedUserIDs[ (int)$user->ID ] = true;
			$this->fireAuditEvent( 'user_password_updated', [
				'user_login' => $user->user_login,
			] );

			$this->updateSnapshotItem( $user );
		}
	}

	private function fireEventUserPasswordReset( \WP_User $user ) {
		if ( !isset( $this->passwordResetUserIDs[ (int)$user->ID ] ) ) {
			$this->passwordResetUserIDs[ (int)$user->ID ] = true;
			$this->fireAuditEvent( 'user_password_reset', [
				'user_login' => $user->user_login,
			] );

			$this->updateSnapshotItem( $user );
		}
	}

	private function isPasswordResetActive( \WP_User $user ) :bool {
		return isset( $this->activePasswordResetUserIDs[ (int)$user->ID ] );
	}

	private function firePasswordResetRequestFailedEvent( string $requestedLogin, string $reason ) :void {
		$this->fireAuditEvent( 'user_password_reset_request_failed', [
			'requested_login' => $requestedLogin,
			'reason'          => $reason,
		] );
	}

	private function getPasswordResetRequestedLogin( ?\WP_User $user = null ) :string {
		$login = Services::Request()->post( 'user_login', '' );
		if ( \is_array( $login ) ) {
			$login = '';
		}
		$login = \trim( (string)wp_unslash( $login ) );
		if ( $login === '' && $user instanceof \WP_User ) {
			$login = $user->user_login;
		}
		return sanitize_text_field( $login );
	}

	private function getPasswordResetFailureReason( \WP_Error $errors ) :string {
		$codes = \array_filter( \array_map( '\strval', $errors->get_error_codes() ) );
		return empty( $codes ) ? 'unknown' : \implode( ',', $codes );
	}

	/**
	 * @snapshotDiffCron
	 */
	public function snapshotDiffForUsers( DiffVO $diff ) {

		foreach ( $diff->added as $added ) {
			$user = Services::WpUsers()->getUserById( $added[ 'uniq' ] );
			$this->fireAuditEvent( 'user_registered', [
				'user_login' => sanitize_user( $user->user_login ),
				'email'      => $user->user_email,
			] );
		}

		foreach ( $diff->removed as $removed ) {
			$this->fireAuditEvent( 'user_deleted', [
				'user_login' => sanitize_user( $removed[ 'user_login' ] ),
				'email'      => 'unavailable',
			] );
		}

		foreach ( $diff->changed as $changed ) {
			$old = $changed[ 'old' ];
			$new = $changed[ 'new' ];
			$user = Services::WpUsers()->getUserById( $old[ 'uniq' ] );

			if ( !$old[ 'is_admin' ] && $new[ 'is_admin' ] ) {
				$this->fireAuditEvent( 'user_promoted', [
					'user_login' => $user->user_login,
				] );
			}
			elseif ( $old[ 'is_admin' ] && !$new[ 'is_admin' ] ) {
				$this->fireAuditEvent( 'user_demoted', [
					'user_login' => $user->user_login,
				] );
			}

			if ( $old[ 'user_pass' ] !== $new[ 'user_pass' ] ) {
				$this->fireEventUserPasswordUpdated( $user );
			}
			if ( $old[ 'user_email' ] !== $new[ 'user_email' ] ) {
				$this->fireAuditEvent( 'user_email_updated', [
					'user_login' => $user->user_login,
				] );
			}
		}
	}

	public function getReporter() :ZoneReportUsers {
		return new ZoneReportUsers();
	}

	public function getSnapper() :SnapUsers {
		return new SnapUsers();
	}
}
