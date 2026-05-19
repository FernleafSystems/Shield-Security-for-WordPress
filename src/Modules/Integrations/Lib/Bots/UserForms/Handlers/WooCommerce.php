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
		add_action( 'woocommerce_store_api_cart_errors', fn( $cartErrors = null ) => $this->checkCheckout_WooRestApi( $cartErrors ), 11 );
	}

	/**
	 * Cooldown checks are suppressed for this hook, because it's triggered frequently.
	 * @param \WP_Error|mixed $cartErrors
	 */
	public function checkCheckout_WooRestApi( $cartErrors ) :void {
		if ( is_wp_error( $cartErrors ) ) {
			$wasSuppressed = $this->suppressCooldownCheck;
			$this->suppressCooldownCheck = true;
			try {
				$this->check( 'checkout', $cartErrors );
			}
			finally {
				$this->suppressCooldownCheck = $wasSuppressed;
			}
		}
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
	 * @return null|\WP_User|\WP_Error
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
	 */
	public function checkRegister_Woo( $wpError, $username ) {
		if ( \is_string( $username ) && is_wp_error( $wpError ) ) {
			$this->check( 'register', $wpError, $username );
		}
		return $wpError;
	}

	private function check( string $context, \WP_Error $wpError, ?string $username = null ) :void {
		$this->setAuditAction( $context );
		if ( !empty( $username ) ) {
			$this->setAuditUser( $username );
		}
		else {
			$this->setAuditUser( '' );
		}

		if ( !$wpError->has_errors() && $this->isBotBlockRequired() ) {
			$this->fireBlockEventForContext( $context );
			$wpError->add( 'shield-user-'.$context, $this->getErrorMessage() );
		}
	}

	private function fireBlockEventForContext( string $context ) :void {
		switch ( $context ) {
			case 'login':
				$this->fireEventBlockLogin();
				break;

			case 'checkout':
				$this->fireEventBlockCheckout();
				break;

			case 'register':
				$this->fireEventBlockRegister();
				break;
		}
	}

	public function isCoolDownBlockRequired() :bool {
		return !$this->suppressCooldownCheck && parent::isCoolDownBlockRequired();
	}
}
