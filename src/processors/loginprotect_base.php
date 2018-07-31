<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_Base', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

abstract class ICWP_WPSF_Processor_LoginProtect_Base extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var string
	 */
	private $sActionToAudit;

	/**
	 * @var string
	 */
	private $sUserToAudit;

	/**
	 * @var bool
	 */
	private $bFactorTested;

	/**
	 */
	public function run() {
		$this->setFactorTested( false );
		add_action( 'init', array( $this, 'addHooks' ) );
	}

	/**
	 * Hooked to INIT so we can test for logged-in. We don't process for logged-in users.
	 */
	public function addHooks() {
		if ( $this->loadWpUsers()->isUserLoggedIn() ) {
			return;
		}

		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		$b3rdParty = $oFO->getIfSupport3rdParty();

		if ( $oFO->isProtectLogin() ) {
			// We give it a priority of 10 so that we can jump in before WordPress does its own validation.
			add_filter( 'authenticate', array( $this, 'checkReqLogin_Wp' ), 10, 3 );

			add_action( 'login_form', array( $this, 'printLoginFormItems' ), 100 );
			add_filter( 'login_form_middle', array( $this, 'provideLoginFormItems' ), 100 );

			if ( $b3rdParty ) {
				add_action( 'edd_login_fields_after', array( $this, 'printLoginFormItems' ), 10 );

				add_action( 'woocommerce_login_form', array( $this, 'printLoginFormItems_Woo' ), 100 );
				add_filter( 'woocommerce_process_login_errors', array( $this, 'checkReqLogin_Woo' ), 10, 2 );
			}
		}

		if ( $oFO->isProtectLostPassword() ) {
			add_action( 'lostpassword_form', array( $this, 'printLoginFormItems' ) );
			add_action( 'lostpassword_post', array( $this, 'checkReqLostPassword_Wp' ), 10, 1 );

			if ( $b3rdParty ) {
				add_action( 'woocommerce_lostpassword_form', array( $this, 'printLoginFormItems' ), 10 );
			}
		}

		if ( $oFO->isProtectRegister() ) {
			add_action( 'register_form', array( $this, 'printLoginFormItems' ) );
//			add_action( 'register_post', array( $this, 'checkReqRegistration_Wp' ), 10, 1 );
			add_filter( 'registration_errors', array( $this, 'checkReqRegistrationErrors_Wp' ), 10, 2 );

			if ( $b3rdParty ) {
				// A bit of a catch-all:
				add_filter( 'wp_pre_insert_user_data', array( $this, 'checkPreUserInsert_Wp' ), 10, 1 );

				add_action( 'bp_before_registration_submit_buttons', array( $this, 'printLoginFormItems_Bp' ), 10 );
				add_action( 'bp_signup_validate', array( $this, 'checkReqRegistration_Bp' ), 10 );

				add_action( 'edd_register_form_fields_before_submit', array( $this, 'printLoginFormItems' ), 10 );
				add_action( 'edd_process_register_form', array( $this, 'checkReqRegistration_Edd' ), 10 );

				add_action( 'woocommerce_register_form', array( $this, 'printRegisterFormItems_Woo' ), 10 );
				add_filter( 'woocommerce_process_registration_errors', array( $this, 'checkReqRegistration_Woo' ), 10, 2 );
			}
		}

		if ( $b3rdParty && $oFO->isProtect( 'checkout_woo' ) ) {
			add_action( 'woocommerce_after_checkout_registration_form', array( $this, 'printRegistrationFormItems_Woo' ), 10 );
			add_action( 'woocommerce_after_checkout_validation', array( $this, 'checkReqCheckout_Woo' ), 10, 2 );
		}
	}

	/**
	 * @throws Exception
	 */
	abstract protected function performCheckWithException();

	/**
	 */
	protected function performCheckWithDie() {
		try {
			$this->performCheckWithException();
		}
		catch ( Exception $oE ) {
			$this->loadWp()->wpDie( $oE->getMessage() );
		}
	}

	/**
	 * @param WP_Error $oWpError
	 * @param string   $sUsername
	 * @return WP_Error
	 */
	public function checkReqLogin_Woo( $oWpError, $sUsername ) {
		try {
			$this->setUserToAudit( $sUsername )
				 ->setActionToAudit( 'woo-login' )
				 ->performCheckWithException();
		}
		catch ( Exception $oE ) {
			$oWpError = $this->giveMeWpError( $oWpError );
			$oWpError->add( $this->prefix( rand() ), $oE->getMessage() );
		}
		return $oWpError;
	}

	/**
	 * Should be a filter added to WordPress's "authenticate" filter, but before WordPress performs
	 * it's own authentication (theirs is priority 30, so we could go in at around 20).
	 * @param null|WP_User|WP_Error $oUserOrError
	 * @param string                $sUsername
	 * @param string                $sPassword
	 * @return WP_User|WP_Error
	 */
	public function checkReqLogin_Wp( $oUserOrError, $sUsername, $sPassword ) {
		try {
			if ( !is_wp_error( $oUserOrError ) && !empty( $sUsername ) && !empty( $sPassword ) ) {
				$this->setUserToAudit( $sUsername )
					 ->setActionToAudit( 'login' )
					 ->performCheckWithException();
			}
		}
		catch ( Exception $oE ) {
			$oUserOrError = $this->giveMeWpError( $oUserOrError );
			$oUserOrError->add( $this->prefix( rand() ), $oE->getMessage() );
		}
		return $oUserOrError;
	}

	/**
	 * @param WP_Error $oWpError
	 * @return WP_Error
	 */
	public function checkReqLostPassword_Wp( $oWpError ) {
		try {
			$this->setUserToAudit( $this->loadDP()->post( 'user_login', '' ) )
				 ->setActionToAudit( 'reset-password' )
				 ->performCheckWithException();
		}
		catch ( Exception $oE ) {
			$oWpError = $this->giveMeWpError( $oWpError );
			$oWpError->add( $this->prefix( rand() ), $oE->getMessage() );
		}
		return $oWpError;
	}

	/**
	 * @param array $aData
	 * @return array
	 */
	public function checkPreUserInsert_Wp( $aData ) {
		if ( !$this->loadWpUsers()->isUserLoggedIn() && $this->loadDP()->isMethodPost() ) {
			$this->setActionToAudit( 'register' )
				 ->performCheckWithDie();
		}
		return $aData;
	}

	/**
	 * @param string $sUsername
	 */
	public function checkReqRegistration_Wp( $sUsername ) {
		return $this->setUserToAudit( $sUsername )
					->setActionToAudit( 'register' )
					->performCheckWithDie();
	}

	/**
	 * see class-wc-checkout.php
	 * @param WP_Error $oWpError
	 * @param array    $aPostedData
	 * @return WP_Error
	 */
	public function checkReqCheckout_Woo( $aPostedData, $oWpError ) {
		try {
			$this->setActionToAudit( 'woo-checkout' )
				 ->performCheckWithException();
		}
		catch ( Exception $oE ) {
			$oWpError = $this->giveMeWpError( $oWpError );
			$oWpError->add( $this->prefix( rand() ), $oE->getMessage() );
		}
		return $oWpError;
	}

	/**
	 */
	public function checkReqRegistration_Edd() {
		try {
			$this->setActionToAudit( 'edd-register' )
				 ->performCheckWithException();
		}
		catch ( Exception $oE ) {
			if ( function_exists( 'edd_set_error' ) ) {
				edd_set_error( $this->prefix( rand() ), $oE->getMessage() );
			}
		}
	}

	/**
	 * @param WP_Error $oWpError
	 * @param string   $sUsername
	 * @return WP_Error
	 */
	public function checkReqRegistration_Woo( $oWpError, $sUsername ) {
		try {
			$this->setUserToAudit( $sUsername )
				 ->setActionToAudit( 'woo-register' )
				 ->performCheckWithException();
		}
		catch ( Exception $oE ) {
			$oWpError = $this->giveMeWpError( $oWpError );
			$oWpError->add( $this->prefix( rand() ), $oE->getMessage() );
		}
		return $oWpError;
	}

	/**
	 * @param WP_Error $oWpError
	 * @param  string  $sUsername
	 * @return WP_Error
	 */
	public function checkReqRegistrationErrors_Wp( $oWpError, $sUsername ) {
		try {
			$this->setUserToAudit( $sUsername )
				 ->setActionToAudit( 'register' )
				 ->performCheckWithException();
		}
		catch ( Exception $oE ) {
			$oWpError = $this->giveMeWpError( $oWpError );
			$oWpError->add( $this->prefix( rand() ), $oE->getMessage() );
		}
		return $oWpError;
	}

	/**
	 * @return bool
	 */
	public function checkReqRegistration_Bp() {
		return $this->performCheckWithDie();
	}

	/**
	 * @return string
	 */
	protected function buildLoginFormItems() {
		return '';
	}

	/**
	 * @return void
	 */
	public function printLoginFormItems() {
		echo $this->buildLoginFormItems();
	}

	/**
	 * @return void
	 */
	public function printLoginFormItems_Woo() {
		$this->printLoginFormItems();
	}

	/**
	 * @return void
	 */
	public function printRegisterFormItems_Woo() {
		$this->printLoginFormItems();
	}

	/**
	 * see form-billing.php
	 * @param WP_Checkout $oCheckout
	 * @return void
	 */
	public function printRegistrationFormItems_Woo( $oCheckout ) {
		if ( $oCheckout instanceof WC_Checkout && $oCheckout->is_registration_enabled() ) {
			$this->printLoginFormItems();
		}
	}

	/**
	 * @return void
	 */
	public function printLoginFormItems_Bp() {
		$this->printLoginFormItems();
	}

	/**
	 * @return string
	 */
	public function provideLoginFormItems() {
		return $this->buildLoginFormItems();
	}

	protected function setLoginAsFailed( $sStatToIncrement ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		$oFO->setOptInsightsAt( 'last_login_block_at' );

		remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );  // wp-includes/user.php
		remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );  // wp-includes/user.php

		$this->doStatIncrement( $sStatToIncrement );
		$this->setIpTransgressed(); // We now black mark this IP
	}

	/**
	 * @param WP_Error $oMaybeWpError
	 * @return WP_Error
	 */
	protected function giveMeWpError( $oMaybeWpError ) {
		return is_wp_error( $oMaybeWpError ) ? $oMaybeWpError : new WP_Error();
	}

	/**
	 * @return string
	 */
	protected function getActionToAudit() {
		return empty( $this->sActionToAudit ) ? 'unknown-action' : $this->sActionToAudit;
	}

	/**
	 * @return string
	 */
	protected function getUserToAudit() {
		return empty( $this->sUserToAudit ) ? 'unknown' : $this->sUserToAudit;
	}

	/**
	 * @return bool
	 */
	public function isFactorTested() {
		return (bool)$this->bFactorTested;
	}

	/**
	 * @param string $sActionToAudit
	 * @return $this
	 */
	protected function setActionToAudit( $sActionToAudit ) {
		$this->sActionToAudit = $sActionToAudit;
		return $this;
	}

	/**
	 * @param bool $bFactorTested
	 * @return $this
	 */
	public function setFactorTested( $bFactorTested ) {
		$this->bFactorTested = $bFactorTested;
		return $this;
	}

	/**
	 * @param string $sUserToAudit
	 * @return $this
	 */
	protected function setUserToAudit( $sUserToAudit ) {
		$this->sUserToAudit = sanitize_user( $sUserToAudit );
		return $this;
	}
}