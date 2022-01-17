<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class Yubikey extends BaseProvider {

	const SLUG = 'yubi';
	const OTP_LENGTH = 12;
	const URL_YUBIKEY_VERIFY = 'https://api.yubico.com/wsapi/2.0/verify';

	public function getJavascriptVars() :array {
		return [
			'ajax' => [
				'user_yubikey_toggle' => $this->getMod()->getAjaxActionData( 'user_yubikey_toggle' ),
				'user_yubikey_remove' => $this->getMod()->getAjaxActionData( 'user_yubikey_remove' )
			],
		];
	}

	protected function getProviderSpecificRenderData( \WP_User $user ) :array {
		$con = $this->getCon();
		return [
			'vars'    => [
				'yubi_ids' => $this->getYubiIds( $user ),
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
				'provided_by'           => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ), $con->getHumanName() ),
				'remove_more_info'      => __( 'Understand how to remove Google Authenticator', 'wp-simple-firewall' )
			],
		];
	}

	private function getYubiIds( \WP_User $user ) :array {
		return array_filter( array_map( 'trim', explode( ',', $this->getSecret( $user ) ) ) );
	}

	protected function processOtp( \WP_User $user, string $otp ) :bool {
		$valid = false;

		foreach ( $this->getYubiIds( $user ) as $sKey ) {
			if ( strpos( $otp, $sKey ) === 0 && $this->sendYubiOtpRequest( $otp ) ) {
				$valid = true;
				break;
			}
			if ( !$this->getCon()->isPremiumActive() ) { // Test 1 key if not Pro
				break;
			}
		}

		return $valid;
	}

	private function sendYubiOtpRequest( string $otp ) :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		$otp = trim( $otp );
		$success = false;

		if ( preg_match( '#^[a-z]{44}$#', $otp ) ) {
			// 2021-09-27: API requires at least 16 chars in the nonce, or it fails.
			$parts = [
				'otp'   => $otp,
				'nonce' => md5( uniqid( Services::Request()->getID() ) ),
				'id'    => $opts->getYubikeyAppId()
			];

			$response = Services::HttpRequest()->getContent( add_query_arg( $parts, self::URL_YUBIKEY_VERIFY ) );

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

	public function toggleRegisteredYubiID( \WP_User $user, string $keyOrOTP ) :StdResponse {
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
			$IDs = $this->getYubiIds( $user );

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

			$this->setSecret( $user, implode( ',', array_unique( array_filter( $IDs ) ) ) );
		}

		return $response;
	}

	public function getFormField() :array {
		return [
			'name'        => $this->getLoginFormParameter(),
			'type'        => 'text',
			'placeholder' => __( 'Use your Yubikey to generate a new code.', 'wp-simple-firewall' ),
			'value'       => '',
			'text'        => __( 'Yubikey OTP', 'wp-simple-firewall' ),
			'help_link'   => 'https://shsec.io/4i'
		];
	}

	public function isProviderEnabled() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledYubikey();
	}

	/**
	 * @param string $secret
	 * @return bool
	 */
	protected function isSecretValid( $secret ) {
		$bValid = parent::isSecretValid( $secret );
		if ( $bValid ) {
			foreach ( explode( ',', $secret ) as $sId ) {
				$bValid = $bValid &&
						  preg_match( sprintf( '#^[a-z]{%s}$#', self::OTP_LENGTH ), $sId );
			}
		}
		return $bValid;
	}

	public function getProviderName() :string {
		return 'Yubikey';
	}
}