<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;

class Users extends Base {

	use WpLoginCapture;

	protected function run() {
		$this->setupLoginCaptureHooks();
		$this->setToCaptureApplicationLogin( true );

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
				/** @var \WP_Error $wpError */
				if ( $user instanceof \WP_User ) {
					$this->auditSuccessAppPassword( $user );
				}
			} );
		}

		add_action( 'wp_create_application_password', [ $this, 'auditAppPasswordNew' ], 30, 2 );
	}

	public function auditAppPasswordNew( $userID, $appPassItem = [] ) {
		if ( is_numeric( $userID ) && !empty( $appPassItem ) && !empty( $appPassItem[ 'name' ] ) ) {
			$this->getCon()->fireEvent(
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
		$this->getCon()->fireEvent(
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
			$this->getCon()->fireEvent(
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
	 * @param int $nReassigned
	 */
	public function auditDeleteUser( $userID, $nReassigned ) {
		$WPU = Services::WpUsers();

		$user = empty( $userID ) ? null : $WPU->getUserById( $userID );
		if ( $user instanceof \WP_User ) {
			$this->getCon()->fireEvent(
				'user_deleted',
				[
					'audit_params' => [
						'user_login' => sanitize_user( $user->user_login ),
						'email'      => $user->user_email,
					]
				]
			);
		}

		$reassigned = empty( $nReassigned ) ? null : $WPU->getUserById( $nReassigned );
		if ( $reassigned instanceof \WP_User ) {
			$this->getCon()->fireEvent(
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
		$this->getCon()->fireEvent(
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
				$this->getCon()->fireEvent( $wpErrorToEventMap[ $code ] );
			}
		}
	}
}