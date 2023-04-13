<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	MfaU2fAdd,
	MfaU2fRemove
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;
use u2flib_server\RegisterRequest;
use u2flib_server\SignRequest;

class U2F extends AbstractShieldProvider {

	protected const SLUG = 'u2f';

	public function isProfileActive() :bool {
		return $this->hasValidatedProfile();
	}

	public function getJavascriptVars() :array {
		[ $reg, $signs ] = $this->createNewU2fRegistrationRequest();
		return [
			'reg_request' => $reg,
			'signs'       => $signs,
			'ajax'        => [
				'profile_u2f_add'    => ActionData::Build( MfaU2fAdd::class ),
				'profile_u2f_remove' => ActionData::Build( MfaU2fRemove::class ),
			],
			'flags'       => [
				'has_validated' => $this->hasValidatedProfile()
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

	public function getFormField() :array {
		$fieldData = [];
		try {
			/** @var SignRequest[] $signReqs */
			$signReqs = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )
				->getAuthenticateData( $this->getRegistrations() );

			if ( empty( $signReqs ) ) {
				throw new \Exception( 'No signature requests could be created' );
			}

			$fieldData = [
				'slug'        => static::ProviderSlug(),
				'name'        => 'btn_u2f_start',
				'type'        => 'button',
				'value'       => __( 'Start U2F Auth', 'wp-simple-firewall' ),
				'placeholder' => '',
				'text'        => 'U2F Authentication',
				'classes'     => [ 'btn', 'btn-light' ],
				'help_link'   => '',
				'datas'       => [
					'signs'     => base64_encode( json_encode( $signReqs ) ),
					'input_otp' => $this->getLoginIntentFormParameter(),
				]
			];
		}
		catch ( \Exception $e ) {
		}

		return $fieldData;
	}

	/**
	 * @return object[]
	 * @throws \u2flib_server\Error
	 */
	private function createNewU2fRegistrationRequest() :array {
		$meta = $this->con()->user_metas->for( $this->getUser() );
		[ $newRegRequest, $signRequests ] = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )
			->getRegisterData( $this->getRegistrations() );

		// Store requests as an array to allow for multiple requests to be kept
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
	 * @return \stdClass[]
	 */
	private function getRegistrations() :array {
		$regs = $this->getSecret();
		if ( !is_array( $regs ) ) {
			$regs = [];
			$this->storeRegistrations( $regs );
		}

		// should always be an array of objects
		foreach ( $regs as $label => $reg ) {
			if ( !is_object( $reg ) ) {
				if ( !is_array( $reg ) || empty( $reg ) ) {
					unset( $regs[ $label ] );
				}
				else {
					$regs[ $label ] = (object)$reg;
					$this->storeRegistrations( $regs );
				}
			}
		}

		return $regs;
	}

	private function getU2fAppID() :string {
		$p = wp_parse_url( Services::WpGeneral()->getHomeUrl() );
		$port = ( empty( $p[ 'port' ] ) || in_array( $p[ 'port' ], [ 80, 443 ] ) ) ? '' : $p[ 'port' ];
		return sprintf( 'https://%s%s', $p[ 'host' ], $port );
	}

	protected function getUserProfileFormRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getUserProfileFormRenderData(),
			[
				'strings' => [
					'title'          => __( 'U2F', 'wp-simple-firewall' ),
					'button_reg_key' => __( 'Register A New U2F Security Key', 'wp-simple-firewall' ),
					'prompt'         => __( 'Click To Register A U2F Device.', 'wp-simple-firewall' ),
				],
				'flags'   => [
					'is_validated' => $this->hasValidatedProfile()
				],
				'vars'    => [
					'registrations' => array_map(
						function ( $reg ) {
							$reg->used_at = sprintf( '(%s: %s)',
								__( 'Used', 'wp-simple-firewall' ),
								empty( $reg->used_at ) ?
									__( 'Never', 'wp-simple-firewall' )
									: Services::Request()
											  ->carbon()
											  ->setTimestamp( $reg->used_at )
											  ->diffForHumans()
							);
							return $reg;
						},
						$this->getRegistrations()
					)
				],
			]
		);
	}

	public function addNewRegistration( array $u2fResponse ) :StdResponse {
		$meta = $this->con()->user_metas->for( $this->getUser() );

		$response = new StdResponse();
		try {
			$u2fResponse = (object)$u2fResponse;
			$label = sanitize_key( $u2fResponse->label );
			if ( strlen( $label ) > 16 ) {
				throw new \Exception( 'U2F Device label is larger than 16 characters.' );
			}
			if ( array_key_exists( $label, $this->getRegistrations() ) ) {
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
			$this->addRegistration( $confirmedReg );

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

	protected function processOtp( string $otp ) :bool {
		try {
			$registration = ( new \u2flib_server\U2F( $this->getU2fAppID() ) )
				->doAuthenticate(
					json_decode( base64_decode( Services::Request()->post( 'u2f_signs' ) ) ),
					$this->getRegistrations(),
					json_decode( $otp )
				);
			$reg = get_object_vars( $registration );
			$reg[ 'used_at' ] = Services::Request()->ts();
			$this->addRegistration( $reg );
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}

		return !empty( $registration );
	}

	private function addRegistration( array $reg ) {
		$regs = $this->getRegistrations();

		// We've been passed a Registration without a label. (for example counter increment)
		// So we try to locate the pre-existing registration and update it.
		if ( empty( $reg[ 'label' ] ) ) {
			$comparisonKeys = [ 'keyHandle', 'publicKey', 'certificate', ];
			foreach ( $regs as $label => $maybeReg ) {
				$isReg = true;
				foreach ( $comparisonKeys as $keyCompare ) {
					$isReg = $isReg && ( $maybeReg->{$keyCompare} === $reg[ $keyCompare ] );
				}
				if ( $isReg ) {
					$reg = array_merge( get_object_vars( $maybeReg ), $reg );
					break;
				}
			}
		}

		// Only add if there's a label, and set defaults
		if ( !empty( $reg[ 'label' ] ) ) {
			$regs[ $reg[ 'label' ] ] = array_merge(
				[
					'used_at' => 0
				],
				$reg
			);
		}

		$this->storeRegistrations( $regs );
	}

	private function storeRegistrations( array $regs ) {
		$this->setProfileValidated( !empty( $regs ) )
			 ->setSecret( $regs );
	}

	public function removeRegisteredU2fId( string $U2fID ) {
		$regs = $this->getRegistrations();
		if ( isset( $regs[ $U2fID ] ) ) {
			unset( $regs[ $U2fID ] );
			$this->storeRegistrations( $regs );
		}
	}

	public function isProviderEnabled() :bool {
		return $this->opts()->isEnabledU2F();
	}

	public function getProviderName() :string {
		return 'U2F';
	}
}