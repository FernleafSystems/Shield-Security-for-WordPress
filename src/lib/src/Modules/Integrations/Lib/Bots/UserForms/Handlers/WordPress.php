<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

use FernleafSystems\Wordpress\Services\Services;

class WordPress extends Base {

	protected function login() {
		// We give it a priority of 10 so that we can jump in before WordPress does its own validation.
		add_filter( 'authenticate', [ $this, 'checkLogin_WP' ], 10, 2 );
	}

	protected function register() {
		add_filter( 'registration_errors', [ $this, 'checkRegister_WP' ], 10, 2 );
	}

	protected function lostpassword() {
		add_action( 'lostpassword_post', [ $this, 'checkLostPassword_WP' ], 10, 2 );
	}

	/**
	 * Should be a filter added to WordPress's "authenticate" filter, but before WordPress performs
	 * it's own authentication (theirs is priority 30, so we could go in at around 20).
	 * @param null|\WP_User|\WP_Error $userOrError
	 * @param string                  $username
	 * @return \WP_User|\WP_Error
	 */
	public function checkLogin_WP( $userOrError, $username ) {
		if ( !is_wp_error( $userOrError ) || empty( $userOrError->get_error_codes() ) ) {
			$this->fireEventBlockLogin();
			$this->setAuditAction( 'login' )
				 ->setAuditUser( $username );
			if ( $this->isBotBlockRequired() ) {
				$userOrError = new \WP_Error( 'shield-fail-login', $this->getErrorMessage() );
				remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );  // wp-includes/user.php
				remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );  // wp-includes/user.php
			}
		}
		return $userOrError;
	}

	/**
	 * @param \WP_Error      $wpError
	 * @param \WP_User|false $user
	 */
	public function checkLostPassword_WP( $wpError = null, $user = false ) {
		if ( is_wp_error( $wpError ) && empty( $wpError->get_error_codes() ) ) {
			$this->fireEventBlockLostpassword();
			$this->setAuditAction( 'lostpassword' );
			if ( $user instanceof \WP_User ) {
				$this->setAuditUser( $user->user_login );
			}
			else {
				$this->setAuditUser( sanitize_user( Services::Request()->post( 'user_login', '' ) ) );
			}
			if ( $this->isBotBlockRequired() ) {
				$wpError->add( 'shield-fail-lostpassword', $this->getErrorMessage() );
			}
		}
	}

	/**
	 * @param \WP_Error $wpError
	 * @param string    $username
	 * @return \WP_Error
	 */
	public function checkRegister_WP( $wpError, $username ) {
		if ( !is_wp_error( $wpError ) || empty( $wpError->get_error_codes() ) ) {
			$this->fireEventBlockRegister();
			$this->setAuditAction( 'register' )
				 ->setAuditUser( $username );
			if ( $this->isBotBlockRequired() ) {
				$wpError = new \WP_Error( 'shield-fail-login', $this->getErrorMessage() );
			}
		}
		return $wpError;
	}

	public static function IsProviderInstalled() :bool {
		return true;
	}

	protected function isProOnly() :bool {
		return false;
	}
}