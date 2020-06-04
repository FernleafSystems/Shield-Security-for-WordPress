<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;
use u2flib_server\RegisterRequest;
use u2flib_server\SignRequest;

class U2F extends BaseProvider {

	const SLUG = 'u2f';

	/**
	 * @var RegisterRequest
	 */
	private $oWorkingRegistration;

	public function setup() {
		add_action( 'admin_enqueue_scripts', function ( $sHook ) {
			if ( in_array( $sHook, [ 'profile.php', ] ) && Services::WpUsers()->isUserLoggedIn() ) {
				$this->enqueueAdminU2f();
			}
		} );
	}

	private function enqueueAdminU2f() {
		$aDeps = [];
		foreach ( [ 'u2f-bundle', 'shield-u2f-admin' ] as $sScript ) {
			wp_enqueue_script(
				$this->getCon()->prefix( $sScript ),
				$this->getCon()->getPluginUrl_Js( $sScript ),
				$aDeps
			);
			$aDeps[] = $this->getCon()->prefix( $sScript );
		}

		$oUser = Services::WpUsers()->getCurrentWpUser();
		$bValidated = $this->hasValidatedProfile( $oUser );
		try {
			wp_localize_script(
				$this->getCon()->prefix( 'shield-u2f-admin' ),
				'icwp_wpsf_vars_u2f',
				[
					'registration' => $bValidated ? [] : json_decode( $this->resetSecret( $oUser ) ),
					'flags'        => [
						'is_validated' => $bValidated
					],
					'strings'      => [
						'not_supported' => __( 'U2F Security Key registration is not supported in this browser', 'wp-simple-firewall' ),
						'failed'        => __( 'Key registration failed.', 'wp-simple-firewall' )
										   .' '.__( 'Please retry or refresh the page.', 'wp-simple-firewall' ),
						'do_save'       => __( 'Key registration was successful.', 'wp-simple-firewall' )
										   .' '.__( 'Please now save your profile settings.', 'wp-simple-firewall' )
					]
				]
			);
		}
		catch ( \Exception $oE ) {
		}
	}

	/**
	 * @return array
	 */
	public function getFormField() {
		$oUser = Services::WpUsers()->getCurrentWpUser();

		$aFieldData = [];
		try {
			$oRegistration = json_decode( $this->getSecret( $oUser ) );
			/** @var SignRequest[] $aSignReqs */
			$aSignReqs = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )
				->getAuthenticateData( [ $oRegistration ] );

			if ( empty( $aSignReqs ) ) {
				throw new \Exception( 'No signature requests could be created' );
			}
			$oSign = array_pop( $aSignReqs );
			$aFieldData = [
				'name'        => $this->getLoginFormParameter(),
				'type'        => 'hidden',
				'value'       => '',
				'placeholder' => '',
				'text'        => '',
				'help_link'   => 'https://shsec.io/3t',
				'datas'       => [
					'req'       => json_encode( $oSign ),
					'version'   => $oSign->version,
					'challenge' => $oSign->challenge,
					// note these keys are different. The JS breaks with the .data() otherwise
					'handle'    => $oSign->keyHandle,
					'app_id'    => $oSign->appId,
				]
			];
		}
		catch ( \Exception $oE ) {
		}

