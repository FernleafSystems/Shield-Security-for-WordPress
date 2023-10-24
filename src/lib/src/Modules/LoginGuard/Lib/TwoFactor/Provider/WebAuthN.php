<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\MfaWebauthnRemoveSource,
	Actions\MfaWebauthnRegistrationVerify,
	Actions\MfaWebauthnRegistrationStart
};
use FernleafSystems\Wordpress\Services\Services;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Webauthn\{
	AttestationStatement\AttestationObjectLoader,
	AttestationStatement\AttestationStatementSupportManager,
	AttestationStatement\NoneAttestationStatementSupport,
	AuthenticationExtensions\ExtensionOutputCheckerHandler,
	AuthenticatorAttestationResponse,
	AuthenticatorAttestationResponseValidator,
	AuthenticatorSelectionCriteria,
	PublicKeyCredentialCreationOptions,
	PublicKeyCredentialLoader,
	PublicKeyCredentialRequestOptions,
	PublicKeyCredentialRpEntity,
	PublicKeyCredentialSource,
	PublicKeyCredentialSourceRepository,
	PublicKeyCredentialUserEntity,
	Server,
	TokenBinding\IgnoreTokenBindingHandler
};

class WebAuthN extends AbstractShieldProvider implements PublicKeyCredentialSourceRepository {

	protected const SLUG = 'wan';

	public function isProfileActive() :bool {
		return $this->hasValidatedProfile();
	}

	public function getJavascriptVars() :array {
		return [
			'ajax'        => [
				'wan_start_registration'  => ActionData::Build( MfaWebauthnRegistrationStart::class ),
				'wan_verify_registration' => ActionData::Build( MfaWebauthnRegistrationVerify::class ),
				'wan_remove_registration' => ActionData::Build( MfaWebauthnRemoveSource::class ),
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
			],
			'vars'        => [
				'username' => $this->getUser()->user_login,
			],
		];
	}

	public function getFormField() :array {
		$fieldData = [];
		try {

			$fieldData = [
				'slug'        => static::ProviderSlug(),
				'name'        => 'btn_start_wan',
				'type'        => 'button',
				'value'       => __( 'Start WebAuthN', 'wp-simple-firewall' ),
				'placeholder' => '',
				'text'        => 'U2F Authentication',
				'classes'     => [ 'btn', 'btn-light' ],
				'help_link'   => '',
				'datas'       => [
					'auth_challenge' => \base64_encode( \json_encode( $this->startNewAuthRequest() ) ),
					'input_otp' => $this->getLoginIntentFormParameter(),
				]
			];
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}

		return $fieldData;
	}

	/**
	 * @throws \Exception
	 */
	public function startNewRegistrationRequest() :array {
		$newReg = $this->generateNewCredentialsCreation()->jsonSerialize();
		$WAN = $this->getWanData();
		$WAN[ 'reg_start' ] = $newReg;
		$WAN[ 'reg_start_meta' ] = [
			'label' => 'Test Label '.wp_rand(),
			/** TODO */
		];
		$this->setWanData( $WAN );
		return $newReg;
	}

	/**
	 * @throws \Exception
	 */
	public function startNewAuthRequest() :array {
		$authChallenge = $this->generateNewAuthChallenge()->jsonSerialize();
		$WAN = $this->getWanData();
		$WAN[ 'auth_challenge' ] = $authChallenge;
		$this->setWanData( $WAN );
		return $authChallenge;
	}

	/**
	 * @throws \Exception
	 */
	private function generateNewAuthChallenge() :PublicKeyCredentialRequestOptions {

		$wanServer = new Server(
			new PublicKeyCredentialRpEntity(
				sprintf( 'Shield WebAuthN on %s', Services::WpGeneral()->getSiteName() ), //Name
				\parse_url( Services::WpGeneral()->getHomeUrl(), \PHP_URL_HOST ), //ID
				null //Icon
			),
			$this,
			null
		);

		$publicKeyCredentialRequestOptions = $wanServer->generatePublicKeyCredentialRequestOptions(
			AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
			\array_map( function ( PublicKeyCredentialSource $credential ) {
				return $credential->getPublicKeyCredentialDescriptor();
			}, $this->findAllForUserEntity( $this->getUserEntity() ) )
		);
		return $publicKeyCredentialRequestOptions->setTimeout( 90000 );
	}

