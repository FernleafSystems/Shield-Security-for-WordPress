<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;

/**
 * There are a few pathways to updating User passwords, so we try to capture them all, but not duplicate logs.
 */
class Users extends Base {

	use WpLoginCapture;

	private $passwordResetUserIDs = [];

	protected function run() {
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

		add_filter( 'wp_pre_insert_user_data', [ $this, 'capturePreUserUpdate' ], PHP_INT_MAX, 4 );

		add_filter( 'send_password_change_email', [ $this, 'captureUserPasswordUpdate' ], PHP_INT_MAX, 2 );
		add_action( 'wp_set_password', [ $this, 'captureUserPasswordSet' ], PHP_INT_MAX, 2 );
		add_action( 'after_password_reset', [ $this, 'captureUserPasswordReset' ], PHP_INT_MAX );
	}

	public function auditAppPasswordNew( $userID, $appPassItem = [] ) {
		if ( is_numeric( $userID ) && !empty( $appPassItem ) && !empty( $appPassItem[ 'name' ] ) ) {
			$this->con()->fireEvent(
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
		$this->con()->fireEvent(
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
			$this->con()->fireEvent(
				'user_registered',
				[
					'audit_params' => [
						'user_login' => sanitize_user( $user->user_login ),
						'email'      => $user->user_email,
					]
				]
			);
		}
	}

	/**
	 * @param int $userID
	 * @param int $reassignedID
	 */
	public function auditDeleteUser( $userID, $reassignedID ) {
		$WPU = Services::WpUsers();

		$user = empty( $userID ) ? null : $WPU->getUserById( $userID );
		if ( $user instanceof \WP_User ) {
			$this->con()->fireEvent(
				'user_deleted',
				[
					'audit_params' => [
						'user_login' => sanitize_user( $user->user_login ),
						'email'      => $user->user_email,
					]
				]
			);
		}

		$reassigned = empty( $reassignedID ) ? null : $WPU->getUserById( $reassignedID );
		if ( $reassigned instanceof \WP_User ) {
			$this->con()->fireEvent(
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
		$this->con()->fireEvent(
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
				$this->con()->fireEvent( $wpErrorToEventMap[ $code ] );
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

	public function capturePreUserUpdate( $data, $update, $maybeUserID = null, $userdata = null ) {
		if ( !empty( $maybeUserID ) ) {
			$user = Services::WpUsers()->getUserById( $maybeUserID );
			if ( empty( $user ) ) {
				// Bail out.
				error_log( 'Inconsistency: A user ID was passed to pre-update filter but no such user found: '.$maybeUserID );
			}
		}

		return $data;
	}

	private function fireEventUserPasswordUpdated( \WP_User $user ) {
		if ( !\in_array( $user->ID, $this->passwordResetUserIDs ) ) {
			$this->passwordResetUserIDs[] = $user->ID;
			$this->con()->fireEvent(
				'user_password_updated',
				[
					'audit_params' => [
						'user_login' => $user->user_login,
					]
				]
			);
		}
	}
}