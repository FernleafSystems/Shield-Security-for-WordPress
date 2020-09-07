<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;
use u2flib_server\RegisterRequest;
use u2flib_server\SignRequest;

class U2F extends BaseProvider {

	const SLUG = 'u2f';
	const DEFAULT_SECRET = '[]';

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
						'not_supported'     => __( 'U2F Security Key registration is not supported in this browser', 'wp-simple-firewall' ),
						'failed'            => __( 'Key registration failed.', 'wp-simple-firewall' )
											   .' '.__( "Perhaps the device isn't supported, or you've already registered it.", 'wp-simple-firewall' )
											   .' '.__( 'Please retry or refresh the page.', 'wp-simple-firewall' ),
						'do_save'           => __( 'Key registration was successful.', 'wp-simple-firewall' )
											   .' '.__( 'Please now save your profile settings.', 'wp-simple-firewall' ),
						'prompt_dialog'     => __( 'Please provide a label to identify the new U2F device.', 'wp-simple-firewall' ),
						'err_no_label'      => __( 'Device registration may not proceed without a unique label.', 'wp-simple-firewall' ),
						'err_invalid_label' => __( 'Device label must contain letters, numbers, underscore, or hypen, and be no more than 16 characters.', 'wp-simple-firewall' ),
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
				'name'        => 'btn_u2f_start',
				'type'        => 'button',
				'value'       => 'Click To Begin U2F Authentication',
				'placeholder' => '',
				'text'        => 'U2F Authentication',
				'classes'     => [ 'btn', 'btn-light' ],
				'help_link'   => '',
				'datas'       => [
					'signs'     => base64_encode( json_encode( $aSignReqs ) ),
					'input_otp' => $this->getLoginFormParameter(),
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
		$oMeta->u2f_regrequest = json_encode( $oRegRequest );
		return [ $oRegRequest, $aSignRequests ];
	}

	/**
	 * @param \WP_User $oUser
	 * @return \stdClass[]
	 */
	private function getRegistrations( \WP_User $oUser ) {
		$aRegs = json_decode( $this->getSecret( $oUser ), true );
		return array_map(
			function ( $aReg ) {
				return (object)$aReg;
			},
			is_array( $aRegs ) ? $aRegs : []
		);
	}

	/**
	 * TODO: Does this port stuff make a difference whatsoever?
	 * @return string
	 */
	private function getU2fAppID() {
		$aPs = wp_parse_url( Services::WpGeneral()->getHomeUrl() );
		$sPort = ( empty( $aPs[ 'port' ] ) || in_array( $aPs[ 'port' ], [ 80, 443 ] ) ) ? '' : $aPs[ 'port' ];
		return sprintf( 'https://%s%s', $aPs[ 'host' ], $sPort );
	}

	/**
	 * @inheritDoc
	 */
	public function renderUserProfileOptions( \WP_User $oUser ) {

		$bValidated = $this->hasValidatedProfile( $oUser );

		$aData = [
			'strings' => [
				'title'          => __( 'U2F', 'wp-simple-firewall' ),
				'button_reg_key' => __( 'Register A New U2F Security Key', 'wp-simple-firewall' ),
				'prompt'         => __( 'Click To Register A U2F Device.', 'wp-simple-firewall' ),
			],
			'flags'   => [
				'is_validated' => $bValidated
			],
			'vars'    => [
				'registrations' => array_map(
					function ( $oReg ) {
						$oReg->used_at = sprintf( '(%s: %s)',
							__( 'Used', 'wp-simple-firewall' ),
							empty( $oReg->used_at ) ?
								__( 'Never', 'wp-simple-firewall' )
								: Services::Request()
										  ->carbon()
										  ->setTimestamp( $oReg->used_at )
										  ->diffForHumans()
						);
						return $oReg;
					},
					$this->getRegistrations( $oUser )
				)
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
				$sLabel = preg_replace( '#[^a-z0-9_-]#i', '', $oDecodedResponse->label );
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

				$sMsg = __( 'U2F Device was successfully registered on your profile.', 'wp-simple-firewall' );
			}
			catch ( \Exception $oE ) {
				$bError = true;
				$sMsg = sprintf( __( 'U2F Device registration failed with the following error: %s', 'wp-simple-firewall' ),
					$oE->getMessage() );
			}
		}
		elseif ( Services::Request()->post( 'wpsf_u2f_key_delete' ) === 'Y' ) {
			$this->processRemovalFromAccount( $oUser );
			$sMsg = __( 'U2F Device was removed from your profile.', 'wp-simple-firewall' );
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
		$aRegs = $this->getRegistrations( $oUser );

		// We've been passed a Registration without a label. (for example counter increment)
		// So we try to locate the pre-existing registration and update it.
		if ( empty( $aReg[ 'label' ] ) ) {
			$aComparisonKeys = [ 'keyHandle', 'publicKey', 'certificate', ];
			foreach ( $aRegs as $sLabel => $oMaybeReg ) {
				$bIsReg = true;
				foreach ( $aComparisonKeys as $sKeyCompare ) {
					$bIsReg = $bIsReg && ( $oMaybeReg->{$sKeyCompare} === $aReg[ $sKeyCompare ] );
				}
				if ( $bIsReg ) {
					$aReg = array_merge( get_object_vars( $oMaybeReg ), $aReg );
					break;
				}
			}
		}

		// Only add if there's a label, and set defaults
		if ( !empty( $aReg[ 'label' ] ) ) {
			$aRegs[ $aReg[ 'label' ] ] = array_merge(
				[
					'used_at' => 0
				],
				$aReg
			);
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
			$oRegistration = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )
				->doAuthenticate(
					json_decode( base64_decode( Services::Request()->post( 'u2f_signs' ) ) ),
					$this->getRegistrations( $oUser ),
					json_decode( $sOtpCode )
				);
			$aReg = get_object_vars( $oRegistration );
			$aReg[ 'used_at' ] = Services::Request()->ts();
			$this->addRegistration( $oUser, $aReg );
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