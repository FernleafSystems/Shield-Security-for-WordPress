<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class Yubikey extends BaseProvider {

	const SLUG = 'yubi';
	const OTP_LENGTH = 12;
	const URL_YUBIKEY_VERIFY = 'https://api.yubico.com/wsapi/2.0/verify';

	public function setupProfile() {
		add_action( 'admin_enqueue_scripts', function ( $sHook ) {
			if ( in_array( $sHook, [ 'profile.php', ] ) ) {
				$this->enqueueYubikeyJS();
			}
		} );
	}

	/**
	 * Enqueue the Javascript for removing Yubikey
	 */
	private function enqueueYubikeyJS() {
		$oCon = $this->getCon();
		$sScript = 'shield-userprofile';
		wp_enqueue_script(
			$oCon->prefix( $sScript ),
			$oCon->getPluginUrl_Js( $sScript ),
			[ 'jquery', $oCon->prefix( 'global-plugin' ) ]
		);
		wp_localize_script(
			$oCon->prefix( $sScript ),
			'icwp_wpsf_vars_profileyubikey',
			[ 'yubikey_remove' => $this->getMod()->getAjaxActionData( 'yubikey_remove' ) ]
		);
	}

	public function renderUserProfileOptions( \WP_User $user ) :string {
		$con = $this->getCon();

		$aData = [
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

		return $this->getMod()
					->renderTemplate(
						'/snippets/user/profile/mfa/mfa_yubikey.twig',
						Services::DataManipulation()->mergeArraysRecursive( $this->getCommonData( $user ), $aData ),
						true
					);
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
	 * @param string $sOTP
	 * @return bool
	 */
	private function sendYubiOtpRequest( $sOTP ) {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$sOTP = trim( $sOTP );
		$bSuccess = false;

		if ( preg_match( '#^[a-z]{44}$#', $sOTP ) ) {
			$aParts = [
				'otp'   => $sOTP,
				'nonce' => md5( uniqid( $this->getCon()->getUniqueRequestId() ) ),
				'id'    => $oOpts->getYubikeyAppId()
			];

			$sResp = Services::HttpRequest()->getContent(
				add_query_arg( $aParts, self::URL_YUBIKEY_VERIFY )
			);

			unset( $aParts[ 'id' ] );
			$aParts[ 'status' ] = 'OK';

			$bSuccess = true;
			foreach ( $aParts as $sKey => $mVal ) {
				if ( !preg_match( sprintf( '#%s=%s#', $sKey, $mVal ), $sResp ) ) {
					$bSuccess = false;
					break;
				}
			}
		}

		return $bSuccess;
	}

	/**
	 * @param \WP_User $user
	 * @param string   $sKey
	 * @param bool     $bAdd - true to add; false to remove
	 * @return $this
	 */
	public function addRemoveRegisteredYubiId( \WP_User $user, $sKey, $bAdd = true ) {
		$aIDs = $this->getYubiIds( $user );
		if ( $bAdd ) {
			$aIDs[] = $sKey;
		}
		else {
			$aIDs = Services::DataManipulation()->removeFromArrayByValue( $aIDs, $sKey );
		}
		return $this->setSecret( $user, implode( ',', array_unique( array_filter( $aIDs ) ) ) );
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
	public function getFormField() {
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
}