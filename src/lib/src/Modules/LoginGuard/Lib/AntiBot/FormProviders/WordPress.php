<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

use FernleafSystems\Wordpress\Services\Services;

class WordPress extends BaseFormProvider {

	protected function login() {
		add_action( 'login_form', [ $this, 'printFormInsert' ], 100 );
		add_filter( 'login_form_middle', [ $this, 'formInsertsAppend' ], 100 );
		// We give it a priority of 10 so that we can jump in before WordPress does its own validation.
		add_filter( 'authenticate', [ $this, 'checkLogin' ], 10, 2 );
	}

	protected function register() {
		add_action( 'register_form', [ $this, 'printFormInsert' ] );
		add_filter( 'registration_errors', [ $this, 'checkRegister' ], 10, 2 );
	}

	protected function lostpassword() {
		add_action( 'lostpassword_form', [ $this, 'printFormInsert' ] );
		add_action( 'lostpassword_post', [ $this, 'checkLostPassword' ] );
	}

	/**
	 * Should be a filter added to WordPress's "authenticate" filter, but before WordPress performs
	 * it's own authentication (theirs is priority 30, so we could go in at around 20).
	 * @param null|\WP_User|\WP_Error $oUserOrError
	 * @param string                  $username
	 * @return \WP_User|\WP_Error
	 */
	public function checkLogin( $oUserOrError, $username ) {
		try {
			if ( !is_wp_error( $oUserOrError ) && !empty( $username ) ) {
				$this->setUserToAudit( $username )
					 ->setActionToAudit( 'login' )
					 ->checkProviders();
			}
		}
		catch ( \Exception $e ) {
			$oUserOrError = $this->giveMeWpError( $oUserOrError );
			$oUserOrError->add( $this->getCon()->prefix( rand() ), $e->getMessage() );
		}
		return $oUserOrError;
	}

	/**
	 * @param \WP_Error $wpError
	 * @return \WP_Error
	 */
	public function checkLostPassword( $wpError ) {
		try {
			$this->setUserToAudit( sanitize_user( Services::Request()->post( 'user_login', '' ) ) )
				 ->setActionToAudit( 'reset-password' )
				 ->checkProviders();
		}
		catch ( \Exception $e ) {
			$wpError = $this->giveMeWpError( $wpError );
			$wpError->add( $this->getCon()->prefix( rand() ), $e->getMessage() );
		}
		return $wpError;
	}

	/**
	 * @param \WP_Error $wpError
	 * @param string    $sUsername
	 * @return \WP_Error
	 */
	public function checkRegister( $wpError, $sUsername ) {
		try {
			$this->setUserToAudit( $sUsername )
				 ->setActionToAudit( 'register' )
				 ->checkProviders();
		}
		catch ( \Exception $e ) {
			$wpError = $this->giveMeWpError( $wpError );
			$wpError->add( $this->getCon()->prefix( rand() ), $e->getMessage() );
		}
		return $wpError;
	}
}