	/**
	 * @throws \Exception
	 */
	private function generateNewCredentialsCreation() :PublicKeyCredentialCreationOptions {

		$wanServer = new Server(
			new PublicKeyCredentialRpEntity(
				sprintf( 'Shield WebAuthN on %s', Services::WpGeneral()->getSiteName() ), //Name
				\parse_url( Services::WpGeneral()->getHomeUrl(), \PHP_URL_HOST ), //ID
				null //Icon
			),
			$this,
			null
		);

		// Create a creation challenge
		$publicKeyCredentialCreationOptions = $wanServer->generatePublicKeyCredentialCreationOptions(
			$this->getUserEntity(),
			PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
			\array_map( function ( PublicKeyCredentialSource $credential ) {
				return $credential->getPublicKeyCredentialDescriptor();
			}, $this->findAllForUserEntity( $this->getUserEntity() ) ),
			new AuthenticatorSelectionCriteria(
				AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE,
				false,
				AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_DISCOURAGED
			)
		);
		return $publicKeyCredentialCreationOptions->setTimeout( 90000 );
	}

	/**
	 * @return \stdClass[]
	 */
	private function getRegistrations() :array {
		return \array_map(
			function ( array $source ) {
				$meta = $source[ 'meta' ];
				return [
					'id'      => $meta[ 'source_id' ],
					'label'   => $meta[ 'label' ],
					'used_at' => empty( $meta[ 'used_at' ] ) ?
						__( 'Never' ) : Services::Request()
												->carbon( true )
												->setTimestamp( $meta[ 'used_at' ] )
												->diffForHumans(),
				];
			},
			$this->loadRawSourcesData()
		);
	}

