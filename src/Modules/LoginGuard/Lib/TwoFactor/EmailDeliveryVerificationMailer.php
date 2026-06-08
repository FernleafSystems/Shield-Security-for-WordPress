<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaCanEmailSendVerify;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\GenericLines;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Email\EmailVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class EmailDeliveryVerificationMailer {

	use PluginControllerConsumer;

	public function send() :bool {
		$con = self::con();

		return $con->email_con->sendVO(
			EmailVO::Factory(
				$con->comps->opts_lookup->getReportEmail(),
				__( 'Email Sending Verification', 'wp-simple-firewall' ),
				$con->action_router->render(
					GenericLines::class,
					[
						'lines' => [
							__( 'Before enabling 2-factor email authentication for your WordPress site, you must verify you can receive this email.', 'wp-simple-firewall' ),
							__( 'This verifies your website can send email and that the configured report email address can receive emails sent from your site.', 'wp-simple-firewall' ),
							'',
							sprintf(
								__( 'Click the verify link: %s', 'wp-simple-firewall' ),
								$con->plugin_urls->noncedPluginAction( MfaCanEmailSendVerify::class, $con->plugin_urls->adminHome() )
							)
						],
					]
				)
			)
		);
	}
}
