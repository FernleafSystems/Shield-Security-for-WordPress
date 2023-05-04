<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

/**
 * LearnPress actually uses action 'init' to process forms, so that's why we hook AntibotSetup so early (-100).
 */
class LearnPress extends BaseFormProvider {

	protected function login() {
		add_action( 'learn-press/after-form-login-fields', [ $this, 'printFormInsert' ], 100 );
		add_action( 'learn-press/before-checkout-form-login-button', [ $this, 'printFormInsert' ], 100 );
		add_filter( 'learn-press/login-validate-field', [ $this, 'checkLogin' ], 100 );
	}

	protected function register() {
		add_action( 'learn-press/after-form-register-fields', [ $this, 'printFormInsert' ], 100 );
		add_filter( 'learn-press/register-validate-field', [ $this, 'checkRegister' ], 100 );
	}

	/**
	 * @param string|\WP_Error $fieldNameOrError
	 * @return string|\WP_Error
	 */
	public function checkLogin( $fieldNameOrError ) {
		if ( !empty( $fieldNameOrError ) && !is_wp_error( $fieldNameOrError ) ) {
			try {
				$this->setActionToAudit( 'learnpress-login' )
					 ->checkProviders();
			}
			catch ( \Exception $e ) {
				$fieldNameOrError = new \WP_Error( 'shield-fail-login', $e->getMessage() );
			}
		}
		return $fieldNameOrError;
	}

	/**
	 * @param string|\WP_Error $fieldNameOrError
	 * @return string|\WP_Error
	 */
	public function checkRegister( $fieldNameOrError ) {
		if ( !empty( $fieldNameOrError ) && !is_wp_error( $fieldNameOrError ) ) {
			try {
				$this->setActionToAudit( 'learnpress-register' )
					 ->checkProviders();
			}
			catch ( \Exception $e ) {
				$fieldNameOrError = new \WP_Error( 'shield-fail-register', $e->getMessage() );
			}
		}
		return $fieldNameOrError;
	}
}