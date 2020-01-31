<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class Yubikey extends BaseProvider {

	const SLUG = 'yubi';
	const OTP_LENGTH = 12;
	/**
	 * @const string
	 */
	const URL_YUBIKEY_VERIFY = 'https://api.yubico.com/wsapi/2.0/verify';

	/**
	 * @inheritDoc
	 */
	public function renderUserProfileOptions( \WP_User $oUser ) {
		$oCon = $this->getCon();
		$oWpUsers = Services::WpUsers();

		$bValidatedProfile = $this->hasValidatedProfile( $oUser );
		$aData = [
			'has_validated_profile' => $bValidatedProfile,
			'is_my_user_profile'    => ( $oUser->ID == $oWpUsers->getCurrentWpUserId() ),
			'i_am_valid_admin'      => $oCon->isPluginAdmin(),
			'user_to_edit_is_admin' => $oWpUsers->isUserAdmin( $oUser ),
			'strings'               => [
				'description_otp_code'     => __( 'This is your unique Yubikey Device ID.', 'wp-simple-firewall' ),
				'description_otp_code_ext' => '['.__( 'Pro Only', 'wp-simple-firewall' ).'] '
											  .__( 'Multiple Yubikey Device IDs are separated by a comma.', 'wp-simple-firewall' ),
				'description_otp'          => __( 'Provide a One Time Password from your Yubikey.', 'wp-simple-firewall' ),
				'description_otp_ext'      => $bValidatedProfile ?
					__( 'This will remove the Yubikey Device ID from your profile.', 'wp-simple-firewall' )
					: __( 'This will add the Yubikey Device ID to your profile.', 'wp-simple-firewall' ),
				'description_otp_ext_2'    => $bValidatedProfile ?
					'['.__( 'Pro Only', 'wp-simple-firewall' ).'] '.__( 'If you provide a OTP from an alternative Yubikey device, it will also be added to your profile.', 'wp-simple-firewall' )
					: '',
				'label_enter_code'         => __( 'Yubikey ID', 'wp-simple-firewall' ),
				'label_enter_otp'          => __( 'Yubikey OTP', 'wp-simple-firewall' ),
				'title'                    => __( 'Yubikey Authentication', 'wp-simple-firewall' ),
				'cant_add_other_user'      => sprintf( __( "Sorry, %s may not be added to another user's account.", 'wp-simple-firewall' ), 'Yubikey' ),
				'cant_remove_admins'       => sprintf( __( "Sorry, %s may only be removed from another user's account by a Security Administrator.", 'wp-simple-firewall' ), __( 'Yubikey', 'wp-simple-firewall' ) ),
				'provided_by'              => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ), $oCon->getHumanName() ),
				'remove_more_info'         => sprintf( __( 'Understand how to remove Google Authenticator', 'wp-simple-firewall' ) )
			],
			'data'                  => [
				'otp_field_name' => $this->getLoginFormParameter(),
				'secret'         => str_replace( ',', ', ', $this->getSecret( $oUser ) ),
			]
		];

		return $this->getMod()
					->renderTemplate(
						'/snippets/user/profile/mfa/mfa_yubikey.twig',
						$aData,
						true
					);
	}

	/**
	 * @inheritDoc
	 */
	public function handleUserProfileSubmit( \WP_User $oUser ) {

		// If it's your own account, you CANT do anything without your OTP (except turn off via email).
		$sOtp = trim( (string)$this->fetchCodeFromRequest() );

		// At this stage, if the OTP was empty, then we have no further processing to do.
		if ( empty( $sOtp ) ) {
			return;
		}

		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();

		if ( !$this->sendYubiOtpRequest( $sOtp ) ) {
			$this->getMod()->setFlashAdminNotice(
				__( 'One Time Password (OTP) was not valid.', 'wp-simple-firewall' ).' '.__( 'Please try again.', 'wp-simple-firewall' ),
				true
			);
			return;
		}

		/*
		 * How we proceed depends on :
		 * 1) Is the OTP for a registered ID - if so, remove it; If not, add it;
		 * 2) Is this a premium Shield installation - if so, multiple yubikeys are permitted
		 */

		$sYubiId = substr( $sOtp, 0, self::OTP_LENGTH );

		$bError = false;
		if ( in_array( $sYubiId, $this->getYubiIds( $oUser ) ) ) {
			$this->removeYubiIdFromProfile( $oUser, $sYubiId );
			$sMsg = sprintf(
				__( '%s was removed from your profile.', 'wp-simple-firewall' ),
				__( 'Yubikey Device', 'wp-simple-firewall' ).sprintf( ' "%s"', $sYubiId )
			);
		}
		elseif ( count( $this->getYubiIds( $oUser ) ) == 0 || $this->getCon()->isPremiumActive() ) {
			$this->addYubiIdToProfile( $oUser, $sYubiId );
			$sMsg = sprintf(
				__( '%s was added to your profile.', 'wp-simple-firewall' ),
				__( 'Yubikey Device', 'wp-simple-firewall' ).sprintf( ' (%s)', $sYubiId )
			);
		}
		else {
			$bError = true;
			$sMsg = __( 'No changes were made to your Yubikey configuration', 'wp-simple-firewall' );
		}

		$this->setProfileValidated( $oUser, $this->hasValidSecret( $oUser ) );
		$oMod->setFlashAdminNotice( $sMsg, $bError );
	}

	/**
	 * @param \WP_User $oUser
	 * @return array
	 */
	protected function getYubiIds( \WP_User $oUser ) {
		return explode( ',', $this->getSecret( $oUser ) );
	}

	/**
	 * @param \WP_User $oUser
	 * @param string   $sOneTimePassword
	 * @return bool
	 */
	protected function processOtp( $oUser, $sOneTimePassword ) {
		$bSuccess = false;

		foreach ( $this->getYubiIds( $oUser ) as $sKey ) {
			if ( strpos( $sOneTimePassword, $sKey ) === 0
				 && $this->sendYubiOtpRequest( $sOneTimePassword ) ) {
				$bSuccess = true;
				break;
			}
			if ( !$this->getCon()->isPremiumActive() ) { // Test 1 key if not Pro
				break;
			}
		}

		return $bSuccess;
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
	 * @param \WP_User $oUser
	 * @param string   $sNewKey
	 * @return $this
	 */
	protected function addYubiIdToProfile( $oUser, $sNewKey ) {
		$aKeys = $this->getYubiIds( $oUser );
		$aKeys[] = $sNewKey;
		return $this->storeYubiIdInProfile( $oUser, $aKeys );
	}

	/**
	 * @param \WP_User $oUser
	 * @param string   $sKey
	 * @return $this
	 */
	protected function removeYubiIdFromProfile( $oUser, $sKey ) {
		$aKeys = Services::DataManipulation()
						 ->removeFromArrayByValue( $this->getYubiIds( $oUser ), $sKey );
		return $this->storeYubiIdInProfile( $oUser, $aKeys );
	}

	/**
	 * @param \WP_User $oUser
	 * @param array    $aKeys
	 * @return $this
	 */
	private function storeYubiIdInProfile( $oUser, $aKeys ) {
		parent::setSecret( $oUser, implode( ',', array_unique( array_filter( $aKeys ) ) ) );
		return $this;
	}

	/**
	 * @param \WP_User $oUser
	 * @param bool     $bIsSuccess
	 */
	protected function auditLogin( $oUser, $bIsSuccess ) {
		$this->getCon()->fireEvent(
			$bIsSuccess ? 'yubikey_verified' : 'yubikey_fail',
			[
				'audit' => [
					'user_login' => $oUser->user_login,
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

	/**
	 * @param string $sSecret
	 * @return bool
	 */
	protected function isSecretValid( $sSecret ) {
		$bValid = parent::isSecretValid( $sSecret );
		if ( $bValid ) {
			foreach ( explode( ',', $sSecret ) as $sId ) {
				$bValid = $bValid &&
						  preg_match( sprintf( '#^[a-z]{%s}$#', self::OTP_LENGTH ), $sId );
			}
		}
		return $bValid;
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	public function isProviderAvailable( \WP_User $oUser ) {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isEnabledYubikey();
	}
}