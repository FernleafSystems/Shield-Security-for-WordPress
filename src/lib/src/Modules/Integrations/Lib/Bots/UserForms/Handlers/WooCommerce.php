<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

class WooCommerce extends Base {

	protected function login() {
		add_filter( 'woocommerce_process_login_errors', [ $this, 'checkLogin_Woo' ], 10, 2 );
	}

	protected function register() {
		add_filter( 'woocommerce_process_registration_errors', [ $this, 'checkRegister_Woo' ], 10, 2 );
	}

	protected function checkout() {
		add_action( 'woocommerce_after_checkout_validation', [ $this, 'checkCheckout_Woo' ], 10, 2 );
	}

	/**
	 * @param array     $data
	 * @param \WP_Error $wpError
	 */
	public function checkCheckout_Woo( $data, $wpError ) {
		if ( empty( $wpError->get_error_code() ) && $this->setAuditAction( 'checkout' )->isBotBlockRequired() ) {
			$this->fireEventBlockCheckout();
			$wpError->add( 'shield-user-checkout', $this->getErrorMessage() );
		}
	}

	/**
	 * @param null|\WP_User|\WP_Error $wpError
	 * @param string                  $username
	 * @return \WP_User|\WP_Error
	 */
	public function checkLogin_Woo( $wpError, $username ) {
		if ( empty( $wpError->get_error_code() ) && $this->setAuditAction( 'login' )->isBotBlockRequired() ) {
			$this->fireEventBlockLogin();
			$this->setAuditUser( $username );
			$wpError->add( 'shield-user-login', $this->getErrorMessage() );
		}
		return $wpError;
	}

	/**
	 * @param \WP_Error $wpError
	 * @param string    $username
	 * @return \WP_Error
	 */
	public function checkRegister_Woo( $wpError, $username ) {
		if ( empty( $wpError->get_error_code() ) && $this->setAuditAction( 'register' )->isBotBlockRequired() ) {
			$this->fireEventBlockRegister();
			$this->setAuditUser( $username );
			$wpError->add( 'shield-user-register', $this->getErrorMessage() );
		}
		return $wpError;
	}
}