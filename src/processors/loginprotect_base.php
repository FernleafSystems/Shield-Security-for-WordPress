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
				// MemberPress
				add_action( 'mepr-login-form-before-submit', [ $this, 'printLoginFormItems_MePr' ], 100 );
				add_filter( 'mepr-validate-login', [ $this, 'checkReqLogin_MePr' ], 100 );
				// Ultimate Member
				add_action( 'um_after_login_fields', [ $this, 'printFormItems_UltMem' ], 100 );
				add_action( 'um_submit_form_login', [ $this, 'checkReqLogin_UltMem' ], 100 );

				// LearnPress
				add_action( 'learn-press/after-form-login-fields', [ $this, 'printFormItems_LearnPress' ], 100 );
				add_action( 'learn-press/before-checkout-form-login-button', [
					$this,
					'printFormItems_LearnPress'
				], 100 );
				add_filter( 'learn-press/login-validate-field', [ $this, 'checkReqLogin_LearnPress' ], 100 );
			}
		}

		if ( $oMod->isProtectLostPassword() ) {
			add_action( 'lostpassword_form', [ $this, 'printFormItems' ] );
			add_action( 'lostpassword_post', [ $this, 'checkReqLostPassword_Wp' ], 10, 1 );

			if ( $b3rdParty ) {
				// MemberPress
				add_action( 'mepr-forgot-password-form', [ $this, 'printLoginFormItems_MePr' ], 100 );
				add_filter( 'mepr-validate-forgot-password', [ $this, 'checkReqLostPassword_MePr' ], 100 );
				// Ultimate Member
				add_action( 'um_after_password_reset_fields', [ $this, 'printFormItems_UltMem' ], 100 );
				add_action( 'um_submit_form_password_reset', [ $this, 'checkReqLostPassword_UltMem' ], 5, 0 );
			}
		}

		if ( $oMod->isProtectRegister() ) {
			add_action( 'register_form', [ $this, 'printFormItems' ] );
//			add_action( 'register_post', array( $this, 'checkReqRegistration_Wp' ), 10, 1 );
			add_filter( 'registration_errors', [ $this, 'checkReqRegistrationErrors_Wp' ], 10, 2 );

			if ( $b3rdParty ) {
				// A Catch-all:
				// 20180909 - not a bit wise as it breaks anything that doesn't properly display front-end output
//				add_filter( 'wp_pre_insert_user_data', array( $this, 'checkPreUserInsert_Wp' ), 10, 1 );

				add_action( 'bp_before_registration_submit_buttons', [ $this, 'printLoginFormItems_Bp' ], 10 );
				add_action( 'bp_signup_validate', [ $this, 'checkReqRegistration_Bp' ], 10 );

				// MemberPress - Checkout == Registration
				add_action( 'mepr-checkout-before-submit', [ $this, 'printRegisterFormItems_MePr' ], 10 );
				add_filter( 'mepr-validate-signup', [ $this, 'checkReqRegistration_MePr' ], 10, 2 );
				// Paid Member Subscriptions (https://wordpress.org/plugins/paid-member-subscriptions)
				add_action( 'pms_register_form_after_fields', [ $this, 'printFormItems_PaidMemberSubscriptions' ], 100 );
				add_filter( 'pms_register_form_validation', [ $this, 'checkReqReg_PaidMemberSubscriptions' ], 100 );
				// Profile Builder (https://wordpress.org/plugins/profile-builder/)
				add_action( 'wppb_form_before_submit_button', [ $this, 'printLoginFormItems' ], 100 );
				add_filter( 'wppb_output_field_errors_filter', [ $this, 'checkReqReg_ProfileBuilder' ], 100 );
				// Ultimate Member
				add_action( 'um_after_register_fields', [ $this, 'printFormItems_UltMem' ], 100 );
				add_action( 'um_submit_form_register', [ $this, 'checkReqRegistration_UltMem' ], 5, 0 );
				// LearnPress
				add_action( 'learn-press/after-form-register-fields', [ $this, 'printFormItems_LearnPress' ], 100 );
				add_filter( 'learn-press/register-validate-field', [
					$this,
					'checkReqRegistration_LearnPress'
				], 100, 1 );
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
	 * @param string|WP_Error $sFieldNameOrError
	 * @return string|WP_Error
	 */
	public function checkReqLogin_LearnPress( $sFieldNameOrError ) {
		if ( !empty( $sFieldNameOrError ) || !is_wp_error( $sFieldNameOrError ) ) {
			try {
				$this->setActionToAudit( 'learnpress-login' )
					 ->performCheckWithException();
			}
			catch ( \Exception $oE ) {
				$sFieldNameOrError = new \WP_Error( 'shield-fail-login', $oE->getMessage() );
			}
		}
		return $sFieldNameOrError;
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
		catch ( \Exception $oE ) {
			$oUserOrError = $this->giveMeWpError( $oUserOrError );
			$oUserOrError->add( $this->getCon()->prefix( rand() ), $oE->getMessage() );
		}
		return $oUserOrError;
	}

	/**
	 * @param array $aErrors
	 * @return array
	 */
	public function checkReqLogin_MePr( $aErrors ) {
		if ( !empty( $aErrors ) && $this->isMemberPress() ) {
			try {
				$this->setActionToAudit( 'memberpress-login' )
					 ->performCheckWithException();
			}
			catch ( \Exception $oE ) {
				$aErrors[] = $oE->getMessage();
			}
		}
		return $aErrors;
	}

	/**
	 */
	public function checkReqLogin_UltMem() {
		if ( $this->isUltimateMember() ) {
			try {
				$this->setActionToAudit( 'ultimatemember-login' )
					 ->performCheckWithException();
			}
			catch ( \Exception $oE ) {
				\UM()->form()->add_error( 'shield-fail-login', $oE->getMessage() );
			}
		}
	}

	/**
	 * @param \WP_Error $oWpError
	 * @return \WP_Error
	 */
	public function checkReqLostPassword_Wp( $oWpError ) {
		try {
			$this->setUserToAudit( Services::Request()->post( 'user_login', '' ) )
				 ->setActionToAudit( 'reset-password' )
				 ->performCheckWithException();
		}
		catch ( \Exception $oE ) {
			$oWpError = $this->giveMeWpError( $oWpError );
			$oWpError->add( $this->getCon()->prefix( rand() ), $oE->getMessage() );
		}
		return $oWpError;
	}

	/**
	 * @param array $aErrors
	 * @return array
	 */
	public function checkReqLostPassword_MePr( $aErrors ) {
		if ( !empty( $aErrors ) && $this->isMemberPress() ) {
			try {
				$this->setActionToAudit( 'memberpress-lostpassword' )
					 ->performCheckWithException();
			}
			catch ( \Exception $oE ) {
				$aErrors[] = $oE->getMessage();
			}
		}
		return $aErrors;
	}

	/**
	 */
	public function checkReqLostPassword_UltMem() {
		if ( $this->isUltimateMember() ) {
			try {
				$this->setActionToAudit( 'ultimatemember-lostpassword' )
					 ->performCheckWithException();
			}
			catch ( \Exception $oE ) {
				UM()->form()->add_error( 'shield-fail-lostpassword', $oE->getMessage() );
			}
		}
	}

	/**
	 * This is for the request where the User actually enters their new password
	 * @param \WP_Error $oWpError
	 * @return \WP_Error
	 */
	public function checkReqResetPassword_Wp( $oWpError ) {
		try {
			$oReq = Services::Request();
			if ( $oReq->isPost() && is_wp_error( $oWpError ) && empty( $oWpError->errors ) ) {
				list( $sUser, $null ) = explode( ':', wp_unslash( $oReq->cookie( 'wp-resetpass-'.COOKIEHASH, '' ) ), 2 );
				$this->setUserToAudit( $sUser )
					 ->setActionToAudit( 'set-password' )
					 ->performCheckWithException();
			}
		}
		catch ( \Exception $oE ) {
			$oWpError = $this->giveMeWpError( $oWpError );
			$oWpError->add( $this->getCon()->prefix( rand() ), $oE->getMessage() );
		}
		return $oWpError;
	}

	/**
	 * @param array $aData
	 * @return array
	 */
	public function checkPreUserInsert_Wp( $aData ) {
		if ( !Services::WpUsers()->isUserLoggedIn() && Services::Request()->isPost() ) {
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
	 */
	public function checkReqRegistration_Edd() {
		try {
			$this->setActionToAudit( 'edd-register' )
				 ->performCheckWithException();
		}
		catch ( \Exception $oE ) {
			if ( function_exists( 'edd_set_error' ) ) {
				edd_set_error( $this->getCon()->prefix( rand() ), $oE->getMessage() );
			}
		}
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

	/**
	 * Errors are passed about here using an array of strings.
	 * @param string[] $aErrors
	 * @return string[]
	 */
	public function checkReqRegistration_MePr( $aErrors ) {
		if ( !empty( $aErrors ) && $this->isMemberPress() ) {
			try {
				$this->setActionToAudit( 'memberpress-register' )
					 ->performCheckWithException();
			}
			catch ( \Exception $oE ) {
				$aErrors[] = $oE->getMessage();
			}
		}
		return $aErrors;
	}

	/**
	 * @param string|WP_Error $sFieldNameOrError
	 * @return string|WP_Error
	 */
	public function checkReqRegistration_LearnPress( $sFieldNameOrError ) {
		if ( !empty( $sFieldNameOrError ) || !is_wp_error( $sFieldNameOrError ) ) {
			try {
				$this->setActionToAudit( 'learnpress-register' )
					 ->performCheckWithException();
			}
			catch ( \Exception $oE ) {
				$sFieldNameOrError = new \WP_Error( 'shield-fail-register', $oE->getMessage() );
			}
		}
		return $sFieldNameOrError;
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
	 */
	public function checkReqRegistration_UltMem() {
		if ( $this->isUltimateMember() ) {
			try {
				$this->setActionToAudit( 'ultimatemember-register' )
					 ->performCheckWithException();
			}
			catch ( \Exception $oE ) {
				UM()->form()->add_error( 'shield-fail-register', $oE->getMessage() );
			}
		}
	}

	/**
	 * @param \WP_Error $oWpError
	 * @param string    $sUsername
	 * @return \WP_Error
	 */
	public function checkReqRegistrationErrors_Wp( $oWpError, $sUsername ) {
		try {
			$this->setUserToAudit( $sUsername )
				 ->setActionToAudit( 'register' )
				 ->performCheckWithException();
		}
		catch ( \Exception $oE ) {
			$oWpError = $this->giveMeWpError( $oWpError );
			$oWpError->add( $this->getCon()->prefix( rand() ), $oE->getMessage() );
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
	public function printLoginFormItems_MePr() {
		$this->printLoginFormItems();
	}

	/**
	 * @return void
	 */
	public function printFormItems_PaidMemberSubscriptions() {
		$this->printLoginFormItems();
	}

	/**
	 * LearnPress
	 * @return void
	 */
	public function printFormItems_LearnPress() {
		$this->printLoginFormItems();
	}

	/**
	 * Ultimate Member Forms
	 * https://wordpress.org/plugins/ultimate-member/
	 * @return void
	 */
	public function printFormItems_UltMem() {
		$this->printLoginFormItems();
	}

	/**
	 * @return void
	 */
	public function printRegisterFormItems_MePr() {
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
	protected function isMemberPress() {
		return defined( 'MEPR_LIB_PATH' ) || defined( 'MEPR_PLUGIN_NAME' );
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
	 * @return bool
	 */
	protected function isUltimateMember() {
		return function_exists( 'UM' ) && @class_exists( 'UM' ) && method_exists( 'UM', 'form' );
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