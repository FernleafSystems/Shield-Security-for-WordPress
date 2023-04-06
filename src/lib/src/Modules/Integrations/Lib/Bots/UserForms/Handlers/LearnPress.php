<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

class LearnPress extends Base {

	protected function login() {
		add_filter( 'learn-press/login-validate-field', [ $this, 'checkLogin_LP' ], 100 );
	}

	protected function register() {
		add_filter( 'learn-press/register-validate-field', [ $this, 'checkRegister_LP' ], 100 );
	}

	/**
	 * @param string|\WP_Error $maybeError
	 * @return string|\WP_Error
	 */
	public function checkLogin_LP( $maybeError ) {
		if ( !is_wp_error( $maybeError ) && $this->setAuditAction( 'login' )->isBotBlockRequired() ) {
			$this->fireEventBlockLogin();
			$maybeError = new \WP_Error( 'shield-fail-login', $this->getErrorMessage() );
		}
		return $maybeError;
	}

	/**
	 * @param string|\WP_Error $maybeError
	 * @return string|\WP_Error
	 */
	public function checkRegister_LP( $maybeError ) {
		if ( !is_wp_error( $maybeError ) && $this->setAuditAction( 'register' )->isBotBlockRequired() ) {
			$this->fireEventBlockRegister();
			$maybeError = new \WP_Error( 'shield-fail-register', $this->getErrorMessage() );
		}
		return $maybeError;
	}
}