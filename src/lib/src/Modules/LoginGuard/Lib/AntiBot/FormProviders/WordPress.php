<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

use FernleafSystems\Wordpress\Services\Services;

class WordPress extends BaseFormProvider {

	protected function login() {
		add_action( 'login_form', [ $this, 'formInsertsPrint' ], 100 );
		add_filter( 'login_form_middle', [ $this, 'formInsertsAppend' ], 100 );

		// We give it a priority of 10 so that we can jump in before WordPress does its own validation.
		add_filter( 'authenticate', [ $this, 'checkLogin' ], 10, 2 );
	}

	protected function register() {
		add_action( 'register_form', [ $this, 'formInsertsPrint' ] );

		add_filter( 'registration_errors', [ $this, 'checkRegister' ], 10, 2 );
	}

	protected function lostpassword() {
		add_action( 'lostpassword_form', [ $this, 'formInsertsPrint' ] );
		add_action( 'lostpassword_post', [ $this, 'checkLostPassword' ], 10, 1 );
	}

	/**
	 * Should be a filter added to WordPress's "authenticate" filter, but before WordPress performs
	 * it's own authentication (theirs is priority 30, so we could go in at around 20).
	 * @param null|\WP_User|\WP_Error $oUserOrError
	 * @param string                  $sUsername
	 * @return \WP_User|\WP_Error
	 */
	public function checkLogin( $oUserOrError, $sUsername ) {
		try {
			if ( !is_wp_error( $oUserOrError ) && !empty( $sUsername ) ) {
				$this->setUserToAudit( $sUsername )
					 ->setActionToAudit( 'login' )
					 ->checkProviders();
			}
		}
		catch ( \Exception $oE ) {
			$oUserOrError = $this->giveMeWpError( $oUserOrError );
			$oUserOrError->add( $this->getCon()->prefix( rand() ), $oE->getMessage() );
		}
		return $oUserOrError;
	}

	/**
	 * @param \WP_Error $oWpError
	 * @return \WP_Error
	 */
	public function checkLostPassword( $oWpError ) {
		try {
			$this->setUserToAudit( sanitize_user( Services::Request()->post( 'user_login', '' ) ) )
				 ->setActionToAudit( 'reset-password' )
				 ->checkProviders();
		}
		catch ( \Exception $oE ) {
			$oWpError = $this->giveMeWpError( $oWpError );
			$oWpError->add( $this->getCon()->prefix( rand() ), $oE->getMessage() );
		}
		return $oWpError;
	}

	/**
	 * @param \WP_Error $oWpError
	 * @param string    $sUsername
	 * @return \WP_Error
	 */
	public function checkRegister( $oWpError, $sUsername ) {
		try {
			$this->setUserToAudit( $sUsername )
				 ->setActionToAudit( 'register' )
				 ->checkProviders();
		}
		catch ( \Exception $oE ) {
			$oWpError = $this->giveMeWpError( $oWpError );
			$oWpError->add( $this->getCon()->prefix( rand() ), $oE->getMessage() );
		}
		return $oWpError;
	}
}