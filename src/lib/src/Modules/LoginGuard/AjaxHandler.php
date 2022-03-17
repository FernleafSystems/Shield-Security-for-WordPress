<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$parentMap = parent::getAjaxActionCallbackMap( $isAuth );
		if ( $isAuth ) {
			$map = array_merge( $parentMap, [
				'mfa_remove_all'            => [ $this, 'ajaxExec_MfaRemoveAll' ],
				'profile_backup_codes_gen'  => [ $this, 'ajaxExec_BackupCodesCreate' ],
				'profile_backup_codes_del'  => [ $this, 'ajaxExec_BackupCodesDelete' ],
				'profile_email2fa_disable'  => [ $this, 'ajaxExec_Profile2faEmailDisable' ],
				'profile_email2fa_toggle'   => [ $this, 'ajaxExec_Profile2faEmailToggle' ],
				'profile_ga_toggle'         => [ $this, 'ajaxExec_ProfileGaToggle' ],
				'profile_sms2fa_add'        => [ $this, 'ajaxExec_ProfileSmsAdd' ],
				'profile_sms2fa_remove'     => [ $this, 'ajaxExec_ProfileSmsRemove' ],
				'profile_sms2fa_verify'     => [ $this, 'ajaxExec_ProfileSmsVerify' ],
				'intent_sms_send'           => [ $this, 'ajaxExec_UserSmsIntentStart' ],
				'resend_verification_email' => [ $this, 'ajaxExec_ResendEmailVerification' ],
				'profile_u2f_add'           => [ $this, 'ajaxExec_ProfileU2fAdd' ],
				'profile_u2f_remove'        => [ $this, 'ajaxExec_ProfileU2fRemove' ],
				'profile_yubikey_toggle'    => [ $this, 'ajaxExec_ProfileYubikeyToggle' ],
			] );
		}
		else {
			$map = array_merge( $parentMap, [
				'intent_email_send' => [ $this, 'ajaxExec_IntentEmailSend' ],
			] );
		}
		return $map;
	}

	public function ajaxExec_MfaRemoveAll() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$userID = Services::Request()->post( 'user_id' );
		if ( !empty( $userID ) ) {
			$result = $mod->getMfaController()->removeAllFactorsForUser( (int)$userID );
			$response = [
				'success' => $result->success,
				'message' => $result->success ? $result->msg_text : $result->error_text,
			];
		}
		else {
			$response = [
				'success' => false,
				'message' => 'Invalid request with no User ID',
			];
		}

		return $response;
	}

	public function ajaxExec_BackupCodesCreate() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\BackupCodes $provider */
		$provider = $mod->getMfaController()->getProviders()[ TwoFactor\Provider\BackupCodes::SLUG ];

		$pass = $provider->setUser( Services::WpUsers()->getCurrentWpUser() )
						 ->resetSecret();
		$pass = implode( '-', str_split( $pass, 5 ) );

		return [
			'message' => sprintf( 'Your backup login code is:<br/><code>%s</code>', $pass ),
			'code'    => $pass,
			'success' => true
		];
	}

	public function ajaxExec_BackupCodesDelete() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\BackupCodes $provider */
		$provider = $mod->getMfaController()->getProviders()[ TwoFactor\Provider\BackupCodes::SLUG ];
		$provider->setUser( Services::WpUsers()->getCurrentWpUser() )->remove();
		$mod->setFlashAdminNotice(
			__( 'Multi-factor login backup code has been removed from your profile', 'wp-simple-firewall' )
		);

		return [
			'message' => __( 'Your backup login codes have been deleted.', 'wp-simple-firewall' ),
			'success' => true
		];
	}

	public function ajaxExec_ProfileGaToggle() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\GoogleAuth $provider */
		$provider = $mod->getMfaController()->getProviders()[ TwoFactor\Provider\GoogleAuth::SLUG ];
		$provider->setUser( Services::WpUsers()->getCurrentWpUser() );

		$otp = Services::Request()->post( 'ga_otp', '' );
		$result = empty( $otp ) ? $provider->removeGA() : $provider->activateGA( $otp );

		return [
			'success'     => $result->success,
			'message'     => $result->success ? $result->msg_text : $result->error_text,
			'page_reload' => true
		];
	}

	public function ajaxExec_Profile2faEmailToggle() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\Email $provider */
		$provider = $mod->getMfaController()->getProviders()[ TwoFactor\Provider\Email::SLUG ];

		$turnOn = Services::Request()->post( 'direction' ) === 'on';
		$provider->setUser( Services::WpUsers()->getCurrentWpUser() )
				 ->setProfileValidated( $turnOn );
		$success = $turnOn === $provider->isProfileActive();

		if ( $success ) {
			$msg = $turnOn ? __( 'Email 2FA activated.', 'wp-simple-firewall' )
				: __( 'Email 2FA deactivated.', 'wp-simple-firewall' );
		}
		else {
			$msg = __( "Email 2FA settings couldn't be changed.", 'wp-simple-firewall' );
		}

		return [
			'success'     => $success,
			'message'     => $msg,
			'page_reload' => true
		];
	}

	public function ajaxExec_Profile2faEmailDisable() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->setEnabled2FaEmail( false );
		return [
			'success'     => true,
			'message'     => __( '2FA by email has been disabled', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}

	public function ajaxExec_ProfileU2fAdd() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\U2F $provider */
		$provider = $mod->getMfaController()->getProviders()[ TwoFactor\Provider\U2F::SLUG ];

		$u2fReg = Services::Request()->post( 'icwp_wpsf_new_u2f_response' );
		if ( empty( $u2fReg ) ) {
			$response = [
				'success'     => false,
				'message'     => __( 'U2F registration details were missing in the request.', 'wp-simple-firewall' ),
				'page_reload' => true
			];
		}
		else {
			$result = $provider->setUser( Services::WpUsers()->getCurrentWpUser() )
							   ->addNewRegistration( $u2fReg );
			$response = [
				'success'     => $result->success,
				'message'     => $result->success ? $result->msg_text : $result->error_text,
				'page_reload' => true
			];
		}
		return $response;
	}

	public function ajaxExec_ProfileSmsAdd() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();
		/** @var TwoFactor\Provider\Sms $provider */
		$provider = $mod->getMfaController()->getProviders()[ TwoFactor\Provider\Sms::SLUG ];

		$countryCode = $req->post( 'sms_country' );
		$phoneNum = $req->post( 'sms_phone' );

		$response = [
			'success'     => false,
			'message'     => __( 'Either the country code or phone number were missing.', 'wp-simple-firewall' ),
			'page_reload' => true
		];

		if ( empty( $countryCode ) ) {
			$response[ 'message' ] = __( 'The country code was missing.', 'wp-simple-firewall' );
		}
		elseif ( empty( $phoneNum ) ) {
			$response[ 'message' ] = __( 'The phone number was missing.', 'wp-simple-firewall' );
		}
		else {
			$user = Services::WpUsers()->getCurrentWpUser();
			try {
				$response = [
					'success'     => true,
					'message'     => __( 'Please confirm the 6-digit code sent to your phone.', 'wp-simple-firewall' ),
					'code'        => $provider->setUser( $user )
											  ->addProvisionalRegistration( $countryCode, $phoneNum ),
					'page_reload' => false
				];
			}
			catch ( \Exception $e ) {
				$response = [
					'success'     => false,
					'message'     => esc_html( $e->getMessage() ),
					'page_reload' => false
				];
			}
		}

		return $response;
	}

	public function ajaxExec_ProfileSmsRemove() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\Sms $provider */
		$provider = $mod->getMfaController()->getProviders()[ TwoFactor\Provider\Sms::SLUG ];
		$provider->setUser( Services::WpUsers()->getCurrentWpUser() )
				 ->remove();
		return [
			'success'     => true,
			'message'     => __( 'SMS Registration Removed', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}

	public function ajaxExec_IntentEmailSend() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mfaCon = $mod->getMfaController();

		$success = false;
		$userID = Services::Request()->post( 'wp_user_id' );
		$loginNonce = Services::Request()->post( 'login_nonce' );
		if ( !empty( $userID ) && !empty( $loginNonce ) ) {
			$user = Services::WpUsers()->getUserById( $userID );
			$nonces = array_keys( $mfaCon->getActiveLoginIntents( $user ) );
			if ( $user instanceof \WP_User && in_array( $loginNonce, $nonces ) ) {
				/** @var TwoFactor\Provider\Email $provider */
				$provider = $mod->getMfaController()
								->getProvidersForUser( $user, true )[ TwoFactor\Provider\Email::SLUG ] ?? null;
				$success = !empty( $provider ) && $provider->sendEmailTwoFactorVerify( $loginNonce );
			}
		}

		return [
			'success'     => $success,
			'message'     => $success ? __( 'One-Time Password was sent to your registered email address.', 'wp-simple-firewall' )
				: __( 'There was a problem sending the One-Time Password email.', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}

	public function ajaxExec_UserSmsIntentStart() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\Sms $provider */
		$provider = $mod->getMfaController()->getProviders()[ TwoFactor\Provider\Sms::SLUG ];
		try {
			$provider->setUser( Services::WpUsers()->getCurrentWpUser() )
					 ->startLoginIntent();
			$response = [
				'success'     => true,
				'message'     => __( 'One-Time Password was sent to your phone.', 'wp-simple-firewall' ),
				'page_reload' => true
			];
		}
		catch ( \Exception $e ) {
			$response = [
				'success'     => false,
				'message'     => $e->getMessage(),
				'page_reload' => true
			];
		}
		return $response;
	}

	public function ajaxExec_ProfileSmsVerify() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();
		/** @var TwoFactor\Provider\Sms $provider */
		$provider = $mod->getMfaController()->getProviders()[ TwoFactor\Provider\Sms::SLUG ];

		$countryCode = $req->post( 'sms_country' );
		$phoneNum = $req->post( 'sms_phone' );
		$verifyCode = $req->post( 'sms_code' );

		$response = [
			'success'     => false,
			'message'     => __( 'SMS Verification Failed.', 'wp-simple-firewall' ),
			'page_reload' => true
		];

		if ( empty( $verifyCode ) ) {
			$response[ 'message' ] = __( 'The code provided was empty.', 'wp-simple-firewall' );
		}
		elseif ( empty( $countryCode ) || empty( $phoneNum ) ) {
			$response[ 'message' ] = __( 'The data provided was inconsistent.', 'wp-simple-firewall' );
		}
		else {
			try {
				$response = [
					'success'     => true,
					'message'     => __( 'Phone verified and registered successfully for SMS Two-Factor Authentication.', 'wp-simple-firewall' ),
					'code'        => $provider->setUser( Services::WpUsers()->getCurrentWpUser() )
											  ->verifyProvisionalRegistration( $countryCode, $phoneNum, $verifyCode ),
					'page_reload' => false
				];
			}
			catch ( \Exception $e ) {
				$response = [
					'success'     => false,
					'message'     => esc_html( $e->getMessage() ),
					'page_reload' => false
				];
			}
		}

		return $response;
	}

	public function ajaxExec_ProfileU2fRemove() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\U2F $provider */
		$provider = $mod->getMfaController()
						->getProviders()[ TwoFactor\Provider\U2F::SLUG ];

		$key = Services::Request()->post( 'u2fid' );
		if ( !empty( $key ) ) {
			$provider->setUser( Services::WpUsers()->getCurrentWpUser() )
					 ->removeRegisteredU2fId( $key );
		}
		return [
			'success'     => !empty( $key ),
			'message'     => __( 'Registered U2F device removed from profile.', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}

	public function ajaxExec_ProfileYubikeyToggle() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\Yubikey $provider */
		$provider = $mod->getMfaController()
						->getProviders()[ TwoFactor\Provider\Yubikey::SLUG ];

		$otp = Services::Request()->post( 'otp', '' );
		$result = $provider->setUser( Services::WpUsers()->getCurrentWpUser() )
						   ->toggleRegisteredYubiID( $otp );
		return [
			'success'     => $result->success,
			'message'     => $result->success ? $result->msg_text : $result->error_text,
			'page_reload' => true
		];
	}

	public function ajaxExec_ResendEmailVerification() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		$success = true;

		if ( !$opts->isEnabledEmailAuth() ) {
			$msg = __( 'Email 2FA option is not currently enabled.', 'wp-simple-firewall' );
			$success = false;
		}
		elseif ( $opts->getIfCanSendEmailVerified() ) {
			$msg = __( 'Email sending has already been verified.', 'wp-simple-firewall' );
		}
		else {
			$msg = __( 'Verification email resent.', 'wp-simple-firewall' );
			$mod->setIfCanSendEmail( false )
				->sendEmailVerifyCanSend();
		}

		return [
			'success' => $success,
			'message' => $msg
		];
	}
}