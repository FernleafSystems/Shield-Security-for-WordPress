<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'gen_backup_codes':
				$aResponse = $this->ajaxExec_GenBackupCodes();
				break;

			case 'del_backup_codes':
				$aResponse = $this->ajaxExec_DeleteBackupCodes();
				break;

			case 'disable_2fa_email':
				$aResponse = $this->ajaxExec_Disable2faEmail();
				break;

			case 'resend_verification_email':
				$aResponse = $this->ajaxExec_ResendEmailVerification();
				break;

			case 'u2f_remove':
				$aResponse = $this->ajaxExec_ProfileU2fRemove();
				break;

			case 'yubikey_remove':
				$aResponse = $this->ajaxExec_ProfileYubikeyRemove();
				break;

			default:
				$aResponse = parent::processAjaxAction( $action );
		}

		return $aResponse;
	}

	protected function ajaxExec_GenBackupCodes() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\Backup $oBU */
		$oBU = $mod->getLoginIntentController()
				   ->getProviders()[ TwoFactor\Provider\Backup::SLUG ];
		$pass = $oBU->resetSecret( Services::WpUsers()->getCurrentWpUser() );

		foreach ( [ 20, 15, 10, 5 ] as $pos ) {
			$pass = substr_replace( $pass, '-', $pos, 0 );
		}

		return [
			'code'    => $pass,
			'success' => true
		];
	}

	private function ajaxExec_DeleteBackupCodes() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var TwoFactor\Provider\Backup $oBU */
		$oBU = $mod->getLoginIntentController()
				   ->getProviders()[ TwoFactor\Provider\Backup::SLUG ];
		$oBU->deleteSecret( Services::WpUsers()->getCurrentWpUser() );
		$mod->setFlashAdminNotice( __( 'Multi-factor login backup code has been removed from your profile', 'wp-simple-firewall' ) );
		return [
			'success' => true
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

	private function ajaxExec_ProfileU2fRemove() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$key = Services::Request()->post( 'u2fid' );
		( new TwoFactor\Provider\U2F() )
			->setMod( $mod )
			->removeRegisteredU2fId( Services::WpUsers()->getCurrentWpUser(), $key );
		return [
			'success'     => true,
			'message'     => __( 'Registered U2F device removed from profile.', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_ProfileYubikeyRemove() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$key = Services::Request()->post( 'yubikeyid' );
		( new TwoFactor\Provider\Yubikey() )
			->setMod( $mod )
			->addRemoveRegisteredYubiId( Services::WpUsers()->getCurrentWpUser(), $key, false );
		return [
			'success'     => true,
			'message'     => __( 'Yubikey removed from profile.', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_ResendEmailVerification() {
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