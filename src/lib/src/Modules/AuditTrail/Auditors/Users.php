<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;

class Users extends Base {

	use WpLoginCapture;

	public function run() {
		$this->setupLoginCaptureHooks();
		add_action( 'user_register', [ $this, 'auditNewUserRegistered' ] );
		add_action( 'delete_user', [ $this, 'auditDeleteUser' ], 30, 2 );
	}

	protected function captureLogin( \WP_User $user ) {
		$this->auditUserLoginSuccess( $user );
	}

	public function auditUserLoginSuccess( \WP_User $user ) {
		$this->getCon()->fireEvent(
			'user_login',
			[
				'audit' => [
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
					'audit' => [
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
					'audit' => [
						'user'  => sanitize_user( $user->user_login ),
						'email' => $user->user_email,
					]
				]
			);
		}

		$oReassignedUser = empty( $nReassigned ) ? null : $oWpUsers->getUserById( $nReassigned );
		if ( $oReassignedUser instanceof \WP_User ) {
			$this->getCon()->fireEvent(
				'user_deleted_reassigned',
				[
					'audit' => [
						'user' => sanitize_user( $oReassignedUser->user_login ),
					]
				]
			);
		}
	}
}