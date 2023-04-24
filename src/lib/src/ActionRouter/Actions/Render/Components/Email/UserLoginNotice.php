<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email;

class UserLoginNotice extends EmailBase {

	use Traits\UserEmail;

	public const SLUG = 'email_user_login_notice';
	public const TEMPLATE = '/email/user_login_notice.twig';

	protected function getBodyData() :array {
		return [
			'hrefs'   => [
				'home_url' => $this->action_data[ 'home_url' ],
			],
			'strings' => [
				'login_occurred'  => __( 'A successful login to your WordPress account has just occurred.', 'wp-simple-firewall' ),
				'unexpected'      => __( 'If this is unexpected or suspicious, please contact your site administrator immediately.', 'wp-simple-firewall' ),
				'details_heading' => __( 'Details for this login are below:', 'wp-simple-firewall' ),
				'details'         => [
					'details_url'      => sprintf( '%s: %s', __( 'Site URL', 'wp-simple-firewall' ), $this->action_data[ 'home_url' ] ),
					'details_username' => sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $this->action_data[ 'username' ] ),
					'details_ip'       => sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), $this->action_data[ 'ip' ] ),
					'details_time'     => sprintf( '%s: %s', __( 'Time', 'wp-simple-firewall' ), $this->action_data[ 'timestamp' ] ),
				]
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'home_url',
			'username',
			'ip',
			'timestamp',
		];
	}
}