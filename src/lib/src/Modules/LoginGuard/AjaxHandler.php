<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'gen_backup_codes':
				$response = $this->ajaxExec_GenBackupCodes();
				break;

			case 'del_backup_codes':
				$response = $this->ajaxExec_DeleteBackupCodes();
				break;

			case 'disable_2fa_email':
				$response = $this->ajaxExec_Disable2faEmail();
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

			case 'yubikey_remove':
				$response = $this->ajaxExec_ProfileYubikeyRemove();
				break;

			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	protected function ajaxExec_GenBackupCodes() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\BackupCodes $oBU */
		$oBU = $mod->getLoginIntentController()
				   ->getProviders()[ TwoFactor\Provider\BackupCodes::SLUG ];
		$pass = $oBU->resetSecret( Services::WpUsers()->getCurrentWpUser() );

		foreach ( [ 20, 15, 10, 5 ] as $pos ) {
			$pass = substr_replace( $pass, '-', $pos, 0 );
		}

		return [
			'message' => sprintf( 'Your backup login code is: %s', $pass ),
			'code'    => $pass,
			'success' => true
		];
	}

	private function ajaxExec_DeleteBackupCodes() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\BackupCodes $oBU */
		$oBU = $mod->getLoginIntentController()
				   ->getProviders()[ TwoFactor\Provider\BackupCodes::SLUG ];
		$oBU->deleteSecret( Services::WpUsers()->getCurrentWpUser() );
		$mod->setFlashAdminNotice( __( 'Multi-factor login backup code has been removed from your profile', 'wp-simple-firewall' ) );
		return [
			'message' => __( 'Your backup login codes have been deleted.', 'wp-simple-firewall' ),
			'success' => true
		];
	}

	private function ajaxExec_UserGaToggle() :array {
		$otp = Services::Request()->post( 'ga_otp', '' );
		if ( empty( $otp ) ) {
			$result = ( new TwoFactor\Provider\GoogleAuth() )
				->setMod( $this->getMod() )
				->removeGaOnAccount( Services::WpUsers()->getCurrentWpUser() );
		}
		else {
			$result = ( new TwoFactor\Provider\GoogleAuth() )
				->setMod( $this->getMod() )
				->activateGaOnAccount( Services::WpUsers()->getCurrentWpUser(), $otp );
		}

		return [
			'success'     => $result->success,
			'message'     => $result->success ? $result->msg_text : $result->error_text,
			'page_reload' => true
		];
	}

	private function ajaxExec_User2faEmailToggle() :array {

		$turnOn = Services::Request()->post( 'direction' ) == 'on';
		$provider = ( new TwoFactor\Provider\Email() )->setMod( $this->getMod() );
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
		$u2fReg = Services::Request()->post( 'icwp_wpsf_new_u2f_response' );
		if ( empty( $u2fReg ) ) {
			$response = [
				'success'     => false,
				'message'     => __( 'U2F registration details were missing in the request.', 'wp-simple-firewall' ),
				'page_reload' => true
			];
		}
		else {
			$result = ( new TwoFactor\Provider\U2F() )
				->setMod( $this->getMod() )
				->addNewRegistration( Services::WpUsers()->getCurrentWpUser(), $u2fReg );
			$response = [
				'success'     => $result->success,
				'message'     => $result->success ? $result->msg_text : $result->error_text,
				'page_reload' => true
			];
		}
		return $response;
	}

	private function ajaxExec_ProfileU2fRemove() :array {
		$key = Services::Request()->post( 'u2fid' );
		if ( !empty( $key ) ) {
			( new TwoFactor\Provider\U2F() )
				->setMod( $this->getMod() )
				->removeRegisteredU2fId( Services::WpUsers()->getCurrentWpUser(), $key );
		}
		return [
			'success'     => !empty( $key ),
			'message'     => __( 'Registered U2F device removed from profile.', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}

	private function ajaxExec_UserYubikeyToggle() :array {
		$otp = Services::Request()->post( 'otp', '' );
		$result = ( new TwoFactor\Provider\Yubikey() )
			->setMod( $this->getMod() )
			->toggleRegisteredYubiID( Services::WpUsers()->getCurrentWpUser(), $otp );
		return [
			'success'     => $result->success,
			'message'     => $result->success ? $result->msg_text : $result->error_text,
			'page_reload' => true
		];
	}

	private function ajaxExec_ProfileYubikeyRemove() :array {
		$key = Services::Request()->post( 'yubikeyid' );
		( new TwoFactor\Provider\Yubikey() )
			->setMod( $this->getMod() )
			->addRemoveRegisteredYubiId( Services::WpUsers()->getCurrentWpUser(), $key, false );
		return [
			'success'     => true,
			'message'     => __( 'Yubikey removed from profile.', 'wp-simple-firewall' ),
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
			$sMessage = __( 'Email 2FA option is not currently enabled.', 'wp-simple-firewall' );
			$success = false;
		}
		elseif ( $opts->getIfCanSendEmailVerified() ) {
			$sMessage = __( 'Email sending has already been verified.', 'wp-simple-firewall' );
		}
		else {
			$sMessage = __( 'Verification email resent.', 'wp-simple-firewall' );
			$mod->setIfCanSendEmail( false )
				->sendEmailVerifyCanSend();
		}

		return [
			'success' => $success,
			'message' => $sMessage
		];
	}
}