<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Passkey\{
	PasskeyAdapterContext,
	PasskeyAdapterInterface,
	WebauthnLibAdapter
};
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

class Passkey extends AbstractShieldProviderMfaDB {

	protected const SLUG = 'passkey';

	private $sourceRepo = null;

	private ?PasskeyAdapterInterface $adapter = null;

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
		$WAN = $this->getPasskeysData();
		$WAN[ 'reg_start' ] = $this->getPasskeyAdapter()->startRegistration(
			$this->getPasskeyAdapterContext(),
			$this->getSourceRepo()
		);
		$this->setPasskeysData( $WAN );

		return $WAN[ 'reg_start' ];
	}

	/**
	 * @throws \Exception
	 */
	public function startNewAuth() :array {
		$WAN = $this->getPasskeysData();
		$WAN[ 'auth_challenge' ] = $this->getPasskeyAdapter()->startAuthentication(
			$this->getPasskeyAdapterContext(),
			$this->getSourceRepo()
		);
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
			$credentialData = $this->getPasskeyAdapter()->verifyAuthentication(
				$rawJsonEncodedWanResponse,
				$this->getPasskeysData()[ 'auth_challenge' ] ?? [],
				$this->getPasskeyAdapterContext(),
				$this->getSourceRepo()
			);

			$this->getSourceRepo()->updateCredentialData( $credentialData, [
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

		try {
			$credentialData = $this->getPasskeyAdapter()->verifyRegistration(
				$rawJsonEncodedWanResponse,
				$this->getPasskeysData()[ 'reg_start' ] ?? [],
				$this->getPasskeyAdapterContext(),
				$this->getSourceRepo()
			);

			$this->getSourceRepo()->saveCredentialData( $credentialData );
			$this->getSourceRepo()->updateCredentialData( $credentialData, [
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

	protected function buildPasskeyAdapter() :PasskeyAdapterInterface {
		return new WebauthnLibAdapter();
	}

	private function getPasskeyAdapter() :PasskeyAdapterInterface {
		return $this->adapter ??= $this->buildPasskeyAdapter();
	}

	private function getPasskeyAdapterContext() :PasskeyAdapterContext {
		$user = $this->getUser();
		$rpId = (string)\wp_parse_url( Services::WpGeneral()->getHomeUrl(), \PHP_URL_HOST );

		return new PasskeyAdapterContext(
			$rpId,
			sprintf( '%s on %s', self::con()->labels->Name, Services::WpGeneral()->getSiteName() ),
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
