<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LicenseEmails {

	use ModConsumer;

	public function sendLicenseWarningEmail() {
		/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
		$oMod = $this->getMod();
		$oOpts = $this->getOptions();

		$bCanSend = Services::Request()
							->carbon()
							->subDay( 1 )->timestamp > $oOpts->getOpt( 'last_warning_email_sent_at' );

		if ( $bCanSend ) {
			$oOpts->setOptAt( 'last_warning_email_sent_at' );
			$oMod->saveModOptions();

			$aMessage = [
				__( 'Attempts to verify Shield Pro license has just failed.', 'wp-simple-firewall' ),
				sprintf( __( 'Please check your license on-site: %s', 'wp-simple-firewall' ), $oMod->getUrl_AdminPage() ),
				sprintf( __( 'If this problem persists, please contact support: %s', 'wp-simple-firewall' ), 'https://support.onedollarplugin.com/' )
			];
			$oMod->getEmailProcessor()
				 ->sendEmailWithWrap(
					 $oMod->getPluginReportEmail(),
					 'Pro License Check Has Failed',
					 $aMessage
				 );
			$this->getCon()->fireEvent( 'lic_fail_email' );
		}
	}

	public function sendLicenseDeactivatedEmail() {
		/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
		$oMod = $this->getMod();
		$oOpts = $this->getOptions();

		$bCanSend = Services::Request()
							->carbon()
							->subDay( 1 )->timestamp > $oOpts->getOpt( 'last_deactivated_email_sent_at' );

		if ( $bCanSend ) {
			$oOpts->setOptAt( 'last_deactivated_email_sent_at' );
			$oMod->saveModOptions();

			$aMessage = [
				__( 'All attempts to verify Shield Pro license have failed.', 'wp-simple-firewall' ),
				sprintf( __( 'Please check your license on-site: %s', 'wp-simple-firewall' ), $oMod->getUrl_AdminPage() ),
				sprintf( __( 'If this problem persists, please contact support: %s', 'wp-simple-firewall' ), 'https://support.onedollarplugin.com/' )
			];
			$oMod->getEmailProcessor()
				 ->sendEmailWithWrap(
					 $oMod->getPluginReportEmail(),
					 '[Action May Be Required] Pro License Has Been Deactivated',
					 $aMessage
				 );
		}
	}
}