		return $aFieldData;
	}

	/**
	 * @param \WP_User $oUser
	 * @return RegisterRequest
	 * @throws \u2flib_server\Error
	 */
	private function getU2fRegistration( \WP_User $oUser ) {
		if ( !isset( $this->oWorkingRegistration ) ) {
			// TODO: support multiple signatures
			list( $this->oWorkingRegistration, $signatures ) = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )->getRegisterData( [] );
		}
		return $this->oWorkingRegistration;
	}

	/**
	 * @return string
	 */
	private function getU2fAppID() {
		$aParts = wp_parse_url( Services::WpGeneral()->getHomeUrl() );
		return sprintf( 'https://%s%s', $aParts[ 'host' ], empty( $aParts[ 'port' ] ) ? '' : ':'.$aParts[ 'port' ] );
	}

	/**
	 * @param \WP_User $oUser
	 * @return string
	 */
	protected function genNewSecret( \WP_User $oUser ) {
		try {
			return json_encode( (object)$this->getU2fRegistration( $oUser ) );
		}
		catch ( \Exception $oE ) {
			return '';
		}
	}

	/**
	 * @inheritDoc
	 */
	public function renderUserProfileOptions( \WP_User $oUser ) {

		$bValidated = $this->hasValidatedProfile( $oUser );

		$aData = [
			'strings' => [
				'title'           => __( 'U2F', 'wp-simple-firewall' ),
				'button_reg_key'  => __( 'Register A New U2F Security Key', 'wp-simple-firewall' ),
				'prompt'          => __( 'Click To Start U2F Security Registration.', 'wp-simple-firewall' ),
				'check_to_delete' => __( 'Check the box to delete your existing U2F key registration.', 'wp-simple-firewall' ),
			],
			'flags'   => [
				'is_validated' => $bValidated
			],
		];

		return $this->getMod()
					->renderTemplate(
						'/snippets/user/profile/mfa/mfa_u2f.twig',
						Services::DataManipulation()->mergeArraysRecursive( $this->getCommonData( $oUser ), $aData ),
						true
					);
	}

	/**
	 * @inheritDoc
	 */
	public function renderUserEditProfileOptions( \WP_User $oUser ) {
		// Allow no actions to be taken on other user profiles
	}

	/**
	 * @inheritDoc
	 */
	public function handleUserProfileSubmit( \WP_User $oUser ) {
		$bError = false;
		$sMsg = null;

		$sU2fResponse = Services::Request()->post( 'icwp_new_u2f_response' );
		if ( !$this->hasValidatedProfile( $oUser ) && !empty( $sU2fResponse ) ) {
			try {
				$aReg = json_decode( $this->getSecret( $oUser ), true );
				$oRegistration = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )->doRegister(
					new RegisterRequest( $aReg[ 'challenge' ], $aReg[ 'appId' ] ),
					json_decode( $sU2fResponse )
				);

				$this->setSecret( $oUser, json_encode( get_object_vars( $oRegistration ) ) )
					 ->setProfileValidated( $oUser );

				$sMsg = __( 'U2F Key has been registered successfully on your profile.', 'wp-simple-firewall' );
			}
			catch ( \Exception $oE ) {
				$bError = true;
				$sMsg = sprintf( __( 'U2F Key registration failed with the following error: %s', 'wp-simple-firewall' ),
					$oE->getMessage() );
			}
		}
		elseif ( Services::Request()->post( 'icwp_u2f_key_delete' ) === 'Y' ) {
			$this->deleteSecret( $oUser )
				 ->setProfileValidated( $oUser, false );
			$sMsg = __( 'Registered U2F Key has been removed from your profile.', 'wp-simple-firewall' );
		}

		if ( !empty( $sMsg ) ) {
			$this->getMod()->setFlashAdminNotice( $sMsg, $bError );
		}
	}

	/**
	 * @param \WP_User $oUser
	 * @param string   $sOtpCode
	 * @return bool
	 */
	protected function processOtp( $oUser, $sOtpCode ) {
		return $this->validateU2F( $oUser, $sOtpCode );
	}

	/**
	 * @param \WP_User $oUser
	 * @param string   $sOtpCode
	 * @return bool
	 */
	private function validateU2F( $oUser, $sOtpCode ) {
		try {
			$oReq = Services::Request();

			// Recreate the signing/authenticate request from the form submission.
			$oSign = new SignRequest();
			$oSign->version = $oReq->post( 'version' );
			$oSign->appId = $oReq->post( 'appId' );
			$oSign->challenge = $oReq->post( 'challenge' );
			$oSign->keyHandle = $oReq->post( 'keyHandle' );

			$oRegistration = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )
				->doAuthenticate(
					[ $oSign ],
					[ json_decode( $this->getSecret( $oUser ) ) ],
					json_decode( $sOtpCode )
				);

			// We "update" the registration as there is a counter to track requests
			$this->setSecret( $oUser, json_encode( (object)$oRegistration ) );
		}
		catch ( \Exception $oE ) {
			error_log( $oE->getMessage() );
		}

		return !empty( $oRegistration );
	}

	/**
	 * @param \WP_User $oUser
	 * @param bool     $bIsSuccess
	 */
	protected function auditLogin( $oUser, $bIsSuccess ) {
		$this->getCon()->fireEvent(
			$bIsSuccess ? '2fa_u2f_verified' : '2fa_u2f_fail',
			[
				'audit' => [
					'user_login' => $oUser->user_login,
					'method'     => 'U2F',
				]
			]
		);
	}

	/**
	 * @return bool
	 */
	public function isProviderEnabled() {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isEnabledU2F();
	}
}