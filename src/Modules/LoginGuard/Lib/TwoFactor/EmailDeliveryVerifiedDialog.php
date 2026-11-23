<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * The dialog shown once, on the page the emailed verification link opens, when that link verifies email delivery.
 * Opening the link again does nothing, so it doesn't come back.
 *
 * @phpstan-type Dialog array{title:string,message:string,icon_class:string,strings:array{ok:string}}
 */
class EmailDeliveryVerifiedDialog {

	use PluginControllerConsumer;

	public function isRequired() :bool {
		return self::con()->this_req->email_delivery_verified_by_link === true;
	}

	/**
	 * @return Dialog
	 */
	public function build() :array {
		return [
			'title'      => __( 'Email 2FA is now available', 'wp-simple-firewall' ),
			'message'    => __( 'You have verified that this site can send emails. Email 2FA is available to your users.', 'wp-simple-firewall' ),
			'icon_class' => 'bi bi-envelope-check',
			'strings'    => [
				'ok' => __( 'OK', 'wp-simple-firewall' ),
			],
		];
	}
}
