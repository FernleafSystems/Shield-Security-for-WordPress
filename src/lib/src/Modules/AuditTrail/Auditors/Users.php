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

	private $passwordResetUserIDs = [];

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

		add_filter( 'send_password_change_email', [ $this, 'captureUserPasswordUpdate' ], \PHP_INT_MAX, 2 );
		add_action( 'wp_set_password', [ $this, 'captureUserPasswordSet' ], \PHP_INT_MAX, 2 );
		add_action( 'after_password_reset', [ $this, 'captureUserPasswordReset' ], \PHP_INT_MAX );
	}

	public function auditAppPasswordNew( $userID, $appPassItem = [] ) {
		if ( \is_numeric( $userID ) && !empty( $appPassItem ) && !empty( $appPassItem[ 'name' ] ) ) {
			self::con()->fireEvent(
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
		self::con()->fireEvent(
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
			self::con()->fireEvent(
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
		self::con()->fireEvent(
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
				self::con()->fireEvent( $wpErrorToEventMap[ $code ] );
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
		$user = Services::WpUsers()->getUserById( $user_id );
		if ( $user instanceof \WP_User ) {
			$this->fireEventUserPasswordUpdated( $user );
		}
	}

	public function captureUserPasswordReset( $user ) {
		if ( $user instanceof \WP_User ) {
			$this->fireEventUserPasswordUpdated( $user );
		}
	}

	public function captureProfileUpdate( $userID, $oldUser, $userdata = null ) :void {
		if ( !empty( $userID ) && \is_array( $userdata ) && $oldUser instanceof \WP_User ) {
			$user = Services::WpUsers()->getUserById( $userID );
			if ( empty( $user ) ) {
				// Bail out.
				error_log( 'Inconsistency: A user ID was passed to "profile_update" action but no such user found: '.$userID );
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
		if ( !\in_array( $user->ID, $this->passwordResetUserIDs ) ) {
			$this->passwordResetUserIDs[] = $user->ID;
			$this->fireAuditEvent( 'user_password_updated', [
				'user_login' => $user->user_login,
			] );

			$this->updateSnapshotItem( $user );
		}
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