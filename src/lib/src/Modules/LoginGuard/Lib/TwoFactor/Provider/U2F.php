<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;
use u2flib_server\RegisterRequest;
use u2flib_server\SignRequest;

class U2F extends BaseProvider {

	const SLUG = 'u2f';
	const DEFAULT_SECRET = '[]';

	public function isProfileActive( \WP_User $user ) :bool {
		return parent::isProfileActive( $user ) && $this->hasValidatedProfile( $user );
	}

	public function getJavascriptVars() :array {
		$user = Services::WpUsers()->getCurrentWpUser();
		list( $reg, $signs ) = $this->createNewU2fRegistrationRequest( $user );
		return [
			'reg_request' => $reg,
			'signs'       => $signs,
			'ajax'        => [
				'u2f_add'    => $this->getMod()->getAjaxActionData( 'u2f_add' ),
				'u2f_remove' => $this->getMod()->getAjaxActionData( 'u2f_remove' ),
			],
			'flags'       => [
				'has_validated' => $this->hasValidatedProfile( $user )
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
		];
	}

	/**
	 * @return array
	 */
	public function getFormField() :array {
		$user = Services::WpUsers()->getCurrentWpUser();

		$aFieldData = [];
		try {
			/** @var SignRequest[] $aSignReqs */
			$aSignReqs = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )
				->getAuthenticateData( $this->getRegistrations( $user ) );

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
		catch ( \Exception $e ) {
		}

		return $aFieldData;
	}

	/**
	 * @param \WP_User $user
	 * @return object[]
	 * @throws \u2flib_server\Error
	 */
	private function createNewU2fRegistrationRequest( \WP_User $user ) {
		$meta = $this->getCon()->getUserMeta( $user );
		list( $newRegRequest, $signRequests ) = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )
			->getRegisterData( $this->getRegistrations( $user ) );

		// Store requests as an array to allow for multiple requests to be kept
		unset( $meta->u2f_regrequest ); // Old property
		$userRegRequests = array_filter(
			is_array( $meta->u2f_regrequests ) ? $meta->u2f_regrequests : [],
			function ( $ts ) {
				return Services::Request()->ts() - $ts < MINUTE_IN_SECONDS*10;
			}
		);
		$userRegRequests[ json_encode( $newRegRequest ) ] = Services::Request()->ts();
		$meta->u2f_regrequests = $userRegRequests;

		return [ $newRegRequest, $signRequests ];
	}

