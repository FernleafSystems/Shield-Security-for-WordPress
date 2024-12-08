<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

class WooCommerce extends Base {

	protected function login() {
		add_filter( 'woocommerce_process_login_errors', [ $this, 'checkLogin_Woo' ], 11, 2 );
	}

	protected function register() {
		add_filter( 'woocommerce_process_registration_errors', [ $this, 'checkRegister_Woo' ], 11, 2 );
	}

	protected function checkout() {
		add_action( 'woocommerce_after_checkout_validation', [ $this, 'checkCheckout_Woo' ], 11, 2 );
	}

	/**
	 * @param array|mixed     $data
	 * @param \WP_Error|mixed $wpError
	 */
	public function checkCheckout_Woo( $data, $wpError ) {
		if ( is_wp_error( $wpError ) ) {
			$this->check( 'checkout', $wpError );
		}
	}

	/**
	 * @param null|\WP_User|\WP_Error $wpError
	 * @param string|mixed            $username
	 * @return \WP_User|\WP_Error
	 */
	public function checkLogin_Woo( $wpError, $username ) {
		if ( \is_string( $username ) && is_wp_error( $wpError ) ) {
			$this->check( 'login', $wpError, $username );
		}
		return $wpError;
	}

	/**
	 * @param \WP_Error|mixed $wpError
	 * @param string|mixed    $username
	 * @return \WP_Error|mixed
	 */
	public function checkRegister_Woo( $wpError, $username ) {
		if ( \is_string( $username ) && is_wp_error( $wpError ) ) {
			$this->check( 'register', $wpError, $username );
		}
		return $wpError;
	}

	private function check( string $context, \WP_Error $wpError, ?string $username = null ) :void {
		if ( !$wpError->has_errors() && $this->setAuditAction( $context )->isBotBlockRequired() ) {
			$this->fireEventBlockRegister();
			if ( !empty( $username ) ) {
				$this->setAuditUser( $username );
			}
			$wpError->add( 'shield-user-'.$context, $this->getErrorMessage() );
		}
	}
}