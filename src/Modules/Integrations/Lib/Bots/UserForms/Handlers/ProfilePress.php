<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

class ProfilePress extends Base {

	protected function register() {
		add_filter( 'ppress_login_validation', [ $this, 'checkLogin' ] );
		add_filter( 'ppress_registration_validation', [ $this, 'checkRegister' ] );
	}

	/**
	 * @param \WP_Error|mixed $wpErrors
	 */
	public function checkLogin( $wpErrors ) :\WP_Error {
		if ( !is_wp_error( $wpErrors ) ) {
			$wpErrors = new \WP_Error();
		}
		if ( $this->setAuditAction( 'login' )->isBotBlockRequired() ) {
			$wpErrors->add( 'shield-fail-login', $this->getErrorMessage() );
			$this->fireEventBlockLogin();
		}
		return $wpErrors;
	}

	/**
	 * @param \WP_Error|mixed $wpErrors
	 */
	public function checkRegister( $wpErrors ) :\WP_Error {
		if ( !is_wp_error( $wpErrors ) ) {
			$wpErrors = new \WP_Error();
		}
		if ( $this->setAuditAction( 'register' )->isBotBlockRequired() ) {
			$wpErrors->add( 'shield-fail-register', $this->getErrorMessage() );
			$this->fireEventBlockRegister();
		}
		return $wpErrors;
	}
}