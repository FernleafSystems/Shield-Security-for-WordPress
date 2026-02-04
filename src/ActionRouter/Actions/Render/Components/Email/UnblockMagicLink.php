<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\IpAutoUnblockShieldUserLinkVerify;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AnyUserAuthRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Services\Services;

class UnblockMagicLink extends EmailBase {

	use AnyUserAuthRequired;
	use Traits\UserEmail;

	public const SLUG = 'email_unblock_magic_link';
	public const TEMPLATE = '/email/uaum_init.twig';

	protected function getBodyData() :array {
		$con = self::con();
		$user = Services::WpUsers()->getUserById( $this->action_data[ 'user_id' ] )->user_login;
		$ip = $this->action_data[ 'ip' ];
		$homeURL = $this->action_data[ 'home_url' ]; // Internally generated via getHomeUrl()
		$common = CommonDisplayStrings::pick( [
			'important_label',
			'details_label',
			'url_label',
			'username',
			'ip_address'
		] );

		return [
			'hrefs'   => [
				// Internally generated - don't escape here as template auto-escapes
				'unblock' => $con->plugin_urls->noncedPluginAction(
					IpAutoUnblockShieldUserLinkVerify::class,
					$homeURL,
					[
						'ip' => $ip
					]
				),
			],
			'strings' => [
				'looks_like'       => __( "It looks like you've been blocked and have clicked to have your IP address removed from the blocklist.", 'wp-simple-firewall' ),
				'please_click'     => __( 'Please click the link provided below to do so.', 'wp-simple-firewall' ),
				'details'          => $common[ 'details_label' ],
				'unblock_my_ip'    => sprintf( '%s: %s', __( 'Unblock My IP', 'wp-simple-firewall' ), $ip ),
				'or_copy'          => __( 'Or Copy-Paste', 'wp-simple-firewall' ),
				'details_url'      => sprintf( '%s: %s', $common[ 'url_label' ], $homeURL ),
				'details_username' => sprintf( '%s: %s', $common[ 'username' ], $user ),
				'details_ip'       => sprintf( '%s: %s', $common[ 'ip_address' ], $ip ),
				'important'        => $common[ 'important_label' ],
				'imp_limit'        => __( "You'll need to wait for a further 60 minutes if your IP address gets blocked again.", 'wp-simple-firewall' ),
				'imp_browser'      => __( "This link will ONLY work if it opens in the same web browser that you used to request this email.", 'wp-simple-firewall' ),
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'ip',
			'user_id',
			'home_url',
		];
	}
}
