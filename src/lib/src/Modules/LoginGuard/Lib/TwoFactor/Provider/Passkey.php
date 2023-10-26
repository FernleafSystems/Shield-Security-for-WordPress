<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\MfaPasskeyRemoveSource,
	Actions\MfaPasskeyRegistrationVerify,
	Actions\MfaPasskeyRegistrationStart
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

class Passkey extends AbstractShieldProvider implements PublicKeyCredentialSourceRepository {

	protected const SLUG = 'passkey';

	public function hasValidatedProfile() :bool {
		return \count( $this->loadRawSourcesData() ) > 0;
	}

	public function getJavascriptVars() :array {
		return [
			'ajax'    => [
				'passkey_start_registration'  => ActionData::Build( MfaPasskeyRegistrationStart::class ),
				'passkey_verify_registration' => ActionData::Build( MfaPasskeyRegistrationVerify::class ),
				'passkey_remove_registration' => ActionData::Build( MfaPasskeyRemoveSource::class ),
			],
			'flags'   => [
				'has_validated' => $this->hasValidatedProfile()
			],
			'strings' => [
				'not_supported'     => __( 'U2F Security Key registration is not supported in this browser', 'wp-simple-firewall' ),
				'failed'            => __( 'Key registration failed.', 'wp-simple-firewall' )
									   .' '.__( "Perhaps the device isn't supported, or you've already registered it.", 'wp-simple-firewall' )
									   .' '.__( 'Please retry or refresh the page.', 'wp-simple-firewall' ),
				'do_save'           => __( 'Key registration was successful.', 'wp-simple-firewall' )
									   .' '.__( 'Please now save your profile settings.', 'wp-simple-firewall' ),
				'prompt_dialog'     => __( 'Please provide a label to identify the new authenticator.', 'wp-simple-firewall' ),
				'err_no_label'      => __( 'Device registration may not proceed without a unique label.', 'wp-simple-firewall' ),
				'err_invalid_label' => __( 'Device label must contain letters, numbers, underscore, or hypen, and be no more than 16 characters.', 'wp-simple-firewall' ),
				'are_you_sure'      => __( 'Are you sure?', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'username' => $this->getUser()->user_login,
			],
		];
	}

	public function getFormField() :array {
		$fieldData = [];
		try {

			$fieldData = [
				'slug'              => static::ProviderSlug(),
				'name'              => 'icwp_wpsf_start_passkey',
				'hidden_input_name' => $this->getLoginIntentFormParameter(),
				'element'           => 'button',
				'type'              => 'button',
				'value'             => '',
				'text'              => __( 'Verify Passkey', 'wp-simple-firewall' ),
				'classes'           => [ 'button', 'btn', 'btn-light' ],
				'help_link'         => '',
				'description'       => 'Passkey, Windows Hello, FIDO2, Yubikey, Titan',
				'datas'             => [
					'auth_challenge' => \base64_encode( \json_encode( $this->startNewAuthRequest() ) ),
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
	public function startNewRegistrationRequest( string $label = '' ) :array {
		$newReg = $this->generateNewCredentialsCreation()->jsonSerialize();
		$WAN = $this->getPasskeysData();
		$WAN[ 'reg_start' ] = $newReg;
		$WAN[ 'reg_start_meta' ] = [
			'label'  => sanitize_text_field( $label ),
			'reg_at' => Services::Request()->ts(),
		];
		$this->setPasskeysData( $WAN );
		return $newReg;
	}

	/**
	 * @throws \Exception
	 */
	public function startNewAuthRequest() :array {
		$authChallenge = $this->generateNewAuthChallenge()->jsonSerialize();
		$WAN = $this->getPasskeysData();
		$WAN[ 'auth_challenge' ] = $authChallenge;
		$this->setPasskeysData( $WAN );
		return $authChallenge;
	}

	/**
	 * @throws \Exception
	 */
	private function generateNewAuthChallenge() :PublicKeyCredentialRequestOptions {

		$wanServer = new Server(
			new PublicKeyCredentialRpEntity(
				sprintf( 'Shield Security on %s', Services::WpGeneral()->getSiteName() ), //Name
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
		return $publicKeyCredentialRequestOptions->setTimeout( 60000 );
	}

	/**
	 * @throws \Exception
	 */
	private function generateNewCredentialsCreation() :PublicKeyCredentialCreationOptions {

		$wanServer = new Server(
			new PublicKeyCredentialRpEntity(
				sprintf( 'Shield Security on %s', Services::WpGeneral()->getSiteName() ), //Name
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
		return $publicKeyCredentialCreationOptions->setTimeout( 60000 );
	}

	/**
	 * @return \stdClass[]
	 */
	private function getAuthenticatorForDisplay() :array {
		$sources = $this->loadRawSourcesData();

		/**
		 * Order by most recently used first, then most recently registered.
		 */
		\usort( $sources, function ( array $a, array $b ) {
			$atA = $a[ 'meta' ][ 'used_at' ] ?? 0;
			$atB = $b[ 'meta' ][ 'used_at' ] ?? 0;
			if ( $atA === $atB ) {
				$atA = $a[ 'meta' ][ 'reg_at' ] ?? 0;
				$atB = $b[ 'meta' ][ 'reg_at' ] ?? 0;
				$ret = $atA == $atB ? 0 : ( $atA > $atB ? -1 : 1 );
			}
			else {
				$ret = $atA > $atB ? -1 : 1;
			}
			return $ret;
		} );

		return \array_map(
			function ( array $source ) {
				$meta = $source[ 'meta' ];
				return [
					'id'      => $meta[ 'source_id' ],
					'label'   => empty( $meta[ 'label' ] ) ? __( 'No Name' ) : $meta[ 'label' ],
					'used_at' => sprintf(
						'%s: %s', __( 'Used', 'wp-simple-firewall' ),
						empty( $meta[ 'used_at' ] ) ? __( 'Never' ) :
							Services::Request()
									->carbon( true )
									->setTimestamp( $meta[ 'used_at' ] )
									->diffForHumans()
					),
					'reg_at'  => sprintf(
						'%s: %s', __( 'Registered', 'wp-simple-firewall' ),
						empty( $meta[ 'reg_at' ] ) ? __( 'Unknown' ) :
							Services::Request()
									->carbon( true )
									->setTimestamp( $meta[ 'reg_at' ] )
									->diffForHumans()
					)
				];
			},
			$sources
		);
	}

	protected function getUserProfileFormRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getUserProfileFormRenderData(),
			[
				'strings' => [
					'title'              => __( 'Passkeys', 'wp-simple-firewall' ),
					'button_reg_key'     => __( 'Register New Passkey', 'wp-simple-firewall' ),
					'prompt'             => __( 'Click To Register A Passkey.', 'wp-simple-firewall' ),
					'registered_devices' => __( 'Registered Passkeys', 'wp-simple-firewall' ),
				],
				'flags'   => [
					'is_validated' => $this->hasValidatedProfile(),
				],
				'vars'    => [
					'registrations' => $this->getAuthenticatorForDisplay(),
				],
			]
		);
	}

	public function verifyAuthResponse( string $rawJsonEncodedWanResponse ) :StdResponse {
		$response = new StdResponse();

		try {
			$wanServer = new Server(
				new PublicKeyCredentialRpEntity(
					sprintf( 'Shield Security on %s', Services::WpGeneral()->getSiteName() ), //Name
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

			$publicKeyCredentialSource = $wanServer->loadAndCheckAssertionResponse(
				$rawJsonEncodedWanResponse,
				PublicKeyCredentialRequestOptions::createFromArray( $this->getPasskeysData()[ 'auth_challenge' ] ),
				$this->getUserEntity(),
				$creator->fromGlobals()
			);

			$this->updateSource( $publicKeyCredentialSource, [
				'used_at' => Services::Request()->ts(),
			] );

			$response->msg_text = __( 'Passkey authentication was successful.', 'wp-simple-firewall' );
			$response->success = true;
		}
		catch ( \Throwable $e ) {
			$response->success = false;
			$response->error_text = sprintf( __( 'Passkey authentication failed with the following error: %s', 'wp-simple-firewall' ),
				$e->getMessage() );
		}

		return $response;
	}

	public function verifyNewRegistration( string $rawJsonEncodedWanResponse, string $label = '' ) :StdResponse {
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
				PublicKeyCredentialCreationOptions::createFromArray( $this->getPasskeysData()[ 'reg_start' ] ),
				$creator->fromGlobals()
			);

			$this->saveCredentialSource( $publicKeyCredentialSource );
			$this->updateSource( $publicKeyCredentialSource, [
				'label' => sanitize_text_field( \trim( $label ) ),
			] );

			$response->msg_text = __( 'Passkey was successfully registered on your profile.', 'wp-simple-firewall' );
			$response->success = true;
		}
		catch ( \Throwable $e ) {
			$response->success = false;
			$response->error_text = sprintf( __( 'Passkey registration failed with the following error: %s', 'wp-simple-firewall' ),
				$e->getMessage() );
		}

		return $response;
	}

	protected function processOtp( string $otp ) :bool {
		return $this->verifyAuthResponse( \base64_decode( $otp ) )->success;
	}

	public function isProviderEnabled() :bool {
		return $this->opts()->isOpt( 'enable_passkeys', 'Y' );
	}

	public function getProviderName() :string {
		return 'Passkeys';
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
			$this->getPasskeysData()[ 'public_credential_sources' ] ?? []
		);
	}

	public function saveCredentialSource( PublicKeyCredentialSource $publicKeyCredentialSource ) :void {

		$WAN = $this->getPasskeysData();
		$startMeta = $WAN[ 'reg_start_meta' ] ?? [];
		unset( $WAN[ 'reg_start_meta' ] );
		$this->setPasskeysData( $WAN );

		if ( Services::Request()->ts() - ( $startMeta[ 'reg_at' ] ?? 0 ) > 60 ) {
			$startMeta = [];
		}

		$this->updateSource( $publicKeyCredentialSource, $startMeta );
	}

	public function deleteSource( string $encodedID ) :bool {
		$WAN = $this->getPasskeysData();
		unset( $WAN[ 'public_credential_sources' ][ $encodedID ] );
		$this->setPasskeysData( $WAN );
		return true;
	}

	public function updateSource( PublicKeyCredentialSource $publicKeyCredentialSource, array $meta = [] ) :void {
		$WAN = $this->getPasskeysData();

		if ( empty( $WAN[ 'public_credential_sources' ] ) ) {
			$WAN[ 'public_credential_sources' ] = [];
		}

		$sourceID = \base64_encode( $publicKeyCredentialSource->getPublicKeyCredentialId() );
		$meta[ 'source_id' ] = $sourceID;

		// Encoding ensures data fidelity
		$WAN[ 'public_credential_sources' ][ $sourceID ] = [
			'meta'   => \array_merge(
				$WAN[ 'public_credential_sources' ][ $sourceID ][ 'meta' ] ?? [],
				$meta
			),
			'source' => \base64_encode( \json_encode( $publicKeyCredentialSource->jsonSerialize() ) ),
		];

		$this->setPasskeysData( $WAN );
	}

	private function getUserWanKey() :string {
		$WAN = $this->getPasskeysData();
		if ( empty( $WAN[ 'user_key' ] ) ) {
			$WAN[ 'user_key' ] = \bin2hex( \random_bytes( 16 ) );
			$this->setPasskeysData( $WAN );
		}
		return $WAN[ 'user_key' ];
	}

	private function getPasskeysData() :array {
		$meta = $this->con()->user_metas->for( $this->getUser() );
		return \is_array( $meta->passkeys ) ? $meta->passkeys : ( $meta->passkeys = [] );
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

	private function setPasskeysData( array $WAN ) :self {
		$this->con()->user_metas->for( $this->getUser() )->passkeys = $WAN;
		return $this;
	}
}