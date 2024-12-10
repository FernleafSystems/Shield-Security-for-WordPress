<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\InvalidYubikeyAppConfiguration;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\MfaRecordsForDisplay;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\MfaRecordsHandler;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\PasswordGenerator;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class Yubikey extends AbstractShieldProviderMfaDB {

	protected const SLUG = 'yubi';
	public const OTP_LENGTH = 12;
	public const URL_YUBIKEY_VERIFY = 'https://api.yubico.com/wsapi/2.0/verify';

	public static function ProviderEnabled() :bool {
		$opts = self::con()->opts;
		return parent::ProviderEnabled() && $opts->optIs( 'enable_yubikey', 'Y' )
			   && !empty( $opts->optGet( 'yubikey_app_id' ) ) && !empty( $opts->optGet( 'yubikey_api_key' ) );
	}

	protected function maybeMigrate() :void {
		$meta = self::con()->user_metas->for( $this->getUser() );
		$legacySecret = $meta->yubi_secret;
		if ( !empty( $legacySecret ) ) {

			$ids = \array_filter( \array_map( '\trim', \explode( ',', $legacySecret ) ) );
			if ( !self::con()->caps->hasCap( '2fa_multi_yubikey' ) ) {
				$ids = \array_slice( $ids, 0, 1 );
			}
			foreach ( $ids as $id ) {
				$this->createNewSecretRecord( $id, 'Yubikey' );
			}
			unset( $meta->yubi_secret );
			unset( $meta->yubi_validated );
		}
	}

	public function getJavascriptVars() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getJavascriptVars(),
			[
				'ajax'    => [
					'profile_yubikey_toggle' => ActionData::Build( Actions\MfaYubikeyToggle::class ),
				],
				'strings' => [
					'invalid_otp' => __( "This doesn't appear to be a valid Yubikey OTP" ),
				],
				'vars'    => [
					'registered_yubikeys' => \array_map(
						function ( $record ) {
							return \substr( $record->unique_id, 0, self::OTP_LENGTH );
						},
						$this->loadMfaRecords()
					),
				],
			]
		);
	}

	protected function getUserProfileFormRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getUserProfileFormRenderData(),
			[
				'vars'    => [
					'yubikeys' => ( new MfaRecordsForDisplay() )->run( $this->loadMfaRecords() ),
				],
				'strings' => [
					'registered_yubi_ids'   => __( 'Registered Yubikey devices', 'wp-simple-firewall' ),
					'no_active_yubi_ids'    => __( 'There are no registered Yubikey devices on this profile.', 'wp-simple-firewall' ),
					'placeholder_enter_otp' => __( 'Enter One Time Password From Yubikey', 'wp-simple-firewall' ),
					'enter_otp'             => __( 'To register a new Yubikey device, enter a One Time Password from the Yubikey.', 'wp-simple-firewall' ),
					'to_remove_device'      => __( 'To remove a Yubikey device, enter the registered device ID and save.', 'wp-simple-firewall' ),
					'multiple_for_pro'      => sprintf( '[%s] %s', __( 'Pro Only', 'wp-simple-firewall' ),
						__( 'You may add as many Yubikey devices to your profile as you need to.', 'wp-simple-firewall' ) ),
					'description_otp_code'  => __( 'This is your unique Yubikey Device ID.', 'wp-simple-firewall' ),
					'description_otp'       => __( 'Provide a One Time Password from your Yubikey.', 'wp-simple-firewall' ),
					'label_enter_code'      => __( 'Yubikey ID', 'wp-simple-firewall' ),
					'label_enter_otp'       => __( 'Yubikey OTP', 'wp-simple-firewall' ),
					'title'                 => __( 'Yubikey Authentication', 'wp-simple-firewall' ),
					'cant_add_other_user'   => sprintf( __( "Sorry, %s may not be added to another user's account.", 'wp-simple-firewall' ), 'Yubikey' ),
					'cant_remove_admins'    => sprintf( __( "Sorry, %s may only be removed from another user's account by a Security Administrator.", 'wp-simple-firewall' ), __( 'Yubikey', 'wp-simple-firewall' ) ),
					'provided_by'           => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ), self::con()->labels->Name ),
					'remove_more_info'      => __( 'Understand how to remove Google Authenticator', 'wp-simple-firewall' )
				],
			]
		);
	}

	protected function processOtp( string $otp ) :bool {
		$valid = false;
		foreach ( $this->loadMfaRecords() as $record ) {
			try {
				if ( \strpos( $otp, $record->unique_id ) === 0 && $this->sendYubiOtpRequest( $otp ) ) {
					$valid = true;
					( new MfaRecordsHandler() )->update( $record, [
						'used_at' => Services::Request()->ts()
					] );
					break;
				}
			}
			catch ( InvalidYubikeyAppConfiguration $e ) {
			}
		}
		return $valid;
	}

	/**
	 * @throws InvalidYubikeyAppConfiguration
	 */
	private function sendYubiOtpRequest( string $otp ) :bool {
		$otp = \trim( $otp );
		$success = false;

		if ( \preg_match( '#^[a-z]{44}$#', $otp ) ) {
			// 2021-09-27: API requires at least 16 chars in the nonce, or it fails.
			$parts = [
				'otp'   => $otp,
				'nonce' => \hash( 'md5', Services::Request()->getID().PasswordGenerator::Gen( 32, false, true, false ) ),
				'id'    => self::con()->opts->optGet( 'yubikey_app_id' )
			];

			$response = Services::HttpRequest()->getContent( URL::Build( self::URL_YUBIKEY_VERIFY, $parts ) );

			if ( \strpos( $response, 'status=NO_SUCH_CLIENT' ) ) {
				throw new InvalidYubikeyAppConfiguration();
			}

			unset( $parts[ 'id' ] );
			$parts[ 'status' ] = 'OK';

			$success = true;
			foreach ( $parts as $key => $value ) {
				if ( !\preg_match( sprintf( '#%s=%s#', $key, $value ), $response ) ) {
					$success = false;
					break;
				}
			}
		}

		return $success;
	}

	public function toggleRegisteredYubiID( string $keyOrOTP, string $label = '' ) :StdResponse {
		$response = new StdResponse();
		$response->success = true;

		$keyOrOTP = \trim( $keyOrOTP );

		if ( empty( $keyOrOTP ) ) {
			$response->success = false;
			$response->error_text = 'One-Time Password was empty';
		}
		elseif ( \strlen( $keyOrOTP ) < self::OTP_LENGTH ) {
			$response->success = false;
			$response->error_text = 'One-Time Password was too short';
		}
		else {
			$keyID = \substr( $keyOrOTP, 0, self::OTP_LENGTH );

			$deleted = false;
			foreach ( $this->loadMfaRecords() as $record ) {
				if ( $keyID === $record->unique_id ) {
					self::con()->db_con->mfa->getQueryDeleter()->deleteRecord( $record );
					$deleted = true;
					break;
				}
			}

			if ( $deleted ) {
				$response->msg_text = sprintf(
					__( '%s was removed from your profile.', 'wp-simple-firewall' ),
					__( 'Yubikey Device', 'wp-simple-firewall' ).' '.$keyID
				);
			}
			else {
				try {
					if ( !self::con()->caps->hasCap( '2fa_multi_yubikey' ) && $this->hasValidatedProfile() ) {
						throw new \Exception( 'Upgrade to add multiple Yubikeys to your profile.' );
					}

					if ( $this->sendYubiOtpRequest( $keyOrOTP ) ) {
						$this->createNewSecretRecord( $keyID, $label );
						$response->msg_text = sprintf(
							__( '%s was added to your profile.', 'wp-simple-firewall' ),
							__( 'Yubikey Device', 'wp-simple-firewall' ).sprintf( ' (%s)', $keyID )
						);
					}
					else {
						$response->success = false;
						$response->error_text = sprintf( '%s - %s.',
							__( 'Failed to verify One-Time Password', 'wp-simple-firewall' ),
							__( 'Please retry again', 'wp-simple-firewall' )
						);
					}
				}
				catch ( InvalidYubikeyAppConfiguration $e ) {
					$response->success = false;
					$response->error_text = sprintf( '%s - %s.',
						__( 'Failed to verify One-Time Password', 'wp-simple-firewall' ),
						__( 'Your Yubikey APP configuration may be invalid', 'wp-simple-firewall' )
					);
				}
				catch ( \Exception $e ) {
					$response->success = false;
					$response->error_text = $e->getMessage();
				}
			}
		}

		return $response;
	}

	public function getFormField() :array {
		return [
			'slug'        => static::ProviderSlug(),
			'name'        => $this->getLoginIntentFormParameter(),
			'type'        => 'text',
			'placeholder' => '',
			'value'       => '',
			'text'        => __( 'Yubikey OTP', 'wp-simple-firewall' ),
			'description' => __( 'Use your Yubikey to generate a new code', 'wp-simple-firewall' ),
			'help_link'   => 'https://clk.shldscrty.com/4i'
		];
	}

	public static function ProviderName() :string {
		return __( 'Yubikey', 'wp-simple-firewall' );
	}
}