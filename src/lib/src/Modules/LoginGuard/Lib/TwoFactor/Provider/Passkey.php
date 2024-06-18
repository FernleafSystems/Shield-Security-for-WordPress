<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\MfaPasskeyRegistrationStart,
	Actions\MfaPasskeyRegistrationVerify,
	Actions\MfaPasskeyRemoveSource
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\MfaRecordsForDisplay;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\PasskeyCompatibilityCheck;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\PasskeySourcesHandler;
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
	PublicKeyCredentialUserEntity,
	Server,
	TokenBinding\IgnoreTokenBindingHandler
};

class Passkey extends AbstractShieldProviderMfaDB {

	protected const SLUG = 'passkey';

	private $sourceRepo = null;

	public static function ProviderEnabled() :bool {
		return parent::ProviderEnabled()
			   && self::con()->opts->optIs( 'enable_passkeys', 'Y' )
			   && ( new PasskeyCompatibilityCheck() )->run();
	}

	public function getJavascriptVars() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getJavascriptVars(),
			[
				'ajax'    => [
					'passkey_start_registration'  => ActionData::Build( MfaPasskeyRegistrationStart::class ),
					'passkey_verify_registration' => ActionData::Build( MfaPasskeyRegistrationVerify::class ),
					'passkey_remove_registration' => ActionData::Build( MfaPasskeyRemoveSource::class ),
				],
				'flags'   => [
					'has_validated' => $this->hasValidatedProfile()
				],
				'strings' => [
					'not_supported' => __( "Passkey registration isn't supported in this browser", 'wp-simple-firewall' ),
					'failed'        => __( 'Key registration failed.', 'wp-simple-firewall' )
									   .' '.__( "Perhaps the device isn't supported, or you've already registered it.", 'wp-simple-firewall' )
									   .' '.__( 'Please retry or refresh the page.', 'wp-simple-firewall' ),
					'do_save'       => __( 'Key registration was successful.', 'wp-simple-firewall' )
									   .' '.__( 'Please now save your profile settings.', 'wp-simple-firewall' ),
					'prompt_dialog' => __( 'Please provide a label to identify the new authenticator.', 'wp-simple-firewall' ),
					'are_you_sure'  => __( 'Are you sure?', 'wp-simple-firewall' ),
				],
				'vars'    => [
					'username' => $this->getUser()->user_login,
				],
			]
		);
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
					'auth_challenge' => \base64_encode( \wp_json_encode( $this->startNewAuth() ) ),
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
	public function startNewRegistration() :array {
		// New registration challenge
		$publicKeyCredentialCreationOptions = $this->getPasskeyServer()->generatePublicKeyCredentialCreationOptions(
			$this->getUserEntity(),
			PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
			\array_map( function ( PublicKeyCredentialSource $credential ) {
				return $credential->getPublicKeyCredentialDescriptor();
			}, $this->getSourceRepo()->getExcludedSourcesFromAllUsers() ),
			new AuthenticatorSelectionCriteria(
				AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE,
				false,
				AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED
			)
		);
		$publicKeyCredentialCreationOptions->setTimeout( 60000 );

		$WAN = $this->getPasskeysData();
		$WAN[ 'reg_start' ] = $publicKeyCredentialCreationOptions->jsonSerialize();
		$this->setPasskeysData( $WAN );

		return $WAN[ 'reg_start' ];
	}

	/**
	 * @throws \Exception
	 */
	public function startNewAuth() :array {
		// New auth challenge
		$publicKeyCredentialRequestOptions = $this->getPasskeyServer()->generatePublicKeyCredentialRequestOptions(
			AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
			\array_map( function ( PublicKeyCredentialSource $credential ) {
				return $credential->getPublicKeyCredentialDescriptor();
			}, $this->getSourceRepo()->findAllForUserEntity( $this->getUserEntity() ) )
		);
		$publicKeyCredentialRequestOptions->setTimeout( 60000 );

		$WAN = $this->getPasskeysData();
		$WAN[ 'auth_challenge' ] = $publicKeyCredentialRequestOptions->jsonSerialize();
		$this->setPasskeysData( $WAN );

		return $WAN[ 'auth_challenge' ];
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
					'passkeys' => ( new MfaRecordsForDisplay() )->run( $this->getSourceRepo()->getUserSourceRecords() ),
				],
			]
		);
	}

	public function verifyAuthResponse( string $rawJsonEncodedWanResponse ) :StdResponse {
		$response = new StdResponse();

		try {
			$psr17Factory = new Psr17Factory();
			$creator = new ServerRequestCreator(
				$psr17Factory, // ServerRequestFactory
				$psr17Factory, // UriFactory
				$psr17Factory, // UploadedFileFactory
				$psr17Factory  // StreamFactory
			);

			$publicKeyCredentialSource = $this->getPasskeyServer()->loadAndCheckAssertionResponse(
				$rawJsonEncodedWanResponse,
				PublicKeyCredentialRequestOptions::createFromArray( $this->getPasskeysData()[ 'auth_challenge' ] ),
				$this->getUserEntity(),
				$creator->fromGlobals()
			);

			$this->getSourceRepo()->updateSource( $publicKeyCredentialSource, [
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

	public function verifyRegistrationResponse( string $rawJsonEncodedWanResponse, string $label = '' ) :StdResponse {
		$response = new StdResponse();

		$attestationStatementSupportManager = new AttestationStatementSupportManager();
		$attestationStatementSupportManager->add( new NoneAttestationStatementSupport() );
		$publicKeyCredentialLoader = new PublicKeyCredentialLoader(
			new AttestationObjectLoader( $attestationStatementSupportManager )
		);

		try {
			$authenticatorAttestationResponse = $publicKeyCredentialLoader
				->load( $rawJsonEncodedWanResponse )
				->getResponse();
			if ( !$authenticatorAttestationResponse instanceof AuthenticatorAttestationResponse ) {
				throw new \Exception( 'invalid AuthenticatorAttestationResponse response' );
			}

			$authenticatorAttestationResponseValidator = new AuthenticatorAttestationResponseValidator(
				$attestationStatementSupportManager,
				$this->getSourceRepo(),
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

			$this->getSourceRepo()->saveCredentialSource( $publicKeyCredentialSource );
			$this->getSourceRepo()->updateSource( $publicKeyCredentialSource, [
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
		return static::ProviderEnabled() && ( new PasskeyCompatibilityCheck() )->run();
	}

	public static function ProviderName() :string {
		return __( 'Passkeys', 'wp-simple-firewall' );
	}

	public function deleteSource( string $encodedID ) :bool {
		return $this->getSourceRepo()->deleteSource( $encodedID );
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

	private function getPasskeyServer() :Server {
		return new Server(
			new PublicKeyCredentialRpEntity(
				sprintf( 'Shield Security on %s', Services::WpGeneral()->getSiteName() ), //Name
				\wp_parse_url( Services::WpGeneral()->getHomeUrl(), \PHP_URL_HOST ), //ID
				null //Icon
			),
			$this->getSourceRepo(),
			null
		);
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

	public function removeFromProfile() :void {
		parent::removeFromProfile();
		$this->setPasskeysData( [] );
	}

	private function setPasskeysData( array $WAN ) :void {
		$this->con()->user_metas->for( $this->getUser() )->passkeys = $WAN;
	}

	private function getSourceRepo() :PasskeySourcesHandler {
		return $this->sourceRepo ?? $this->sourceRepo = ( new PasskeySourcesHandler() )->setWpUser( $this->getUser() );
	}
}