<?php

use FernleafSystems\Wordpress\Services\Services;
use Dolondro\GoogleAuthenticator;

/**
 * @deprecated 8.6.0
 */
class ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator extends ICWP_WPSF_Processor_LoginProtect_IntentProviderBase {

	/**
	 * @var GoogleAuthenticator\Secret
	 */
	private $oWorkingSecret;

	/**
	 */
	public function run() {
		parent::run();
		if ( $this->getCon()->getShieldAction() == 'garemovalconfirm' ) {
			add_action( 'wp_loaded', [ $this, 'validateUserGaRemovalLink' ], 10 );
		}
	}

	/**
	 * @param \WP_User $oUser
	 * @return string
	 */
	public function getGaRegisterChartUrl( $oUser ) {
		if ( empty( $oUser ) ) {
			$sUrl = '';
		}
		else {
			$sUrl = ( new GoogleAuthenticator\QrImageGenerator\GoogleQrImageGenerator () )
				->generateUri(
					$this->getGaSecret( $oUser )
				);
		}
		return $sUrl;
	}

	/**
	 * The only thing we can do is REMOVE Google Authenticator from an account that is not our own
	 * But, only admins can do this.  If Security Admin feature is enabled, then only they can do it.
	 * @param int $nSavingUserId
	 */
	public function handleEditOtherUserProfileSubmit( $nSavingUserId ) {

		// Can only edit other users if you're admin/security-admin
		if ( $this->getCon()->isPluginAdmin() ) {
			$oWpUsers = Services::WpUsers();
			$oSavingUser = $oWpUsers->getUserById( $nSavingUserId );

			$sShieldTurnOff = Services::Request()->post( 'shield_turn_off_google_authenticator' );
			if ( !empty( $sShieldTurnOff ) && $sShieldTurnOff == 'Y' ) {

				$bPermissionToRemoveGa = true;
				// if the current user has Google Authenticator on THEIR account, process their OTP.
				$oCurrentUser = $oWpUsers->getCurrentWpUser();
				if ( $this->hasValidatedProfile( $oCurrentUser ) ) {
					$bPermissionToRemoveGa = $this->processOtp( $oCurrentUser, $this->fetchCodeFromRequest() );
				}

				if ( $bPermissionToRemoveGa ) {
					$this->processRemovalFromAccount( $oSavingUser );
					$sMsg = __( 'Google Authenticator was successfully removed from the account.', 'wp-simple-firewall' );
				}
				else {
					$sMsg = __( 'Google Authenticator could not be removed from the account - ensure your code is correct.', 'wp-simple-firewall' );
				}
				$this->getMod()->setFlashAdminNotice( $sMsg, $bPermissionToRemoveGa );
			}
		}
		else {
			// DO NOTHING EVER
		}
	}

	/**
	 * @param WP_User $oUser
	 * @return $this
	 */
	protected function processRemovalFromAccount( $oUser ) {
		$this->setProfileValidated( $oUser, false )
			 ->resetSecret( $oUser );
		return $this;
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile,
	 * so we can use "current user" functions.  Otherwise we need to be careful of mixing up users.
	 * @param int $nSavingUserId
	 */
	public function handleUserProfileSubmit( $nSavingUserId ) {
		$oWpUsers = Services::WpUsers();

		$oSavingUser = $oWpUsers->getUserById( $nSavingUserId );

		// If it's your own account, you CANT do anything without your OTP (except turn off via email).
		$sOtp = $this->fetchCodeFromRequest();
		$bValidOtp = $this->processOtp( $oSavingUser, $sOtp );

		$sMessageOtpInvalid = __( 'One Time Password (OTP) was not valid.', 'wp-simple-firewall' ).' '.__( 'Please try again.', 'wp-simple-firewall' );

		$sShieldTurnOff = Services::Request()->post( 'shield_turn_off_google_authenticator' );
		if ( !empty( $sShieldTurnOff ) && $sShieldTurnOff == 'Y' ) {

			$bError = false;
			if ( $bValidOtp ) {
				$this->processRemovalFromAccount( $oSavingUser );
				$sFlash = __( 'Google Authenticator was successfully removed from the account.', 'wp-simple-firewall' );
			}
			elseif ( empty( $sOtp ) ) {

				if ( $this->sendEmailConfirmationGaRemoval( $oSavingUser ) ) {
					$sFlash = __( 'An email has been sent to you in order to confirm Google Authenticator removal', 'wp-simple-firewall' );
				}
				else {
					$bError = true;
					$sFlash = __( 'We tried to send an email for you to confirm Google Authenticator removal but it failed.', 'wp-simple-firewall' );
				}
			}
			else {
				$bError = true;
				$sFlash = $sMessageOtpInvalid;
			}
			$this->getMod()->setFlashAdminNotice( $sFlash, $bError );
			return;
		}

		// At this stage, if the OTP was empty, then we have no further processing to do.
		if ( empty( $sOtp ) ) {
			return;
		}

		// We're trying to validate our OTP to activate our GA
		if ( !$this->hasValidatedProfile( $oSavingUser ) ) {

			if ( $bValidOtp ) {
				$this->setProfileValidated( $oSavingUser );
				$sFlash = sprintf(
					__( '%s was successfully added to your account.', 'wp-simple-firewall' ),
					__( 'Google Authenticator', 'wp-simple-firewall' )
				);
			}
			else {
				$this->resetSecret( $oSavingUser );
				$sFlash = $sMessageOtpInvalid;
			}
			$this->getMod()->setFlashAdminNotice( $sFlash, !$bValidOtp );
		}
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
				'value'       => '',
				'placeholder' => __( 'Please use your Google Authenticator App to retrieve your code.', 'wp-simple-firewall' ),
				'text'        => __( 'Google Authenticator Code', 'wp-simple-firewall' ),
				'help_link'   => 'https://shsec.io/wpsf42',
				'extras'      => [
					'onkeyup' => "this.value=this.value.replace(/[^\d]/g,'')"
				]
			];
		}
		return $aFields;
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	protected function sendEmailConfirmationGaRemoval( $oUser ) {
		$bSendSuccess = false;

		$aEmailContent = [];
		$aEmailContent[] = __( 'You have requested the removal of Google Authenticator from your WordPress account.', 'wp-simple-firewall' )
						   .__( 'Please click the link below to confirm.', 'wp-simple-firewall' );
		$aEmailContent[] = $this->generateGaRemovalConfirmationLink();

		$sRecipient = $oUser->get( 'user_email' );
		if ( Services::Data()->validEmail( $sRecipient ) ) {
			$sEmailSubject = __( 'Google Authenticator Removal Confirmation', 'wp-simple-firewall' );
			$bSendSuccess = $this->getEmailProcessor()
								 ->sendEmailWithWrap( $sRecipient, $sEmailSubject, $aEmailContent );
		}
		return $bSendSuccess;
	}

