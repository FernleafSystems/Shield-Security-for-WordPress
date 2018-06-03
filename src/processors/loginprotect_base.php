<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_Base', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

abstract class ICWP_WPSF_Processor_LoginProtect_Base extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		// We give it a priority of 10 so that we can jump in before WordPress does its own validation.
		add_filter( 'authenticate', array( $this, 'checkReqLogin_Wp' ), 10, 2 );

		add_action( 'login_form', array( $this, 'printLoginFormItems' ), 100 );
		add_filter( 'login_form_middle', array( $this, 'provideLoginFormItems' ), 100 );

		$b3rdParty = $oFO->getIfSupport3rdParty();

		if ( $b3rdParty ) {
			add_action( 'edd_login_fields_after', array( $this, 'printLoginFormItems' ), 10 );
			add_action( 'woocommerce_login_form', array( $this, 'printLoginFormItems_Woo' ), 100 );
		}

		// apply to user registrations if set to do so.
		if ( $oFO->getIsCheckingUserRegistrations() ) {

			// Print form supplements:
			add_action( 'register_form', array( $this, 'printLoginFormItems' ) );
			add_action( 'lostpassword_form', array( $this, 'printLoginFormItems' ) );

			// Check form submissions:
			add_action( 'lostpassword_post', array( $this, 'checkReqLostPassword_Wp' ), 10, 1 );

			add_action( 'register_post', array( $this, 'checkReqRegistration_Wp' ), 10, 1 );
			add_filter( 'registration_errors', array( $this, 'checkReqRegistrationErrors_Wp' ), 10, 2 );

			if ( $b3rdParty ) {
				add_action( 'bp_before_registration_submit_buttons', array( $this, 'printLoginFormItems_Bp' ), 10 );
				add_action( 'bp_signup_validate', array( $this, 'checkReqRegistration_Bp' ), 10 );

				// Easy Digital Downloads
				add_action( 'edd_register_form_fields_before_submit', array( $this, 'printLoginFormItems' ), 10 );

				// Woocommerce actions
				add_action( 'woocommerce_register_form', array( $this, 'printLoginFormItems' ), 10 );
				add_action( 'woocommerce_lostpassword_form', array( $this, 'printLoginFormItems' ), 10 );
				add_filter( 'woocommerce_process_registration_errors', array( $this, 'checkReqRegistration_Woo' ), 10, 2 );
			}
		}
	}

	/**
	 * @throws Exception
	 */
	protected function performCheckWithException() {
	}

	/**
	 * @return bool
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
	 * Should be a filter added to WordPress's "authenticate" filter, but before WordPress performs
	 * it's own authentication (theirs is priority 30, so we could go in at around 20).
	 * @param null|WP_User|WP_Error $oUserOrError
	 * @param string                $sUsername
	 * @return WP_User|WP_Error
	 */
	public function checkReqLogin_Wp( $oUserOrError, $sUsername ) {
		if ( $this->loadWp()->isRequestUserLogin() ) {

			try {
				$this->performCheckWithException();
			}
			catch ( Exception $oE ) {
				if ( !is_wp_error( $oUserOrError ) ) {
					$oUserOrError = new WP_Error();
				}
				$oUserOrError->add( $this->prefix( rand() ), $oE->getMessage() );
			}
		}
		return $oUserOrError;
	}

	/**
	 * @param WP_Error $oWpError
	 * @return WP_Error
	 */
	public function checkReqLostPassword_Wp( $oWpError ) {
		$sSanitizedUsername = sanitize_user( $this->loadDP()->post( 'user_login', '' ) );
		// TODO: $sSanitizedUsername, 'reset-password'
		try {
			$this->performCheckWithException();
		}
		catch ( Exception $oE ) {
			if ( !is_wp_error( $oWpError ) ) {
				$oWpError = new WP_Error();
			}
			$oWpError->add( $this->prefix( rand() ), $oE->getMessage() );
		}
		return $oWpError;
	}

	/**
	 * @param string $sSanitizedUsername
	 * @return true
	 */
	public function checkReqRegistration_Wp( $sSanitizedUsername ) {
		//TODO: $sSanitizedUsername, 'register'
		return $this->performCheckWithDie();
	}

	/**
	 * @param WP_Error $oWpError
	 * @param string   $sUsername
	 * @return WP_Error
	 */
	public function checkReqRegistration_Woo( $oWpError, $sUsername ) {
		//( sanitize_user( $sUsername ), 'woo-register' )
		try {
			$this->performCheckWithException();
		}
		catch ( Exception $oE ) {
			if ( !is_wp_error( $oWpError ) ) {
				$oWpError = new WP_Error();
			}
			$oWpError->add( $this->prefix( rand() ), $oE->getMessage() );
		}
		return $oWpError;
	}

	/**
	 * @param WP_Error $oWpError
	 * @return WP_Error
	 */
	public function checkReqRegistrationErrors_Wp( $oWpError ) {
		try {
			$this->performCheckWithException();
		}
		catch ( Exception $oE ) {
			if ( !is_wp_error( $oWpError ) ) {
				$oWpError = new WP_Error();
			}
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
}