	/**
	 * @param \WP_User $user
	 * @return \stdClass[]
	 */
	private function getRegistrations( \WP_User $user ) {
		$regs = json_decode( $this->getSecret( $user ), true );
		return array_map(
			function ( $reg ) {
				return (object)$reg;
			},
			is_array( $regs ) ? $regs : []
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

	protected function getProviderSpecificRenderData( \WP_User $user ) :array {
		return [
			'strings' => [
				'title'          => __( 'U2F', 'wp-simple-firewall' ),
				'button_reg_key' => __( 'Register A New U2F Security Key', 'wp-simple-firewall' ),
				'prompt'         => __( 'Click To Register A U2F Device.', 'wp-simple-firewall' ),
			],
			'flags'   => [
				'is_validated' => $this->hasValidatedProfile( $user )
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
					$this->getRegistrations( $user )
				)
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function renderUserEditProfileOptions( \WP_User $user ) {
		// Allow no actions to be taken on other user profiles
	}

	/**
	 * @inheritDoc
	 */
	public function handleUserProfileSubmit( \WP_User $user ) {
		$rawU2fResponse = Services::Request()->post( 'icwp_wpsf_new_u2f_response' );
		if ( !empty( $rawU2fResponse ) ) {
			$result = $this->addNewRegistration( $user, json_decode( $rawU2fResponse, true ) );
			$this->getMod()
				 ->setFlashAdminNotice( $result->success ? $result->msg_text : $result->error_text, $result->failed );
		}
	}

	public function addNewRegistration( \WP_User $user, array $u2fResponse ) :StdResponse {
		$response = new StdResponse();

		$meta = $this->getCon()->getUserMeta( $user );

		try {
			$u2fResponse = (object)$u2fResponse;
			$label = preg_replace( '#[^a-z0-9_-]#i', '', $u2fResponse->label );
			if ( strlen( $label ) > 16 ) {
				throw new \Exception( 'U2F Device label is larger than 16 characters.' );
			}
			if ( array_key_exists( $label, $this->getRegistrations( $user ) ) ) {
				throw new \Exception( 'U2F Device with this label already exists.' );
			}

			$U2FRegistration = null;
			foreach ( $meta->u2f_regrequests as $u2fRequest => $ts ) {
				try {
					$regRequest = json_decode( $u2fRequest );
					$U2FRegistration = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )->doRegister(
						new RegisterRequest( $regRequest->challenge, $regRequest->appId ),
						$u2fResponse
					);
					$regReqs = $meta->u2f_regrequests;
					unset( $regReqs[ $u2fRequest ] );
					$meta->u2f_regrequests = $regReqs;
					break;
				}
				catch ( \Exception $e ) {
				}
			}

			if ( empty( $U2FRegistration ) ) {
				throw new \Exception( "Couldn't find a suitable U2F challenge to verify." );
			}

			// attach the device label
			$confirmedReg = get_object_vars( $U2FRegistration );
			$confirmedReg[ 'label' ] = $label;
			$this->addRegistration( $user, $confirmedReg )
				 ->setProfileValidated( $user );

			$response->msg_text = __( 'U2F Device was successfully registered on your profile.', 'wp-simple-firewall' );
			$response->success = true;
		}
		catch ( \Exception $e ) {
			$response->success = false;
			$response->error_text = sprintf( __( 'U2F Device registration failed with the following error: %s', 'wp-simple-firewall' ),
				$e->getMessage() );
		}

		return $response;
	}

	protected function processOtp( \WP_User $user, string $otp ) :bool {
		return $this->validateU2F( $user, $otp );
	}

	/**
	 * @param \WP_User $user
	 * @param array    $aReg
	 * @return $this
	 */
	private function addRegistration( \WP_User $user, array $aReg ) {
		$aRegs = $this->getRegistrations( $user );

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

		return $this->storeRegistrations( $user, $aRegs );
	}

	/**
	 * @param \WP_User $user
	 * @param array    $regs
	 * @return $this
	 */
	private function storeRegistrations( \WP_User $user, array $regs ) {
		return $this->setProfileValidated( $user, !empty( $regs ) )
					->setSecret( $user, json_encode( $regs ) );
	}

	/**
	 * @param \WP_User $user
	 * @param string   $sU2fID
	 * @return $this
	 */
	public function removeRegisteredU2fId( \WP_User $user, $sU2fID ) {
		$regs = $this->getRegistrations( $user );
		if ( isset( $regs[ $sU2fID ] ) ) {
			unset( $regs[ $sU2fID ] );
			$this->storeRegistrations( $user, $regs );
		}
		return $this;
	}

	private function validateU2F( \WP_User $user, string $otp ) :bool {
		try {
			$oRegistration = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )
				->doAuthenticate(
					json_decode( base64_decode( Services::Request()->post( 'u2f_signs' ) ) ),
					$this->getRegistrations( $user ),
					json_decode( $otp )
				);
			$aReg = get_object_vars( $oRegistration );
			$aReg[ 'used_at' ] = Services::Request()->ts();
			$this->addRegistration( $user, $aReg );
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}

		return !empty( $oRegistration );
	}

	/**
	 * @param \WP_User $user
	 * @param bool     $bIsSuccess
	 */
	protected function auditLogin( \WP_User $user, bool $bIsSuccess ) {
		$this->getCon()->fireEvent(
			$bIsSuccess ? '2fa_u2f_verified' : '2fa_u2f_fail',
			[
				'audit' => [
					'user_login' => $user->user_login,
					'method'     => 'U2F',
				]
			]
		);
	}

	public function isProviderEnabled() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledU2F();
	}

	protected function getProviderName() :string {
		return 'U2F';
	}
}