	protected function getUserProfileFormRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getUserProfileFormRenderData(),
			[
				'strings' => [
					'title'          => __( 'WebAuthN / FIDO2', 'wp-simple-firewall' ),
					'button_reg_key' => __( 'Register A New WebAuthN Security Key', 'wp-simple-firewall' ),
					'prompt'         => __( 'Click To Register A WebAuthN Device.', 'wp-simple-firewall' ),
				],
				'flags'   => [
					'is_validated' => $this->hasValidatedProfile(),
				],
				'vars'    => [
					'registrations' => $this->getRegistrations(),
				],
			]
		);
	}

	public function verifyAuthResponse( string $rawJsonEncodedWanResponse ) :StdResponse {
		$response = new StdResponse();
		try {
			$wanServer = new Server(
				new PublicKeyCredentialRpEntity(
					sprintf( 'Shield WebAuthN on %s', Services::WpGeneral()->getSiteName() ), //Name
					\parse_url( Services::WpGeneral()->getHomeUrl(), \PHP_URL_HOST ), //ID
					null //Icon
				),
				$this,
				null
			);

			$psr17Factory = new Psr17Factory();
			$creator = new ServerRequestCreator(
				$psr17Factory, // ServerRequestFactory
				$psr17Factory, // UriFactory
				$psr17Factory, // UploadedFileFactory
				$psr17Factory  // StreamFactory
			);

			$wanServer->loadAndCheckAssertionResponse(
				$rawJsonEncodedWanResponse,
				PublicKeyCredentialRequestOptions::createFromArray( $this->getWanData()[ 'auth_challenge' ] ),
				$this->getUserEntity(),
				$creator->fromGlobals()
			);

			$response->msg_text = __( 'WebAuthN authentication was successful.', 'wp-simple-firewall' );
			$response->success = true;
		}
		catch ( \Throwable $e ) {
			$response->success = false;
			$response->error_text = sprintf( __( 'WebAuthN authentication failed with the following error: %s', 'wp-simple-firewall' ),
				$e->getMessage() );
		}

		return $response;
	}

	public function verifyNewRegistration( string $rawJsonEncodedWanResponse ) :StdResponse {
		$response = new StdResponse();

		$attestationStatementSupportManager = new AttestationStatementSupportManager();
		$attestationStatementSupportManager->add( new NoneAttestationStatementSupport() );
		$publicKeyCredentialLoader = new PublicKeyCredentialLoader(
			new AttestationObjectLoader( $attestationStatementSupportManager )
		);

		try {
			$publicKeyCredential = $publicKeyCredentialLoader->load( $rawJsonEncodedWanResponse );
			$authenticatorAttestationResponse = $publicKeyCredential->getResponse();
			if ( !$authenticatorAttestationResponse instanceof AuthenticatorAttestationResponse ) {
				throw new \Exception( 'invalid AuthenticatorAttestationResponse response' );
			}

			$authenticatorAttestationResponseValidator = new AuthenticatorAttestationResponseValidator(
				$attestationStatementSupportManager,
				$this,
				new IgnoreTokenBindingHandler(),
				new ExtensionOutputCheckerHandler()
			);

			$psr17Factory = new Psr17Factory();
			$creator = new ServerRequestCreator(
				$psr17Factory, // ServerRequestFactory
				$psr17Factory, // UriFactory
				$psr17Factory, // UploadedFileFactory
				$psr17Factory  // StreamFactory
			);

			$publicKeyCredentialSource = $authenticatorAttestationResponseValidator->check(
				$authenticatorAttestationResponse,
				PublicKeyCredentialCreationOptions::createFromArray( $this->getWanData()[ 'reg_start' ] ),
				$creator->fromGlobals()
			);

			$this->saveCredentialSource( $publicKeyCredentialSource );

			$response->msg_text = __( 'WebAuthN Device was successfully registered on your profile.', 'wp-simple-firewall' );
			$response->success = true;
		}
		catch ( \Throwable $e ) {
			$response->success = false;
			$response->error_text = sprintf( __( 'WebAuthN Device registration failed with the following error: %s', 'wp-simple-firewall' ),
				$e->getMessage() );
		}

		return $response;
	}

	protected function processOtp( string $otp ) :bool {
		try {
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}

		return !empty( $registration );
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
		return true;
	}

	public function getProviderName() :string {
		return 'WebAuthN';
	}

	public function findOneByCredentialId( string $publicKeyCredentialId ) :?PublicKeyCredentialSource {
		return $this->loadRawSourcesData()[ \base64_encode( $publicKeyCredentialId ) ][ 'source' ] ?? null;
	}

	/**
	 * @return PublicKeyCredentialSource[]
	 */
	public function findAllForUserEntity( PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity ) :array {
		return \array_values( \array_filter( \array_map(
			function ( array $data ) {
				return $data[ 'source' ];
			},
			$this->loadRawSourcesData()
		) ) );
	}

	private function loadRawSourcesData() :array {
		return \array_map(
			function ( array $data ) {
				try {
					return [
						'source' => PublicKeyCredentialSource::createFromArray( \json_decode( \base64_decode( $data[ 'source' ] ), true ) ),
						'meta'   => $data[ 'meta' ],
					];
				}
				catch ( \InvalidArgumentException $e ) {
					return null;
				}
			},
			$this->getWanData()[ 'public_credential_sources' ] ?? []
		);
	}

	public function saveCredentialSource( PublicKeyCredentialSource $publicKeyCredentialSource ) :void {
		$this->updateSource( $publicKeyCredentialSource, $this->getWanData()[ 'reg_start_meta' ] ?? [] );
	}

	public function deleteSource( string $encodedID ) :void {
		$all = $this->loadRawSourcesData();
		unset( $all[ $encodedID ] );
		$WAN = $this->getWanData();
		$WAN[ 'public_credential_sources' ] = $all;
		$this->setWanData( $WAN );
	}

	public function updateSource( PublicKeyCredentialSource $publicKeyCredentialSource, array $meta = [] ) :void {
		$WAN = $this->getWanData();

		if ( empty( $WAN[ 'public_credential_sources' ] ) ) {
			$WAN[ 'public_credential_sources' ] = [];
		}

		$meta[ 'source_id' ] = \base64_encode( $publicKeyCredentialSource->getPublicKeyCredentialId() );

		// Encoding ensures data fidelity
		$WAN[ 'public_credential_sources' ][ $meta[ 'source_id' ] ] = [
			'meta'   => $meta,
			'source' => \base64_encode( \json_encode( $publicKeyCredentialSource->jsonSerialize() ) ),
		];

		$this->setWanData( $WAN );
	}

	private function getUserWanKey() :string {
		$WAN = $this->getWanData();
		if ( empty( $WAN[ 'user_key' ] ) ) {
			$WAN[ 'user_key' ] = \bin2hex( \random_bytes( 16 ) );
			$this->setWanData( $WAN );
		}
//		unset( $WAN[ 'public_credential_sources' ] );
//		$this->setWanData( $WAN );
		return $WAN[ 'user_key' ];
	}

	private function getWanData() :array {
		$meta = $this->con()->user_metas->for( $this->getUser() );
		return \is_array( $meta->webauthn ) ? $meta->webauthn : ( $meta->webauthn = [] );
	}

	private function getUserEntity() :PublicKeyCredentialUserEntity {
		$user = $this->getUser();
		return new PublicKeyCredentialUserEntity(
			$user->user_login,
			$this->getUserWanKey(),
			$user->display_name,
			get_avatar_url( $user->user_email, [ "scheme" => "https" ] )
		);
	}

	private function setWanData( array $WAN ) :self {
		$this->con()->user_metas->for( $this->getUser() )->webauthn = $WAN;
		return $this;
	}
}