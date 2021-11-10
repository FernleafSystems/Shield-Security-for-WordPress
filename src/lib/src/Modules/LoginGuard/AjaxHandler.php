<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'mfa_remove_all':
				$response = $this->ajaxExec_MfaRemoveAll();
				break;

			case 'gen_backup_codes':
				$response = $this->ajaxExec_GenBackupCodes();
				break;

			case 'del_backup_codes':
				$response = $this->ajaxExec_DeleteBackupCodes();
				break;

			case 'disable_2fa_email':
				$response = $this->ajaxExec_Disable2faEmail();
				break;

			case 'user_sms2fa_add':
				$response = $this->ajaxExec_UserSmsAdd();
				break;

			case 'user_sms2fa_remove':
				$response = $this->ajaxExec_UserSmsRemove();
				break;

			case 'user_sms2fa_verify':
				$response = $this->ajaxExec_UserSmsVerify();
				break;

			case 'user_ga_toggle':
				$response = $this->ajaxExec_UserGaToggle();
				break;

			case 'user_email2fa_toggle':
				$response = $this->ajaxExec_User2faEmailToggle();
				break;

			case 'resend_verification_email':
				$response = $this->ajaxExec_ResendEmailVerification();
				break;

			case 'u2f_add':
				$response = $this->ajaxExec_ProfileU2fAdd();
				break;

			case 'u2f_remove':
				$response = $this->ajaxExec_ProfileU2fRemove();
				break;

			case 'user_yubikey_toggle':
				$response = $this->ajaxExec_UserYubikeyToggle();
				break;

			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	private function ajaxExec_MfaRemoveAll() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$userID = Services::Request()->post( 'user_id' );
		if ( !empty( $userID ) ) {
			$result = $mod->getLoginIntentController()->removeAllFactorsForUser( (int)$userID );
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

	protected function ajaxExec_GenBackupCodes() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\BackupCodes $provider */
		$provider = $mod->getLoginIntentController()->getProviders()[ TwoFactor\Provider\BackupCodes::SLUG ];

		$pass = $provider->resetSecret( Services::WpUsers()->getCurrentWpUser() );
		$pass = implode( '-', str_split( $pass, 5 ) );

		return [
			'message' => sprintf( 'Your backup login code is:<br/><code>%s</code>', $pass ),
			'code'    => $pass,
			'success' => true
		];
	}

	private function ajaxExec_DeleteBackupCodes() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\BackupCodes $provider */
		$provider = $mod->getLoginIntentController()->getProviders()[ TwoFactor\Provider\BackupCodes::SLUG ];
		$provider->deleteSecret( Services::WpUsers()->getCurrentWpUser() );
		$mod->setFlashAdminNotice( __( 'Multi-factor login backup code has been removed from your profile', 'wp-simple-firewall' ) );

		return [
			'message' => __( 'Your backup login codes have been deleted.', 'wp-simple-firewall' ),
			'success' => true
		];
	}

	private function ajaxExec_UserGaToggle() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\GoogleAuth $provider */
		$provider = $mod->getLoginIntentController()->getProviders()[ TwoFactor\Provider\GoogleAuth::SLUG ];

		$otp = Services::Request()->post( 'ga_otp', '' );
		$result = empty( $otp ) ?
			$provider->removeGaOnAccount( Services::WpUsers()->getCurrentWpUser() )
			: $provider->activateGaOnAccount( Services::WpUsers()->getCurrentWpUser(), $otp );

		return [
			'success'     => $result->success,
			'message'     => $result->success ? $result->msg_text : $result->error_text,
			'page_reload' => true
		];
	}

	private function ajaxExec_User2faEmailToggle() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\Email $provider */
		$provider = $mod->getLoginIntentController()->getProviders()[ TwoFactor\Provider\Email::SLUG ];

		$turnOn = Services::Request()->post( 'direction' ) == 'on';
		$provider->setProfileValidated( Services::WpUsers()->getCurrentWpUser(), $turnOn );
		$success = $turnOn === $provider->isProfileActive( Services::WpUsers()->getCurrentWpUser() );

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

	private function ajaxExec_Disable2faEmail() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->setEnabled2FaEmail( false );
		return [
			'success'     => true,
			'message'     => __( '2FA by email has been disabled', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}

	private function ajaxExec_ProfileU2fAdd() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\U2F $provider */
		$provider = $mod->getLoginIntentController()->getProviders()[ TwoFactor\Provider\U2F::SLUG ];

		$u2fReg = Services::Request()->post( 'icwp_wpsf_new_u2f_response' );
		if ( empty( $u2fReg ) ) {
			$response = [
				'success'     => false,
				'message'     => __( 'U2F registration details were missing in the request.', 'wp-simple-firewall' ),
				'page_reload' => true
			];
		}
		else {
			$result = $provider->addNewRegistration( Services::WpUsers()->getCurrentWpUser(), $u2fReg );
			$response = [
				'success'     => $result->success,
				'message'     => $result->success ? $result->msg_text : $result->error_text,
				'page_reload' => true
			];
		}
		return $response;
	}

	private function ajaxExec_UserSmsAdd() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();
		/** @var TwoFactor\Provider\Sms $provider */
		$provider = $mod->getLoginIntentController()->getProviders()[ TwoFactor\Provider\Sms::SLUG ];

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
					'code'        => $provider->addProvisionalRegistration( $user, $countryCode, $phoneNum ),
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

	private function ajaxExec_UserSmsRemove() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\Sms $provider */
		$provider = $mod->getLoginIntentController()->getProviders()[ TwoFactor\Provider\Sms::SLUG ];
		$provider->remove( Services::WpUsers()->getCurrentWpUser() );
		return [
			'success'     => true,
			'message'     => __( 'SMS Registration Removed', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}

	private function ajaxExec_UserSmsVerify() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();
		/** @var TwoFactor\Provider\Sms $provider */
		$provider = $mod->getLoginIntentController()->getProviders()[ TwoFactor\Provider\Sms::SLUG ];

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
			$user = Services::WpUsers()->getCurrentWpUser();
			try {
				$response = [
					'success'     => true,
					'message'     => __( 'Please confirm the 6-digit code sent to your phone.', 'wp-simple-firewall' ),
					'code'        => $provider->verifyProvisionalRegistration( $user, $countryCode, $phoneNum, $verifyCode ),
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

	private function ajaxExec_ProfileU2fRemove() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\U2F $provider */
		$provider = $mod->getLoginIntentController()
						->getProviders()[ TwoFactor\Provider\U2F::SLUG ];

		$key = Services::Request()->post( 'u2fid' );
		if ( !empty( $key ) ) {
			$provider->removeRegisteredU2fId( Services::WpUsers()->getCurrentWpUser(), $key );
		}
		return [
			'success'     => !empty( $key ),
			'message'     => __( 'Registered U2F device removed from profile.', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}

	private function ajaxExec_UserYubikeyToggle() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\Yubikey $provider */
		$provider = $mod->getLoginIntentController()
						->getProviders()[ TwoFactor\Provider\Yubikey::SLUG ];

		$otp = Services::Request()->post( 'otp', '' );
		$result = $provider->toggleRegisteredYubiID( Services::WpUsers()->getCurrentWpUser(), $otp );
		return [
			'success'     => $result->success,
			'message'     => $result->success ? $result->msg_text : $result->error_text,
			'page_reload' => true
		];
	}

	private function ajaxExec_ResendEmailVerification() :array {
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