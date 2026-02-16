<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\GenericLines;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Email\EmailVO;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LicenseEmails {

	use PluginControllerConsumer;

	public function sendLicenseWarningEmail() {
		$this->sendThrottledNoticeEmail(
			'last_warning_email_sent_at',
			'Pro License Check Has Failed',
			[
				sprintf( __( 'Attempts to verify %s Pro license has just failed.', 'wp-simple-firewall' ), self::con()->labels->Name ),
				sprintf( __( 'Please check your license on-site: %s', 'wp-simple-firewall' ),
					self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_LICENSE ) ),
				sprintf( __( 'If this problem persists, please contact support: %s', 'wp-simple-firewall' ), 'https://support.getshieldsecurity.com/' )
			],
			'lic_fail_email'
		);
	}

	public function sendLicenseDeactivatedEmail() {
		$this->sendThrottledNoticeEmail(
			'last_deactivated_email_sent_at',
			'[Action May Be Required] Pro License Has Been Deactivated',
			[
				sprintf( __( 'All attempts to verify %s Pro license have failed.', 'wp-simple-firewall' ), self::con()->labels->Name ),
				sprintf( __( 'Please check your license on-site: %s', 'wp-simple-firewall' ), self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_LICENSE ) ),
				sprintf( __( 'If this problem persists, please contact support: %s', 'wp-simple-firewall' ), 'https://support.getshieldsecurity.com/' )
			]
		);
	}

	private function sendThrottledNoticeEmail( string $throttleOption, string $subject, array $lines, string $event = '' ) :void {
		$con = self::con();
		$canSend = Services::Request()
						   ->carbon()
						   ->subDay()->timestamp > $con->opts->optGet( $throttleOption );

		if ( $canSend ) {
			$con->opts
				->optSet( $throttleOption, Services::Request()->ts() )
				->store();

			$con->email_con->sendVO(
				EmailVO::Factory(
					$con->comps->opts_lookup->getReportEmail(),
					$subject,
					$con->action_router->render( GenericLines::class, [
						'lines' => $lines
					] )
				)
			);

			if ( !empty( $event ) ) {
				$con->comps->events->fireEvent( $event );
			}
		}
	}
}
