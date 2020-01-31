<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class Email extends BaseProvider {

	const SLUG = 'email';

	/**
	 * @param \WP_User $oUser
	 */
	public function captureLoginAttempt( $oUser ) {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Update $oUpd */
		$oUpd = $oMod->getDbHandler_Sessions()->getQueryUpdater();
		$oUpd->setLoginIntentCodeEmail( $oMod->getSession(), $this->getSecret( $oUser ) );

		// Now send email with authentication link for user.
		$this->sendEmailTwoFactorVerify( $oUser );
	}

	/**
	 * @param \WP_User $oUser
	 * @param bool     $bIsSuccess
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
	 * @param \WP_User $oUser
	 * @param string   $sOtpCode
	 * @return bool
	 */
	protected function processOtp( $oUser, $sOtpCode ) {
		$bValid = !empty( $sOtpCode ) && ( $sOtpCode == $this->getStoredSessionHashCode() );
		if ( $bValid ) {
			/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
			$oMod = $this->getMod();
			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Update $oUpd */
			$oUpd = $oMod->getDbHandler_Sessions()->getQueryUpdater();
			$oUpd->clearLoginIntentCodeEmail( $oMod->getSession() );
		}
		return $bValid;
	}

	/**
	 * @return array
	 */
	public function getFormField() {
		return [
			'name'        => $this->getLoginFormParameter(),
			'type'        => 'text',
			'value'       => $this->fetchCodeFromRequest(),
			'placeholder' => __( 'This code was just sent to your registered Email address.', 'wp-simple-firewall' ),
			'text'        => __( 'Email OTP', 'wp-simple-firewall' ),
			'help_link'   => 'https://shsec.io/3t'
		];
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
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		return $oMod->hasSession() ? $oMod->getSession()->getLoginIntentCodeEmail() : '';
	}

	/**
	 * @inheritDoc
	 */
	public function handleUserProfileSubmit( \WP_User $oUser ) {

		$bWasEnabled = $this->isProfileActive( $oUser );
		$bToEnable = Services::Request()->post( 'shield_enable_mfaemail' ) === 'Y';

		$sMsg = null;
		$bError = false;
		if ( $bToEnable ) {
			$this->setProfileValidated( $oUser );
			if ( !$bWasEnabled ) {
				$sMsg = __( 'Email Two-Factor Authentication has been enabled.', 'wp-simple-firewall' );
			}
		}
		elseif ( $this->isEnforced( $oUser ) ) {
			$sMsg = __( "Email Two-Factor Authentication couldn't be disabled because it is enforced based on your user roles.", 'wp-simple-firewall' );
			$bError = true;
		}
		else {
			$this->setProfileValidated( $oUser, false );
			$sMsg = __( 'Email Two-Factor Authentication was has been disabled.', 'wp-simple-firewall' );
		}

		if ( !empty( $sMsg ) ) {
			$this->getMod()->setFlashAdminNotice( $sMsg, $bError );
		}
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	public function isProfileActive( \WP_User $oUser ) {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return parent::isProfileActive( $oUser ) &&
			   ( $this->isEnforced( $oUser ) ||
				 ( $this->hasValidatedProfile( $oUser ) && $oOpts->isEnabledEmailAuthAnyUserSet() ) );
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	protected function isEnforced( $oUser ) {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return count( array_intersect( $oOpts->getEmail2FaRoles(), $oUser->roles ) ) > 0;
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

		$bResult = $this->getMod()
						->getEmailProcessor()
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
	 * @inheritDoc
	 */
	public function renderUserProfileOptions( \WP_User $oUser ) {
		$aData = [
			'strings' => [
				'label_email_authentication'                => __( 'Email Authentication', 'wp-simple-firewall' ),
				'title'                                     => __( 'Email Authentication', 'wp-simple-firewall' ),
				'description_email_authentication_checkbox' => __( 'Check the box to enable email-based login authentication.', 'wp-simple-firewall' ),
				'provided_by'                               => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ), $this->getCon()
																														   ->getHumanName() )
			]
		];

		return $this->getMod()
					->renderTemplate(
						'/snippets/user/profile/mfa/mfa_email.twig',
						Services::DataManipulation()->mergeArraysRecursive( $this->getCommonData( $oUser ), $aData ),
						true
					);
	}

	/**
	 * @return bool
	 */
	public function isProviderEnabled() {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isEnabledEmailAuth();
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	public function isProviderAvailableToUser( \WP_User $oUser ) {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return parent::isProviderAvailableToUser( $oUser )
			   && ( $this->isEnforced( $oUser ) || $oOpts->isEnabledEmailAuthAnyUserSet() );
	}
}