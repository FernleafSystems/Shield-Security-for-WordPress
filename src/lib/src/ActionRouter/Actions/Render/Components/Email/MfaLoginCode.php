<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;
use FernleafSystems\Wordpress\Services\Services;

class MfaLoginCode extends EmailBase {

	use Traits\UserEmail;

	public const SLUG = 'email_mfa_login_code';
	public const TEMPLATE = '/email/lp_2fa_email_code.twig';

	protected function getBodyData() :array {
		/** @var Options $opts */
		$opts = self::con()->getModule_LoginGuard()->opts();
		$user = Services::WpUsers()->getUserById( $this->action_data[ 'user_id' ] )->user_login;

		return [
			'flags'   => [
				'can_auto_login'  => $opts->canAutoLoginURL(),
			],
			'vars'    => [
				'code' => $this->action_data[ 'otp' ],
			],
			'hrefs'   => [
				'login_link' => 'https://shsec.io/96',
				'auto_login' => $this->action_data[ 'url_auto_login' ],
			],
			'strings' => [
				'someone'          => __( 'Someone attempted to login into this WordPress site using your account.', 'wp-simple-firewall' ),
				'requires'         => __( 'Login requires verification with the following code.', 'wp-simple-firewall' ),
				'verification'     => __( 'Verification Code', 'wp-simple-firewall' ),
				'auto_login'       => __( 'Autologin URL', 'wp-simple-firewall' ),
				'details_heading'  => __( 'Login Details', 'wp-simple-firewall' ),
				'details_url'      => sprintf( '%s: %s', __( 'URL', 'wp-simple-firewall' ), $this->action_data[ 'home_url' ] ),
				'details_username' => sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $user ),
				'details_ip'       => sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), $this->action_data[ 'ip' ] ),
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