<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\Base\AjaxHandlerShield {

	/**
	 * @param string $sAction
	 * @return array
	 */
	protected function processAjaxAction( $sAction ) {

		switch ( $sAction ) {
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

			default:
				$aResponse = parent::processAjaxAction( $sAction );
		}

		return $aResponse;
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_GenBackupCodes() {
		/** @var \ICWP_WPSF_Processor_LoginProtect $oPro */
		$oPro = $this->getMod()->getProcessor();
		$sPass = $oPro->getSubProIntent()
					  ->getProcessorBackupCodes()
					  ->resetSecret( Services::WpUsers()->getCurrentWpUser() );

		foreach ( [ 20, 15, 10, 5 ] as $nPos ) {
			$sPass = substr_replace( $sPass, '-', $nPos, 0 );
		}

		return [
			'code'    => $sPass,
			'success' => true
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_DeleteBackupCodes() {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		/** @var \ICWP_WPSF_Processor_LoginProtect $oPro */
		$oPro = $oMod->getProcessor();
		$oPro->getSubProIntent()
			 ->getProcessorBackupCodes()
			 ->deleteSecret( Services::WpUsers()->getCurrentWpUser() );
		$oMod->setFlashAdminNotice( __( 'Multi-factor login backup code has been removed from your profile', 'wp-simple-firewall' ) );
		return [
			'success' => true
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_Disable2faEmail() {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		$oMod->setEnabled2FaEmail( false );
		return [
			'success'     => true,
			'message'     => __( '2FA by email has been disabled', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_ResendEmailVerification() {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		$bSuccess = true;

		if ( !$oMod->isEmailAuthenticationOptionOn() ) {
			$sMessage = __( 'Email 2FA option is not currently enabled.', 'wp-simple-firewall' );
			$bSuccess = false;
		}
		else if ( $oMod->getIfCanSendEmailVerified() ) {
			$sMessage = __( 'Email sending has already been verified.', 'wp-simple-firewall' );
		}
		else {
			$sMessage = __( 'Verification email resent.', 'wp-simple-firewall' );
			$oMod->setIfCanSendEmail( false )
				 ->sendEmailVerifyCanSend();
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage
		];
	}
}