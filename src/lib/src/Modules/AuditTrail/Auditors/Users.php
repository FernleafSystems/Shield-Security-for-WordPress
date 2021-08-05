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
	}

	protected function captureLogin( \WP_User $user ) {
		$this->auditUserLoginSuccess( $user );
	}

	public function auditUserLoginSuccess( \WP_User $user ) {
		$this->getCon()->fireEvent(
			Services::WpUsers()->isAppPasswordAuth() ? 'user_login_app' : 'user_login',
			[
				'audit_params' => [
					'user' => $user->user_login,
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
						'user'  => sanitize_user( $user->user_login ),
						'email' => $user->user_email,
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
		$oWpUsers = Services::WpUsers();

		$user = empty( $userID ) ? null : $oWpUsers->getUserById( $userID );
		if ( $user instanceof \WP_User ) {
			$this->getCon()->fireEvent(
				'user_deleted',
				[
					'audit_params' => [
						'user'  => sanitize_user( $user->user_login ),
						'email' => $user->user_email,
					]
				]
			);
		}

		$reassigned = empty( $nReassigned ) ? null : $oWpUsers->getUserById( $nReassigned );
		if ( $reassigned instanceof \WP_User ) {
			$this->getCon()->fireEvent(
				'user_deleted_reassigned',
				[
					'audit_params' => [
						'user' => sanitize_user( $reassigned->user_login ),
					]
				]
			);
		}
	}
}