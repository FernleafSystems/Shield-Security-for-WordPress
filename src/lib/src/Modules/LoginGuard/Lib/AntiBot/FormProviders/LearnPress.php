<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

class LearnPress extends BaseFormProvider {

	protected function login() {
		add_action( 'learn-press/after-form-login-fields', [ $this, 'formInsertsPrint' ], 100 );
		add_action( 'learn-press/before-checkout-form-login-button', [ $this, 'formInsertsPrint' ], 100 );
		add_filter( 'learn-press/login-validate-field', [ $this, 'checkLogin' ], 100 );
	}

	protected function register() {
		add_action( 'learn-press/after-form-register-fields', [ $this, 'formInsertsPrint' ], 100 );
		add_filter( 'learn-press/register-validate-field', [ $this, 'checkRegister' ], 100, 1 );
	}

	/**
	 * @param string|\WP_Error $sFieldNameOrError
	 * @return string|\WP_Error
	 */
	public function checkLogin( $sFieldNameOrError ) {
		if ( !empty( $sFieldNameOrError ) && !is_wp_error( $sFieldNameOrError ) ) {
			try {
				$this->setActionToAudit( 'learnpress-login' )
					 ->checkProviders();
			}
			catch ( \Exception $oE ) {
				$sFieldNameOrError = new \WP_Error( 'shield-fail-login', $oE->getMessage() );
			}
		}
		return $sFieldNameOrError;
	}

	/**
	 * @param string|\WP_Error $sFieldNameOrError
	 * @return string|\WP_Error
	 */
	public function checkRegister( $sFieldNameOrError ) {
		if ( !empty( $sFieldNameOrError ) && !is_wp_error( $sFieldNameOrError ) ) {
			try {
				$this->setActionToAudit( 'learnpress-register' )
					 ->checkProviders();
			}
			catch ( \Exception $oE ) {
				$sFieldNameOrError = new \WP_Error( 'shield-fail-register', $oE->getMessage() );
			}
		}
		return $sFieldNameOrError;
	}
}