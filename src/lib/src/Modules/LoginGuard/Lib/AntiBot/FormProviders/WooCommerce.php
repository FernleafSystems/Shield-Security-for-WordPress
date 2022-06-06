<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

class WooCommerce extends BaseFormProvider {

	public function run() {
		parent::run();
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isProtect( 'checkout_woo' ) ) {
			$this->woocheckout();
		}
	}

	protected function login() {
		add_action( 'woocommerce_login_form', [ $this, 'formInsertsPrint_WooLogin' ], 100 );
		add_filter( 'woocommerce_process_login_errors', [ $this, 'checkLogin' ], 10, 2 );
		add_filter( 'authenticate', [ $this, 'checkLogin' ], 10, 3 );
	}

	protected function register() {
		add_action( 'woocommerce_register_form', [ $this, 'formInsertsPrint_WooRegister' ] );
		add_action( $this->getCheckoutHookLocation(), [ $this, 'formInsertsPrintCheckout' ] );
		add_filter( 'woocommerce_process_registration_errors', [ $this, 'checkRegister' ], 10, 2 );
	}

	protected function lostpassword() {
		add_action( 'woocommerce_lostpassword_form', [ $this, 'printFormInsert' ] );
	}

	protected function woocheckout() {
		add_action( $this->getCheckoutHookLocation(), [ $this, 'formInsertsPrintCheckout' ] );
		add_action( 'woocommerce_after_checkout_validation', [ $this, 'checkCheckout' ], 10, 2 );
	}

	private function getCheckoutHookLocation() :string {
		return apply_filters(
			'shield/woocommerce_checkout_hook_location',
			'woocommerce_after_checkout_registration_form'
		);
	}

	/**
	 * @return void
	 */
	public function formInsertsPrint_WooLogin() {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$sInserts = $this->buildFormInsert();
		if ( $mod->getCaptchaCfg()->invisible ) {
			$sInserts .= '<input type="hidden" name="login" value="Log in" />';
		}
		echo $sInserts;
	}

	/**
	 * @return void
	 */
	public function formInsertsPrint_WooRegister() {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$sInserts = $this->buildFormInsert();
		if ( $mod->getCaptchaCfg()->invisible ) {
			$sInserts .= '<input type="hidden" name="register" value="Register" />';
		}
		echo $sInserts;
	}

	/**
	 * see form-billing.php
	 * @param \WC_Checkout $oCheckout
	 * @return void
	 */
	public function formInsertsPrintCheckout( $oCheckout ) {
		if ( $oCheckout instanceof \WC_Checkout && $oCheckout->is_registration_enabled() ) {
			$this->printFormInsert();
		}
	}

	/**
	 * Should be a filter added to WordPress's "authenticate" filter, but before WordPress performs
	 * it's own authentication (theirs is priority 30, so we could go in at around 20).
	 * @param null|\WP_User|\WP_Error $userOrError
	 * @param string                  $sUsername
	 * @return \WP_User|\WP_Error
	 */
	public function checkLogin( $userOrError, $sUsername ) {
		try {
			if ( !is_wp_error( $userOrError ) && !empty( $sUsername ) ) {
				$this->setUserToAudit( $sUsername )
					 ->setActionToAudit( 'woo-register' )
					 ->checkProviders();
			}
		}
		catch ( \Exception $e ) {
			$userOrError = $this->giveMeWpError( $userOrError );
			$userOrError->add( $this->getCon()->prefix( uniqid() ), $e->getMessage() );
		}
		return $userOrError;
	}

	/**
	 * @param \WP_Error $wpError
	 * @param string    $username
	 * @return \WP_Error
	 */
	public function checkRegister( $wpError, $username ) {
		try {
			$this->setUserToAudit( $username )
				 ->setActionToAudit( 'woo-register' )
				 ->checkProviders();
		}
		catch ( \Exception $e ) {
			$wpError = $this->giveMeWpError( $wpError );
			$wpError->add( $this->getCon()->prefix( uniqid() ), $e->getMessage() );
		}
		return $wpError;
	}

	/**
	 * see class-wc-checkout.php
	 * @param \WP_Error $wpError
	 * @param array     $aPostedData
	 * @return \WP_Error
	 */
	public function checkCheckout( $aPostedData, $wpError ) {
		try {
			$this->setActionToAudit( 'woo-checkout' )
				 ->checkProviders();
		}
		catch ( \Exception $e ) {
			$wpError = $this->giveMeWpError( $wpError );
			$wpError->add( $this->getCon()->prefix( uniqid() ), $e->getMessage() );
		}
		return $wpError;
	}
}