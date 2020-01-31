<?php

use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 8.6.0
 */
class ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth extends ICWP_WPSF_Processor_LoginProtect_IntentProviderBase {

	/**
	 * @param WP_User|WP_Error|null $oUser
	 * @return WP_Error|WP_User|null    - WP_User when the login success AND the IP is authenticated. null when login
	 *                                  not successful but IP is valid. WP_Error otherwise.
	 */
	public function processLoginAttempt( $oUser ) {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();

		if ( !$this->isLoginCaptured() && $oUser instanceof WP_User
			 && $this->hasValidatedProfile( $oUser ) && !$oMod->canUserMfaSkip( $oUser ) ) {

			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Update $oUpd */
			$oUpd = $oMod->getDbHandler_Sessions()->getQueryUpdater();
			$oUpd->setLoginIntentCodeEmail( $oMod->getSession(), $this->getSecret( $oUser ) );

			// Now send email with authentication link for user.
			$this->sendEmailTwoFactorVerify( $oUser )
				 ->setLoginCaptured();
		}
		return $oUser;
	}

	/**
	 * @param WP_User $oUser
	 * @param bool    $bIsSuccess
	 */
	protected function auditLogin( $oUser, $bIsSuccess ) {
		$this->getCon()->fireEvent(
			$bIsSuccess ? 'email_verified' : 'email_fail',
			[
				'audit' => [
					'user_login' => $oUser->user_login,
					'method'     => 'Email',
				]
			]
		);
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sOtpCode
	 * @return bool
	 */
	protected function processOtp( $oUser, $sOtpCode ) {
		$bValid = !empty( $sOtpCode ) && ( $sOtpCode == $this->getStoredSessionHashCode() );
		if ( $bValid ) {
			/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
			$oMod = $this->getMod();
			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Update $oUpd */
			$oUpd = $oMod->getDbHandler_Sessions()->getQueryUpdater();
			$oUpd->clearLoginIntentCodeEmail( $oMod->getSession() );
		}
		return $bValid;
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
				'value'       => $this->fetchCodeFromRequest(),
				'placeholder' => __( 'This code was just sent to your registered Email address.', 'wp-simple-firewall' ),
				'text'        => __( 'Email OTP', 'wp-simple-firewall' ),
				'help_link'   => 'https://shsec.io/3t'
			];
		}
		return $aFields;
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	protected function hasValidatedProfile( $oUser ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		// Currently it's a global setting but this will evolve to be like Google Authenticator so that it's a user meta
		return ( $oFO->isEmailAuthenticationActive() && $this->isSubjectToEmailAuthentication( $oUser ) );
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	private function isSubjectToEmailAuthentication( $oUser ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		return count( array_intersect( $oFO->getEmail2FaRoles(), $oUser->roles ) ) > 0;
	}

	/**
	 * We don't use user meta as it's dependent on the particular user sessions in-use
	 * @param \WP_User $oUser
	 * @return string
	 */
	protected function getSecret( \WP_User $oUser ) {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		return strtoupper( substr(
			hash_hmac( 'sha1', $this->getCon()->getUniqueRequestId(), $oMod->getTwoAuthSecretKey() ),
			0, 6
		) );
	}

	/**
	 * @return string The unique 2FA 6-digit code
	 */
	protected function getStoredSessionHashCode() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->hasSession() ? $oFO->getSession()->getLoginIntentCodeEmail() : '';
	}

	/**
	 * @param string $sSecret
	 * @return bool
	 */
	protected function isSecretValid( $sSecret ) {
		$sHash = $this->getStoredSessionHashCode();
		return !empty( $sHash );
	}

	/**
	 * @param \WP_User $oUser
	 * @return $this
	 */
	private function sendEmailTwoFactorVerify( \WP_User $oUser ) {
		$aMessage = [
			__( 'Someone attempted to login into this WordPress site using your account.', 'wp-simple-firewall' ),
			__( 'Login requires verification with the following code.', 'wp-simple-firewall' ),
			'',
			sprintf( __( 'Verification Code: %s', 'wp-simple-firewall' ), sprintf( '<strong>%s</strong>', $this->getSecret( $oUser ) ) ),
			'',
			sprintf( '<strong>%s</strong>', __( 'Login Details', 'wp-simple-firewall' ) ),
			sprintf( '%s: %s', __( 'URL', 'wp-simple-firewall' ), Services::WpGeneral()->getHomeUrl() ),
			sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $oUser->user_login ),
			sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), Services::IP()->getRequestIp() ),
			'',
		];

		if ( !$this->getCon()->isRelabelled() ) {
			$aMessage[] = sprintf( '- <a href="%s" target="_blank">%s</a>', 'https://shsec.io/96', __( 'Why no login link?', 'wp-simple-firewall' ) );
			$aContent[] = '';
		}

		$bResult = $this->getEmailProcessor()
						->sendEmailWithWrap(
							$oUser->user_email,
							__( 'Two-Factor Login Verification', 'wp-simple-firewall' ),
							$aMessage
						);

		$this->getCon()->fireEvent(
			$bResult ? '2fa_email_send_success' : '2fa_email_send_fail',
			[
				'audit' => [
					'user_login' => $oUser->user_login,
				]
			]
		);
		return $this;
	}

	/**
	 * @return string
	 */
	protected function getStub() {
		return ICWP_WPSF_Processor_LoginProtect_Track::Factor_Email;
	}

	/**
	 * @return string
	 */
	protected function get2FaCodeUserMetaKey() {
		return $this->getMod()->prefix( 'tfaemail_reqid' );
	}
}