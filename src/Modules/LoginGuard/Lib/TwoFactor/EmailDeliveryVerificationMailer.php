<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaCanEmailSendVerify;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\MfaEmailDeliveryVerify;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Email\EmailVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class EmailDeliveryVerificationMailer {

	use PluginControllerConsumer;

	/**
	 * Sends the verification email and, on success, records it as sent.
	 */
	public function send() :bool {
		$con = self::con();
		$verification = new EmailDeliveryVerification();
		$sentAt = Services::Request()->ts();

		$sent = $con->email_con->sendVO(
			EmailVO::Factory(
				$con->comps->opts_lookup->getReportEmail(),
				__( 'Confirm this site can send email', 'wp-simple-firewall' ),
				$con->action_router->render(
					MfaEmailDeliveryVerify::class,
					[
						'site'        => (string)\wp_parse_url( Services::WpGeneral()->getHomeUrl(), \PHP_URL_HOST ),
						'verify_href' => $con->plugin_urls->noncedPluginAction(
							MfaCanEmailSendVerify::class,
							$con->plugin_urls->adminHome(),
							[
								MfaCanEmailSendVerify::FIELD_TOKEN => $verification->linkToken( $sentAt ),
							]
						),
					]
				)
			)
		);

		if ( $sent ) {
			$verification->markSent( $sentAt );
		}
		return $sent;
	}
}
