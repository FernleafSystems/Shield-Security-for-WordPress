<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LicenseEmails {

	use PluginControllerConsumer;

	public function sendLicenseWarningEmail() {
		$con = self::con();
		$canSend = Services::Request()
						   ->carbon()
						   ->subDay()->timestamp > $con->opts->optGet( 'last_warning_email_sent_at' );

		if ( $canSend ) {
			$con->opts
				->optSet( 'last_warning_email_sent_at', Services::Request()->ts() )
				->store();

			$con->email_con->sendEmailWithWrap(
				$con->comps->opts_lookup->getReportEmail(),
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
						   ->subDay()->timestamp > $con->opts->optGet( 'last_deactivated_email_sent_at' );

		if ( $canSend ) {
			$con->opts
				->optSet( 'last_deactivated_email_sent_at', Services::Request()->ts() )
				->store();

			$con->email_con->sendEmailWithWrap(
				$con->comps->opts_lookup->getReportEmail(),
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