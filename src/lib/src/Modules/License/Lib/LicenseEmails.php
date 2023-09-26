<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LicenseEmails {

	use ModConsumer;

	public function sendLicenseWarningEmail() {
		$con = self::con();
		$canSend = Services::Request()
						   ->carbon()
						   ->subDay()->timestamp > $this->opts()->getOpt( 'last_warning_email_sent_at' );

		if ( $canSend ) {
			$this->opts()->setOpt( 'last_warning_email_sent_at', Services::Request()->ts() );
			$con->opts->commit();

			$con->email_con->sendEmailWithWrap(
				$con->getModule_Plugin()->getPluginReportEmail(),
				'Pro License Check Has Failed',
				[
					__( 'Attempts to verify Shield Pro license has just failed.', 'wp-simple-firewall' ),
					sprintf( __( 'Please check your license on-site: %s', 'wp-simple-firewall' ),
						self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_LICENSE ) ),
					sprintf( __( 'If this problem persists, please contact support: %s', 'wp-simple-firewall' ), 'https://support.getshieldsecurity.com/' )
				]
			);
			self::con()->fireEvent( 'lic_fail_email' );
		}
	}

	public function sendLicenseDeactivatedEmail() {
		$con = self::con();
		$canSend = Services::Request()
						   ->carbon()
						   ->subDay()->timestamp > $this->opts()->getOpt( 'last_deactivated_email_sent_at' );

		if ( $canSend ) {
			$this->opts()->setOpt( 'last_deactivated_email_sent_at', Services::Request()->ts() );
			$con->opts->commit();

			$con->email_con->sendEmailWithWrap(
				$con->getModule_Plugin()->getPluginReportEmail(),
				'[Action May Be Required] Pro License Has Been Deactivated',
				[
					__( 'All attempts to verify Shield Pro license have failed.', 'wp-simple-firewall' ),
					sprintf( __( 'Please check your license on-site: %s', 'wp-simple-firewall' ), $con->plugin_urls->adminTopNav( PluginNavs::NAV_LICENSE ) ),
					sprintf( __( 'If this problem persists, please contact support: %s', 'wp-simple-firewall' ), 'https://support.getshieldsecurity.com/' )
				]
			);
		}
	}
}