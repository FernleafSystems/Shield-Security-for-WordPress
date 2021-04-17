<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class Yubikey extends BaseProvider {

	const SLUG = 'yubi';
	const OTP_LENGTH = 12;
	const URL_YUBIKEY_VERIFY = 'https://api.yubico.com/wsapi/2.0/verify';

	public function getJavascriptVars() :array {
		return [
			'ajax'           => [
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
				'registered_yubi_ids'  => __( 'Registered Yubikey devices', 'wp-simple-firewall' ),
				'no_active_yubi_ids'   => __( 'There are no registered Yubikey devices on this profile.', 'wp-simple-firewall' ),
				'enter_otp'            => __( 'To register a new Yubikey device, enter a One Time Password from the Yubikey.', 'wp-simple-firewall' ),
				'to_remove_device'     => __( 'To remove a Yubikey device, enter the registered device ID and save.', 'wp-simple-firewall' ),
				'multiple_for_pro'     => sprintf( '[%s] %s', __( 'Pro Only', 'wp-simple-firewall' ),
					__( 'You may add as many Yubikey devices to your profile as you need to.', 'wp-simple-firewall' ) ),
				'description_otp_code' => __( 'This is your unique Yubikey Device ID.', 'wp-simple-firewall' ),
				'description_otp'      => __( 'Provide a One Time Password from your Yubikey.', 'wp-simple-firewall' ),
				'label_enter_code'     => __( 'Yubikey ID', 'wp-simple-firewall' ),
				'label_enter_otp'      => __( 'Yubikey OTP', 'wp-simple-firewall' ),
				'title'                => __( 'Yubikey Authentication', 'wp-simple-firewall' ),
				'cant_add_other_user'  => sprintf( __( "Sorry, %s may not be added to another user's account.", 'wp-simple-firewall' ), 'Yubikey' ),
				'cant_remove_admins'   => sprintf( __( "Sorry, %s may only be removed from another user's account by a Security Administrator.", 'wp-simple-firewall' ), __( 'Yubikey', 'wp-simple-firewall' ) ),
				'provided_by'          => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ), $con->getHumanName() ),
				'remove_more_info'     => sprintf( __( 'Understand how to remove Google Authenticator', 'wp-simple-firewall' ) )
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function handleUserProfileSubmit( \WP_User $user ) {

		// If it's your own account, you CANT do anything without your OTP (except turn off via email).
		$sOtpOrDeviceId = trim( (string)$this->fetchCodeFromRequest() );
		if ( empty( $sOtpOrDeviceId ) ) {
			return;
		}

		$bError = true;
		$aRegisteredDevices = $this->getYubiIds( $user );

		if ( strlen( $sOtpOrDeviceId ) < self::OTP_LENGTH ) {
			$sMsg = __( 'The Yubikey device ID was not valid.', 'wp-simple-firewall' )
					.' '.__( 'Please try again.', 'wp-simple-firewall' );
		}
		else {
			$sDeviceId = substr( $sOtpOrDeviceId, 0, self::OTP_LENGTH );
			$bDeviceRegistered = in_array( $sDeviceId, $aRegisteredDevices );

			if ( $bDeviceRegistered || strlen( $sOtpOrDeviceId ) == self::OTP_LENGTH ) { // attempt to remove device

				if ( $bDeviceRegistered ) {
					$this->addRemoveRegisteredYubiId( $user, $sDeviceId, false );
					$sMsg = sprintf(
						__( '%s was removed from your profile.', 'wp-simple-firewall' ),
						__( 'Yubikey Device', 'wp-simple-firewall' ).sprintf( ' (%s)', $sDeviceId )
					);
					$bError = false;
				}
				else {
					$sMsg = __( "That Yubikey device ID wasn't found on your profile", 'wp-simple-firewall' );
				}
			}
			elseif ( $this->sendYubiOtpRequest( $sOtpOrDeviceId ) ) { // A full OTP was provided so we're adding a new one
				if ( count( $aRegisteredDevices ) == 0 || $this->getCon()->isPremiumActive() ) {
					$this->addRemoveRegisteredYubiId( $user, $sDeviceId, true );
					$sMsg = sprintf(
						__( '%s was added to your profile.', 'wp-simple-firewall' ),
						__( 'Yubikey Device', 'wp-simple-firewall' ).sprintf( ' (%s)', $sDeviceId )
					);
					$bError = false;
				}
				else {
					$sMsg = __( 'No further Yubikey devices may be added to your account at this time.', 'wp-simple-firewall' );
				}
			}
			else {
				$sMsg = __( 'One Time Password (OTP) was not valid.', 'wp-simple-firewall' )
						.' '.__( 'Please try again.', 'wp-simple-firewall' );
			}
		}

		$this->setProfileValidated( $user, $this->hasValidSecret( $user ) );
		$this->getMod()->setFlashAdminNotice( $sMsg, $bError );
	}

	/**
	 * @param \WP_User $user
	 * @return array
	 */
	private function getYubiIds( \WP_User $user ) {
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

	/**
	 * @param string $otp
	 * @return bool
	 */
	private function sendYubiOtpRequest( $otp ) {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		$otp = trim( $otp );
		$success = false;

		if ( preg_match( '#^[a-z]{44}$#', $otp ) ) {
			$parts = [
				'otp'   => $otp,
				'nonce' => md5( uniqid( $this->getCon()->getUniqueRequestId() ) ),
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

	public function toggleRegisteredYubiID( \WP_User $user, string $key ) :StdResponse {
		$response = new StdResponse();
		$response->success = true;

		if ( strlen( $key ) > self::OTP_LENGTH ) {
			$key = substr( $key, 0, self::OTP_LENGTH );
		}
		$IDs = $this->getYubiIds( $user );

		if ( in_array( $key, $IDs ) ) {
			$IDs = Services::DataManipulation()->removeFromArrayByValue( $IDs, $key );
			$response->msg_text = sprintf(
				__( '%s was removed from your profile.', 'wp-simple-firewall' ),
				__( 'Yubikey Device', 'wp-simple-firewall' ).sprintf( ' (%s)', $key )
			);
		}
		else {
			$IDs[] = $key;
			$response->msg_text = sprintf(
				__( '%s was added to your profile.', 'wp-simple-firewall' ),
				__( 'Yubikey Device', 'wp-simple-firewall' ).sprintf( ' (%s)', $key )
			);
		}

		$this->setSecret( $user, implode( ',', array_unique( array_filter( $IDs ) ) ) );

		return $response;
	}

	/**
	 * @param \WP_User $user
	 * @param string   $key
	 * @param bool     $add - true to add; false to remove
	 * @return $this
	 */
	public function addRemoveRegisteredYubiId( \WP_User $user, string $key, $add = true ) {
		$IDs = $this->getYubiIds( $user );

		if ( strlen( $key ) > self::OTP_LENGTH ) {
			$key = substr( $key, 0, self::OTP_LENGTH );
		}

		if ( $add ) {
			$IDs[] = $key;
		}
		else {
			$IDs = Services::DataManipulation()->removeFromArrayByValue( $IDs, $key );
		}
		return $this->setSecret( $user, implode( ',', array_unique( array_filter( $IDs ) ) ) );
	}

	/**
	 * @param \WP_User $user
	 * @param bool     $bIsSuccess
	 */
	protected function auditLogin( \WP_User $user, bool $bIsSuccess ) {
		$this->getCon()->fireEvent(
			$bIsSuccess ? 'yubikey_verified' : 'yubikey_fail',
			[
				'audit' => [
					'user_login' => $user->user_login,
					'method'     => 'Yubikey',
				]
			]
		);
	}

	/**
	 * @return array
	 */
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