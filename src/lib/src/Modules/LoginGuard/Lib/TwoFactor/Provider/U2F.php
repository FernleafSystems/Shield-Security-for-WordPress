<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;
use u2flib_server\RegisterRequest;
use u2flib_server\SignRequest;

class U2F extends BaseProvider {

	const SLUG = 'u2f';
	const STANDALONE = false;

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	public function isProfileActive( \WP_User $oUser ) {
		return parent::isProfileActive( $oUser ) && $this->hasValidatedProfile( $oUser );
	}

	public function setupProfile() {
		add_action( 'admin_enqueue_scripts', function ( $sHook ) {
			if ( in_array( $sHook, [ 'profile.php', ] ) ) {
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
		try {
			list( $oReg, $aSigns ) = $this->createNewU2fRegistrationRequest( $oUser );
			wp_localize_script(
				$this->getCon()->prefix( 'shield-u2f-admin' ),
				'icwp_wpsf_vars_u2f',
				[
					'reg_request' => $oReg,
					'signs'       => $aSigns,
					'ajax'        => [
						'u2f_remove' => $this->getMod()->getAjaxActionData( 'u2f_remove' )
					],
					'flags'       => [
						'has_validated' => $this->hasValidatedProfile( $oUser )
					],
					'strings'     => [
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
			/** @var SignRequest[] $aSignReqs */
			$aSignReqs = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )
				->getAuthenticateData( $this->getRegistrations( $oUser ) );

			if ( empty( $aSignReqs ) ) {
				throw new \Exception( 'No signature requests could be created' );
			}

			$aFieldData = [
				'name'        => $this->getLoginFormParameter(),
				'type'        => 'hidden',
				'value'       => '',
				'placeholder' => '',
				'text'        => '',
				'help_link'   => '',
				'datas'       => [
					'signs' => base64_encode( json_encode( $aSignReqs ) ),
				]
			];
		}
		catch ( \Exception $oE ) {
		}

		return $aFieldData;
	}

	/**
	 * @param \WP_User $oUser
	 * @return object[]
	 * @throws \u2flib_server\Error
	 */
	private function createNewU2fRegistrationRequest( \WP_User $oUser ) {
		$oMeta = $this->getCon()->getUserMeta( $oUser );
		list( $oRegRequest, $aSignRequests ) = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )
			->getRegisterData( $this->getRegistrations( $oUser ) );
		$oMeta->u2f_regrequest = json_encode( get_object_vars( $oRegRequest ) );
		return [ $oRegRequest, $aSignRequests ];
	}

	/**
	 * @param \WP_User $oUser
	 * @return \stdClass[]
	 */
	private function getRegistrations( \WP_User $oUser, $bAsObjects = true ) {
		$aRegs = json_decode( $this->getSecret( $oUser ), true );
		if ( $bAsObjects ) {
			$aRegs = array_map(
				function ( $aReg ) {
					return (object)$aReg;
				},
				$aRegs
			);
		}
		return $aRegs;
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
			return json_encode( (object)$this->createNewU2fRegistrationRequest( $oUser ) );
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
			'vars'    => [
				'registrations' => $this->getRegistrations( $oUser )
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

		$sU2fResponse = Services::Request()->post( 'icwp_wpsf_new_u2f_response' );
		if ( !empty( $sU2fResponse ) ) {
			$oMeta = $this->getCon()->getUserMeta( $oUser );

			try {
				$oDecodedResponse = json_decode( $sU2fResponse );
				$sLabel = sanitize_key( $oDecodedResponse->label );
				if ( strlen( $sLabel ) > 16 ) {
					throw new \Exception( 'U2F Device label is larger than 16 characters.' );
				}
				if ( array_key_exists( $sLabel, $this->getRegistrations( $oUser ) ) ) {
					throw new \Exception( 'U2F Device with this label already exists.' );
				}

				$oRegRequest = json_decode( $oMeta->u2f_regrequest );
				$oRegistration = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )->doRegister(
					new RegisterRequest( $oRegRequest->challenge, $oRegRequest->appId ),
					$oDecodedResponse
				);

				// attach the device label
				$aConfirmedReg = get_object_vars( $oRegistration );
				$aConfirmedReg[ 'label' ] = $sLabel;
				$this->addRegistration( $oUser, $aConfirmedReg )
					 ->setProfileValidated( $oUser );

				$sMsg = __( 'U2F Key has been registered successfully on your profile.', 'wp-simple-firewall' );
			}
			catch ( \Exception $oE ) {
				$bError = true;
				$sMsg = sprintf( __( 'U2F Key registration failed with the following error: %s', 'wp-simple-firewall' ),
					$oE->getMessage() );
			}
		}
		elseif ( Services::Request()->post( 'wpsf_u2f_key_delete' ) === 'Y' ) {
			$this->processRemovalFromAccount( $oUser );
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
	 * @return $this
	 */
	protected function processRemovalFromAccount( $oUser ) {
		return $this->setProfileValidated( $oUser, false )
					->deleteSecret( $oUser );
	}

	/**
	 * @param \WP_User $oUser
	 * @param array    $aReg
	 * @return $this
	 */
	private function addRegistration( \WP_User $oUser, array $aReg ) {
		$aRegs = $this->getRegistrations( $oUser, false );

		// We've been passed a Registration without a label. (for example counter increment)
		// So we try to locate the pre-existing registration in order to update it.
		if ( empty( $aReg[ 'label' ] ) ) {
			$aComparisonKeys = [
				'keyHandle',
				'publicKey',
				'certificate',
			];
			foreach ( $aRegs as $sLabel => $aMaybeReg ) {
				$bIsReg = true;
				foreach ( $aComparisonKeys as $sKeyToCompare ) {
					$bIsReg = $bIsReg && ( $aMaybeReg[ $sKeyToCompare ] === $aReg[ $sKeyToCompare ] );
				}
				if ( $bIsReg ) {
					$aReg[ 'label' ] = $aMaybeReg[ 'label' ];
					break;
				}
			}
		}

		if ( !empty( $aReg[ 'label' ] ) ) {
			$aRegs[ $aReg[ 'label' ] ] = $aReg;
			error_log( var_export( $aRegs, true ) );
		}

		return $this->storeRegistrations( $oUser, $aRegs );
	}

	/**
	 * @param \WP_User $oUser
	 * @param array    $aRegs
	 * @return $this
	 */
	private function storeRegistrations( \WP_User $oUser, array $aRegs ) {
		return $this->setProfileValidated( $oUser, !empty( $aRegs ) )
					->setSecret( $oUser, json_encode( $aRegs ) );
	}

	/**
	 * @param \WP_User $oUser
	 * @param string   $sU2fID
	 * @return $this
	 */
	public function removeRegisteredU2fId( \WP_User $oUser, $sU2fID ) {
		$aRegs = $this->getRegistrations( $oUser );
		if ( isset( $aRegs[ $sU2fID ] ) ) {
			unset( $aRegs[ $sU2fID ] );
			$this->storeRegistrations( $oUser, $aRegs );
		}
		return $this;
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

			$oRegistration = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )
				->doAuthenticate(
					json_decode( base64_decode( $oReq->post( 'u2f_signs' ) ) ),
					$this->getRegistrations( $oUser ),
					json_decode( $sOtpCode )
				);

			$this->addRegistration( $oUser, get_object_vars( $oRegistration ) );

			// We "update" the registration as there is a counter to track requests
//			$this->setSecret( $oUser, json_encode( (object)$oRegistration ) );
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