<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class Email extends BaseProvider {

	const SLUG = 'email';

	private $secretToDelete = '';

	/**
	 * @param \WP_User $user
	 */
	public function captureLoginAttempt( $user ) {
		$this->sendEmailTwoFactorVerify( $user );
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
	 * @param \WP_User $user
	 * @return $this
	 */
	public function postSuccessActions( \WP_User $user ) {
		if ( !empty( $this->secretToDelete ) ) {
			$secrets = $this->getAllSecrets( $user );
			unset( $secrets[ $this->secretToDelete ] );
			$this->getCon()->getUserMeta( $user )->email_secrets = $secrets;
		}
		return $this;
	}

	/**
	 * @param \WP_User $user
	 * @param string   $otp
	 * @return bool
	 */
	protected function processOtp( \WP_User $user, $otp ) {
		$valid = false;
		$secrets = $this->getAllSecrets( $user );
		foreach ( $secrets as $secret => $expiresAt ) {
			if ( wp_check_password( $otp, $secret ) ) {
				$valid = true;
				$this->secretToDelete = $secret;
				break;
			}
		}
		return $valid;
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
	 * @param \WP_User $user
	 * @return string
	 */
	protected function getSecret( \WP_User $user ) {
		return wp_generate_password( 6, false );
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
			$sMsg = __( 'Email Two-Factor Authentication has been disabled.', 'wp-simple-firewall' );
		}

		if ( !empty( $sMsg ) ) {
			$this->getMod()->setFlashAdminNotice( $sMsg, $bError );
		}
	}

	/**
	 * @param \WP_User $user
	 * @return bool
	 */
	public function isProfileActive( \WP_User $user ) {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return parent::isProfileActive( $user ) &&
			   ( $this->isEnforced( $user ) ||
				 ( $this->hasValidatedProfile( $user ) && $opts->isEnabledEmailAuthAnyUserSet() ) );
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	protected function isEnforced( \WP_User $oUser ) {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return count( array_intersect( $opts->getEmail2FaRoles(), $oUser->roles ) ) > 0;
	}

	/**
	 * @param \WP_User $oUser
	 * @return $this
	 */
	private function sendEmailTwoFactorVerify( \WP_User $oUser ) {

		$aLines = [
			'someone'          => __( 'Someone attempted to login into this WordPress site using your account.', 'wp-simple-firewall' ),
			'requires'         => __( 'Login requires verification with the following code.', 'wp-simple-firewall' ),
			'verification'     => __( 'Verification Code', 'wp-simple-firewall' ),
			'login_link'       => __( 'Why no login link?', 'wp-simple-firewall' ),
			'details_heading'  => __( 'Login Details', 'wp-simple-firewall' ),
			'details_url'      => sprintf( '%s: %s', __( 'URL', 'wp-simple-firewall' ), Services::WpGeneral()
																								->getHomeUrl() ),
			'details_username' => sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $oUser->user_login ),
			'details_ip'       => sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), Services::IP()
																									   ->getRequestIp() ),
		];

		try {
			$bResult = $this->getMod()
							->getEmailProcessor()
							->sendEmailWithTemplate(
								'/email/lp_2fa_email_code',
								$oUser->user_email,
								__( 'Two-Factor Login Verification', 'wp-simple-firewall' ),
								[
									'flags'   => [
										'show_login_link' => !$this->getCon()->isRelabelled()
									],
									'vars'    => [
										'code' => $this->genNewCode( $oUser )
									],
									'hrefs'   => [
										'login_link' => 'https://shsec.io/96'
									],
									'strings' => $aLines
								]
							);
		}
		catch ( \Exception $e ) {
			$bResult = false;
		}

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
		return $oOpts->isEmailAuthenticationActive();
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	public function isProviderAvailableToUser( \WP_User $oUser ) {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return parent::isProviderAvailableToUser( $oUser )
			   && ( $this->isEnforced( $oUser ) || $opts->isEnabledEmailAuthAnyUserSet() );
	}

	/**
	 * @param \WP_User $user
	 * @return string
	 */
	private function genNewCode( \WP_User $user ) {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		$secrets = $this->getAllSecrets( $user );
		$new = $this->getSecret( $user );
		$secrets[ wp_hash_password( $new ) ] = Services::Request()
													   ->carbon()
													   ->addMinutes( $opts->getLoginIntentMinutes() )->timestamp;
		$this->getCon()->getUserMeta( $user )->email_secrets = array_slice( $secrets, -10 );

		return $new;
	}

	/**
	 * @param \WP_User $user
	 * @return array
	 */
	private function getAllSecrets( \WP_User $user ) {
		$meta = $this->getCon()->getUserMeta( $user );
		return array_filter(
			empty( $meta->email_secrets ) ? [] : $meta->email_secrets,
			function ( $ts ) {
				return $ts >= Services::Request()->ts();
			}
		);
	}
}