<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Services\Services;

class Users extends Base {

	public function run() {
		add_action( 'wp_login', [ $this, 'auditUserLoginSuccess' ] );
		add_action( 'user_register', [ $this, 'auditNewUserRegistered' ] );
		add_action( 'delete_user', [ $this, 'auditDeleteUser' ], 30, 2 );
	}

	/**
	 * @param string $sUsername
	 */
	public function auditUserLoginSuccess( $sUsername ) {
		if ( !empty( $sUsername ) ) {
			$this->getCon()->fireEvent(
				'user_login',
				[
					'audit' => [
						'user' => sanitize_user( $sUsername ),
					]
				]
			);
		}
	}

	/**
	 * @param int $nUserId
	 */
	public function auditNewUserRegistered( $nUserId ) {
		$oUser = empty( $nUserId ) ? null : Services::WpUsers()->getUserById( $nUserId );
		if ( $oUser instanceof \WP_User ) {
			$this->getCon()->fireEvent(
				'user_registered',
				[
					'audit' => [
						'user'  => sanitize_user( $oUser->user_login ),
						'email' => $oUser->user_email,
					]
				]
			);
		}
	}

	/**
	 * @param int $nUserId
	 * @param int $nReassigned
	 */
	public function auditDeleteUser( $nUserId, $nReassigned ) {
		$oWpUsers = Services::WpUsers();

		$oUser = empty( $nUserId ) ? null : $oWpUsers->getUserById( $nUserId );
		if ( $oUser instanceof \WP_User ) {
			$this->getCon()->fireEvent(
				'user_deleted',
				[
					'audit' => [
						'user'  => sanitize_user( $oUser->user_login ),
						'email' => $oUser->user_email,
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