<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_LoginProtect_Yubikey extends ICWP_WPSF_Processor_LoginProtect_IntentProviderBase {

	const OTP_LENGTH = 12;
	/**
	 * @const string
	 */
	const URL_YUBIKEY_VERIFY = 'https://api.yubico.com/wsapi/2.0/verify';

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param WP_User $oUser
	 */
	public function addOptionsToUserProfile( $oUser ) {
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

		echo $this->getMod()->renderTemplate( 'snippets/user_profile_yubikey.php', $aData );
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile,
	 * so we can use "current user" functions.  Otherwise we need to be careful of mixing up users.
	 * @param int $nSavingUserId
	 */
	public function handleUserProfileSubmit( $nSavingUserId ) {

		// If it's your own account, you CANT do anything without your OTP (except turn off via email).
		$sOtp = $this->fetchCodeFromRequest();

		// At this stage, if the OTP was empty, then we have no further processing to do.
		if ( empty( $sOtp ) ) {
			return;
		}

		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();

		if ( !$this->sendYubiOtpRequest( $sOtp ) ) {
			$oFO->setFlashAdminNotice(
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

		$oSavingUser = Services::WpUsers()->getUserById( $nSavingUserId );
		$sYubiId = $this->getYubiIdFromOtp( $sOtp );

		$bError = false;
		if ( $this->hasYubiIdInProfile( $oSavingUser, $sYubiId ) ) {
			$this->removeYubiIdFromProfile( $oSavingUser, $sYubiId );
			$sMsg = sprintf(
				__( '%s was removed from your profile.', 'wp-simple-firewall' ),
				__( 'Yubikey Device', 'wp-simple-firewall' ).sprintf( ' "%s"', $sYubiId )
			);
		}
		else if ( count( $this->getYubiIds( $oSavingUser ) ) == 0 || $oFO->isPremium() ) {
			$this->addYubiIdToProfile( $oSavingUser, $sYubiId );
			$sMsg = sprintf(
				__( '%s was added to your profile.', 'wp-simple-firewall' ),
				__( 'Yubikey Device', 'wp-simple-firewall' ).sprintf( ' (%s)', $sYubiId )
			);
		}
		else {
			$bError = true;
			$sMsg = __( 'No changes were made to your Yubikey configuration', 'wp-simple-firewall' );
		}

		$this->setProfileValidated( $oSavingUser, $this->hasValidSecret( $oSavingUser ) );
		$oFO->setFlashAdminNotice( $sMsg, $bError );
	}

	/**
	 * @param WP_User $oUser
	 * @return array
	 */
	protected function getYubiIds( WP_User $oUser ) {
		return explode( ',', parent::getSecret( $oUser ) );
	}

	/**
	 * @param string $sOTP
	 * @return string
	 */
	protected function getYubiIdFromOtp( $sOTP ) {
		return substr( $sOTP, 0, $this->getYubiOtpLength() );
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sKey
	 * @return bool
	 */
	protected function hasYubiIdInProfile( WP_User $oUser, $sKey ) {
		return in_array( $sKey, $this->getYubiIds( $oUser ) );
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sOneTimePassword
	 * @return bool
	 */
	protected function processOtp( $oUser, $sOneTimePassword ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		$bSuccess = false;

		$aYubiKeys = $this->getYubiIds( $oUser );

		// Only process the 1st secret if premium
		if ( !$oFO->isPremium() ) {
			$aYubiKeys = array_slice( $aYubiKeys, 0, 1 );
		}

		foreach ( $aYubiKeys as $sKey ) {
			$bSuccess = strpos( $sOneTimePassword, $sKey ) === 0
						&& $this->sendYubiOtpRequest( $sOneTimePassword );
			if ( $bSuccess ) {
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
		$sOTP = trim( $sOTP );
		$bSuccess = false;

		if ( preg_match( '#^[a-z]{44}$#', $sOTP ) ) {
			$aParts = [
				'otp'   => $sOTP,
				'nonce' => md5( uniqid( rand() ) ),
				'id'    => $this->getOption( 'yubikey_app_id' )
			];

			$sReqUrl = add_query_arg( $aParts, self::URL_YUBIKEY_VERIFY );
			$sYubiResponse = Services::HttpRequest()->getContent( $sReqUrl );

			unset( $aParts[ 'id' ] );
			$aParts[ 'status' ] = 'OK';

			$bSuccess = true;
			foreach ( $aParts as $sKey => $mVal ) {
				$bSuccess = $bSuccess && preg_match( sprintf( '#%s=%s#', $sKey, $mVal ), $sYubiResponse );
			}
		}

		return $bSuccess;
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sNewKey
	 * @return $this
	 */
	protected function addYubiIdToProfile( $oUser, $sNewKey ) {
		$aKeys = $this->getYubiIds( $oUser );
		$aKeys[] = $sNewKey;
		return $this->storeYubiIdInProfile( $oUser, $aKeys );
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sKey
	 * @return $this
	 */
	protected function removeYubiIdFromProfile( $oUser, $sKey ) {
		$aKeys = Services::DataManipulation()->removeFromArrayByValue( $this->getYubiIds( $oUser ), $sKey );
		return $this->storeYubiIdInProfile( $oUser, $aKeys );
	}

	/**
	 * @param WP_User $oUser
	 * @param array   $aKeys
	 * @return $this
	 */
	private function storeYubiIdInProfile( $oUser, $aKeys ) {
		parent::setSecret( $oUser, implode( ',', array_unique( array_filter( $aKeys ) ) ) );
		return $this;
	}

	/**
	 * @param WP_User $oUser
	 * @param bool    $bIsSuccess
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
	 * @param array $aFields
	 * @return array
	 */
	public function addLoginIntentField( $aFields ) {
		if ( $this->getCurrentUserHasValidatedProfile() ) {
			$aFields[] = [
				'name'        => $this->getLoginFormParameter(),
				'type'        => 'text',
				'placeholder' => __( 'Use your Yubikey to generate a new code.', 'wp-simple-firewall' ),
				'value'       => '',
				'text'        => __( 'Yubikey OTP', 'wp-simple-firewall' ),
				'help_link'   => 'https://icwp.io/4i'
			];
		}
		return $aFields;
	}

	/**
	 * @return string
	 */
	protected function getStub() {
		return ICWP_WPSF_Processor_LoginProtect_Track::Factor_Yubikey;
	}

	/**
	 * @param string $sSecret
	 * @return bool
	 */
	protected function isSecretValid( $sSecret ) {
		$bValid = parent::isSecretValid( $sSecret );
		if ( $bValid ) {
			foreach ( explode( ',', $sSecret ) as $sId ) {
				$bValid = $bValid && preg_match( sprintf( '#^[a-z]{%s}$#', $this->getYubiOtpLength() ), $sId );
			}
		}
		return $bValid;
	}

	/**
	 * @return int
	 */
	protected function getYubiOtpLength() {
		return self::OTP_LENGTH;
	}
}