	/**
	 */
	public function validateUserGaRemovalLink() {
		// Must be already logged in for this link to work.
		$oWpCurrentUser = Services::WpUsers()->getCurrentWpUser();
		if ( empty( $oWpCurrentUser ) ) {
			return;
		}

		// Session IDs must be the same
		$sSessionId = Services::Request()->query( 'sessionid' );
		if ( empty( $sSessionId ) || ( $sSessionId !== $this->getCon()->getSessionId() ) ) {
			return;
		}

		$this->processRemovalFromAccount( $oWpCurrentUser );
		$this->getMod()
			 ->setFlashAdminNotice( __( 'Google Authenticator was successfully removed from this account.', 'wp-simple-firewall' ) );
		Services::Response()->redirectToAdmin();
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sOtpCode
	 * @return bool
	 */
	protected function processOtp( $oUser, $sOtpCode ) {
		return $this->validateGaCode( $oUser, $sOtpCode );
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sOtpCode
	 * @return bool
	 */
	public function validateGaCode( $oUser, $sOtpCode ) {
		$bValidOtp = false;
		if ( !empty( $sOtpCode ) && preg_match( '#^[0-9]{6}$#', $sOtpCode ) ) {
			try {
				$bValidOtp = ( new GoogleAuthenticator\GoogleAuthenticator() )
					->authenticate( $this->getSecret( $oUser ), $sOtpCode );
			}
			catch ( \Exception $oE ) {
			}
			catch ( \Psr\Cache\CacheException $oE ) {
			}
		}
		return $bValidOtp;
	}

	/**
	 * @param WP_User $oUser
	 * @param bool    $bIsSuccess
	 */
	protected function auditLogin( $oUser, $bIsSuccess ) {
		$this->getCon()->fireEvent(
			$bIsSuccess ? 'googleauth_verified' : 'googleauth_fail',
			[
				'audit' => [
					'user_login' => $oUser->user_login,
					'method'     => 'Google Authenticator',
				]
			]
		);
	}

	/**
	 * @return string
	 */
	protected function generateGaRemovalConfirmationLink() {
		$aQueryArgs = [
			'shield_action' => 'garemovalconfirm',
			'sessionid'     => $this->getCon()->getSessionId()
		];
		return add_query_arg( $aQueryArgs, Services::WpGeneral()->getAdminUrl() );
	}

	/**
	 * @param \WP_User $oUser
	 * @return string
	 */
	protected function genNewSecret( \WP_User $oUser ) {
		try {
			return $this->getGaSecret( $oUser )->getSecretKey();
		}
		catch ( \InvalidArgumentException $oE ) {
			return '';
		}
	}

	/**
	 * @param \WP_User $oUser
	 * @return GoogleAuthenticator\Secret
	 * @throws InvalidArgumentException
	 */
	private function getGaSecret( $oUser ) {
		if ( !isset( $this->oWorkingSecret ) ) {
			$this->oWorkingSecret = ( new GoogleAuthenticator\SecretFactory() )
				->create(
					sanitize_user( $oUser->user_login ),
					preg_replace( '#[^0-9a-z]#i', '', Services::WpGeneral()->getSiteName() )
				);
		}
		return $this->oWorkingSecret;
	}

	/**
	 * @param \WP_User $oUser
	 * @return string
	 */
	protected function getSecret( WP_User $oUser ) {
		$sSec = parent::getSecret( $oUser );
		return empty( $sSec ) ? $this->resetSecret( $oUser ) : $sSec;
	}

	/**
	 * @return string
	 */
	protected function getStub() {
		return ICWP_WPSF_Processor_LoginProtect_Track::Factor_Google_Authenticator;
	}

	/**
	 * @param string $sSecret
	 * @return bool
	 */
	protected function isSecretValid( $sSecret ) {
		return parent::isSecretValid( $sSecret ) && ( strlen( $sSecret ) == 16 );
	}
}