<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class Yubikey extends AbstractShieldProvider {

	protected const SLUG = 'yubi';
	public const OTP_LENGTH = 12;
	public const URL_YUBIKEY_VERIFY = 'https://api.yubico.com/wsapi/2.0/verify';

	public function getJavascriptVars() :array {
		return [
			'ajax' => [
				'profile_yubikey_toggle' => ActionData::Build( Actions\MfaYubikeyToggle::class ),
			],
		];
	}

	protected function getUserProfileFormRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getUserProfileFormRenderData(),
			[
				'vars'    => [
					'yubi_ids' => $this->getYubiIds(),
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
					'provided_by'           => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ),
						$this->con()->getHumanName() ),
					'remove_more_info'      => __( 'Understand how to remove Google Authenticator', 'wp-simple-firewall' )
				],
			]
		);
	}

	private function getYubiIds() :array {
		return array_filter( array_map( 'trim', explode( ',', $this->getSecret() ) ) );
	}

	public function isProfileActive() :bool {
		return count( $this->getYubiIds() ) > 0;
	}

	protected function processOtp( string $otp ) :bool {
		$valid = false;

		foreach ( $this->getYubiIds() as $key ) {
			if ( strpos( $otp, $key ) === 0 && $this->sendYubiOtpRequest( $otp ) ) {
				$valid = true;
				break;
			}
			if ( !$this->con()->isPremiumActive() ) { // Test 1 key if not Pro
				break;
			}
		}

		return $valid;
	}

	private function sendYubiOtpRequest( string $otp ) :bool {
		$otp = trim( $otp );
		$success = false;

		if ( preg_match( '#^[a-z]{44}$#', $otp ) ) {
			// 2021-09-27: API requires at least 16 chars in the nonce, or it fails.
			$parts = [
				'otp'   => $otp,
				'nonce' => md5( uniqid( Services::Request()->getID() ) ),
				'id'    => $this->opts()->getYubikeyAppId()
			];

			$response = Services::HttpRequest()->getContent( URL::Build( self::URL_YUBIKEY_VERIFY, $parts ) );

			unset( $parts[ 'id' ] );
			$parts[ 'status' ] = 'OK';

			$success = true;
			foreach ( $parts as $key => $value ) {
				if ( !preg_match( sprintf( '#%s=%s#', $key, $value ), $response ) ) {
					$success = false;
					break;
				}
			}
		}

		return $success;
	}

	public function toggleRegisteredYubiID( string $keyOrOTP ) :StdResponse {
		$response = new StdResponse();
		$response->success = true;

		if ( empty( $keyOrOTP ) ) {
			$response->success = false;
			$response->error_text = 'One-Time Password was empty';
		}
		elseif ( strlen( $keyOrOTP ) < self::OTP_LENGTH ) {
			$response->success = false;
			$response->error_text = 'One-Time Password was too short';
		}
		else {
			$keyID = substr( $keyOrOTP, 0, self::OTP_LENGTH );
			$IDs = $this->getYubiIds();

			if ( in_array( $keyID, $IDs ) ) {
				$response->success = true;
				$IDs = Services::DataManipulation()->removeFromArrayByValue( $IDs, $keyID );
				$response->msg_text = sprintf(
					__( '%s was removed from your profile.', 'wp-simple-firewall' ),
					__( 'Yubikey Device', 'wp-simple-firewall' ).sprintf( ' (%s)', $keyID )
				);
			}
			elseif ( !$this->sendYubiOtpRequest( $keyOrOTP ) ) {
				// If we're going to add the device, we test it
				$response->success = false;
				$response->error_text = 'Failed to verify One-Time Password from device';
			}
			else {
				$IDs[] = $keyID;
				$response->msg_text = sprintf(
					__( '%s was added to your profile.', 'wp-simple-firewall' ),
					__( 'Yubikey Device', 'wp-simple-firewall' ).sprintf( ' (%s)', $keyID )
				);
			}

			if ( !$this->sendYubiOtpRequest( $keyOrOTP ) ) {
				$response->error_text = 'One-Time Password verification failed';
			}
			$response->success = true;

			$this->setSecret( implode( ',', array_unique( array_filter( $IDs ) ) ) );
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
			'help_link'   => 'https://shsec.io/4i'
		];
	}

	public function isProviderEnabled() :bool {
		return $this->getOptions()->isEnabledYubikey();
	}

	protected function hasValidSecret() :bool {
		$secret = $this->getSecret();
		return count( array_filter(
				explode( ',', is_string( $secret ) ? $secret : '' ),
				function ( $yubiID ) {
					return (bool)preg_match( sprintf( '#^[a-z]{%s}$#', self::OTP_LENGTH ), $yubiID );
				}
			) ) > 0;
	}

	public function getProviderName() :string {
		return 'Yubikey';
	}
}