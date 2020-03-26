<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

abstract class ICWP_WPSF_Processor_LoginProtect_Base extends Modules\BaseShield\ShieldProcessor {

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
		add_action( 'init', [ $this, 'addHooks' ], -100 );
	}

	/**
	 * Hooked to INIT so we can test for logged-in. We don't process for logged-in users.
	 */
	public function addHooks() {
		if ( Services::WpUsers()->isUserLoggedIn() ) {
			return;
		}

		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		$b3rdParty = $oMod->getIfSupport3rdParty();

		if ( $oMod->isProtectLogin() ) {

			if ( $b3rdParty ) {
				add_action( 'woocommerce_login_form', [ $this, 'printLoginFormItems_Woo' ], 100 );
				add_filter( 'woocommerce_process_login_errors', [ $this, 'checkReqLogin_Woo' ], 10, 2 );
			}
		}

		if ( $oMod->isProtectLostPassword() ) {

			if ( $b3rdParty ) {
				add_action( 'woocommerce_lostpassword_form', [ $this, 'printFormItems' ], 10 );
			}
		}

		if ( $oMod->isProtectRegister() ) {

			if ( $b3rdParty ) {
				// A Catch-all:
				// 20180909 - not a bit wise as it breaks anything that doesn't properly display front-end output
//				add_filter( 'wp_pre_insert_user_data', array( $this, 'checkPreUserInsert_Wp' ), 10, 1 );

				add_action( 'woocommerce_register_form', [ $this, 'printRegisterFormItems_Woo' ], 10 );
				add_action( 'woocommerce_after_checkout_registration_form', [
					$this,
					'printRegistrationFormItems_Woo'
				], 10 );
				add_filter( 'woocommerce_process_registration_errors', [ $this, 'checkReqRegistration_Woo' ], 10, 2 );

				// Paid Member Subscriptions (https://wordpress.org/plugins/paid-member-subscriptions)
				add_action( 'pms_register_form_after_fields', [ $this, 'printFormItems_PaidMemberSubscriptions' ], 100 );
				add_filter( 'pms_register_form_validation', [ $this, 'checkReqReg_PaidMemberSubscriptions' ], 100 );
				// Profile Builder (https://wordpress.org/plugins/profile-builder/)
				add_action( 'wppb_form_before_submit_button', [ $this, 'printLoginFormItems' ], 100 );
				add_filter( 'wppb_output_field_errors_filter', [ $this, 'checkReqReg_ProfileBuilder' ], 100 );
			}
		}

		if ( $b3rdParty && $oMod->isProtect( 'checkout_woo' ) ) {
			add_action( 'woocommerce_after_checkout_registration_form', [
				$this,
				'printRegistrationFormItems_Woo'
			], 10 );
			add_action( 'woocommerce_after_checkout_validation', [ $this, 'checkReqCheckout_Woo' ], 10, 2 );
		}
	}

	/**
	 * @throws \Exception
	 */
	abstract protected function performCheckWithException();

	/**
	 */
	protected function performCheckWithDie() {
		try {
			$this->performCheckWithException();
		}
		catch ( \Exception $oE ) {
			Services::WpGeneral()->wpDie( $oE->getMessage() );
		}
	}

	/**
	 * @param \WP_Error $oWpError
	 * @param string    $sUsername
	 * @return \WP_Error
	 */
	public function checkReqLogin_Woo( $oWpError, $sUsername ) {
		try {
			$this->setUserToAudit( $sUsername )
				 ->setActionToAudit( 'woo-login' )
				 ->performCheckWithException();
		}
		catch ( \Exception $oE ) {
			$oWpError = $this->giveMeWpError( $oWpError );
			$oWpError->add( $this->getCon()->prefix( rand() ), $oE->getMessage() );
		}
		return $oWpError;
	}

	/**
	 * see class-wc-checkout.php
	 * @param \WP_Error $oWpError
	 * @param array     $aPostedData
	 * @return \WP_Error
	 */
	public function checkReqCheckout_Woo( $aPostedData, $oWpError ) {
		try {
			$this->setActionToAudit( 'woo-checkout' )
				 ->performCheckWithException();
		}
		catch ( \Exception $oE ) {
			$oWpError = $this->giveMeWpError( $oWpError );
			$oWpError->add( $this->getCon()->prefix( rand() ), $oE->getMessage() );
		}
		return $oWpError;
	}

	/**
	 * @param \WP_Error $oWpError
	 * @param string    $sUsername
	 * @return \WP_Error
	 */
	public function checkReqRegistration_Woo( $oWpError, $sUsername ) {
		try {
			$this->setUserToAudit( $sUsername )
				 ->setActionToAudit( 'woo-register' )
				 ->performCheckWithException();
		}
		catch ( \Exception $oE ) {
			$oWpError = $this->giveMeWpError( $oWpError );
			$oWpError->add( $this->getCon()->prefix( rand() ), $oE->getMessage() );
		}
		return $oWpError;
	}

	public function checkReqReg_PaidMemberSubscriptions() {
		if ( $this->isPaidMemberSubscriptions() ) {
			try {
				$this->setActionToAudit( 'paidmembersubscriptions-register' )
					 ->performCheckWithException();
			}
			catch ( \Exception $oE ) {
				\pms_errors()->add( 'shield-fail-register', $oE->getMessage() );
			}
		}
	}

	/**
	 * @param array $aErrors
	 * @return array
	 */
	public function checkReqReg_ProfileBuilder( $aErrors ) {
		if ( $this->isProfileBuilder() ) {
			try {
				$this->setActionToAudit( 'profilebuilder-register' )
					 ->performCheckWithException();
			}
			catch ( \Exception $oE ) {
				$aErrors[ 'shield-fail-register' ] =
					'<span class="wppb-form-error">Bot</span>';
			}
		}
		return $aErrors;
	}

	/**
	 * @return string
	 */
	protected function buildFormItems() {
		return '';
	}

	/**
	 * @return string
	 */
	protected function buildLoginFormItems() {
		$sItems = $this->buildFormItems();
		if ( !empty( $sItems ) ) {
			$this->incrementLoginFormPrintCount();
		}
		return $sItems;
	}

	/**
	 * @return void
	 */
	public function printFormItems() {
		echo $this->buildFormItems();
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
	public function printFormItems_PaidMemberSubscriptions() {
		$this->printLoginFormItems();
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
		$this->printFormItems();
	}

	/**
	 * see form-billing.php
	 * @param \WC_Checkout $oCheckout
	 * @return void
	 */
	public function printRegistrationFormItems_Woo( $oCheckout ) {
		if ( $oCheckout instanceof \WC_Checkout && $oCheckout->is_registration_enabled() ) {
			$this->printFormItems();
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

	/**
	 * @return $this
	 */
	protected function processFailure() {
		remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );  // wp-includes/user.php
		remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );  // wp-includes/user.php
		$this->getCon()->fireEvent( 'login_block' );
		return $this;
	}

	/**
	 * @param \WP_Error $oMaybeWpError
	 * @return \WP_Error
	 */
	protected function giveMeWpError( $oMaybeWpError ) {
		return is_wp_error( $oMaybeWpError ) ? $oMaybeWpError : new \WP_Error();
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
	 * @return bool
	 */
	protected function isPaidMemberSubscriptions() {
		return @class_exists( 'Paid_Member_Subscriptions' ) && function_exists( 'pms_errors' );
	}

	/**
	 * @return bool
	 */
	protected function isProfileBuilder() {
		return defined( 'PROFILE_BUILDER_VERSION' );
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

	/**
	 * @return bool
	 * @deprecated 9.0
	 */
	protected function canPrintLoginFormElement() {
		return true;
	}

	/**
	 * @return $this
	 * @deprecated 9.0
	 */
	public function incrementLoginFormPrintCount() {
		return $this;
	}

	/**
	 * @return int
	 * @deprecated 9.0
	 */
	protected function getLoginFormCountMax() {
		return 1;
	}

	/**
	 * @return int
	 * @deprecated 9.0
	 */
	protected function getLoginFormPrintCount() {
		return 0;
	}
}