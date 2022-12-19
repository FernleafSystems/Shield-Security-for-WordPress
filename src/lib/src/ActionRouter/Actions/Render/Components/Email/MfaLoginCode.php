<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email;

use FernleafSystems\Wordpress\Services\Services;

class MfaLoginCode extends EmailBase {

	public const SLUG = 'email_mfa_login_code';
	public const TEMPLATE = '/email/lp_2fa_email_code.twig';

	protected function getBodyData() :array {
		$user = Services::WpUsers()->getUserById( $this->action_data[ 'user_id' ] )->user_login;
		$ip = $this->action_data[ 'ip' ];
		$homeURL = $this->action_data[ 'home_url' ];

		return [
			'flags'   => [
				'show_login_link' => !$this->getCon()->isRelabelled()
			],
			'vars'    => [
				'code' => $this->action_data[ 'otp' ]
			],
			'hrefs'   => [
				'login_link' => 'https://shsec.io/96',
			],
			'strings' => [
				'someone'          => __( 'Someone attempted to login into this WordPress site using your account.', 'wp-simple-firewall' ),
				'requires'         => __( 'Login requires verification with the following code.', 'wp-simple-firewall' ),
				'verification'     => __( 'Verification Code', 'wp-simple-firewall' ),
				'login_link'       => __( 'Why no login link?', 'wp-simple-firewall' ),
				'details_heading'  => __( 'Login Details', 'wp-simple-firewall' ),
				'details_url'      => sprintf( '%s: %s', __( 'URL', 'wp-simple-firewall' ), $homeURL ),
				'details_username' => sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $user ),
				'details_ip'       => sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), $ip ),
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'ip',
			'user_id',
			'home_url',
			'otp',
		];
	}
}