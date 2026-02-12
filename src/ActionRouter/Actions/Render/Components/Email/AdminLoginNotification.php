<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;

class AdminLoginNotification extends EmailBase {

	public const SLUG = 'email_admin_login_notification';
	public const TEMPLATE = '/email/admin_login_notification.twig';

	protected function getBodyData() :array {
		$con = self::con();
		$common = CommonDisplayStrings::pick( [
			'site_url_label',
			'username',
			'ip_address',
		] );

		return [
			'strings' => [
				'intro'            => sprintf(
					/* translators: %1$s: plugin name, %2$s: user role */
					__( 'As requested, %1$s is notifying you of a successful %2$s login to a WordPress site that you manage.', 'wp-simple-firewall' ),
					$con->labels->Name,
					$this->action_data[ 'role_name' ]
				),
				'important'        => sprintf(
					__( 'Important: %s', 'wp-simple-firewall' ),
					__( 'This user may now be subject to additional Two-Factor Authentication before completing their login.', 'wp-simple-firewall' )
				),
				'details_heading'  => __( 'Details for this user are below:', 'wp-simple-firewall' ),
				'details_site'     => sprintf( '%s: %s', $common[ 'site_url_label' ], $this->action_data[ 'home_url' ] ),
				'details_username' => sprintf( '%s: %s', $common[ 'username' ], $this->action_data[ 'username' ] ),
				'details_email'    => sprintf( '%s: %s', __( 'Email', 'wp-simple-firewall' ), $this->action_data[ 'user_email' ] ),
				'details_ip'       => sprintf( '%s: %s', $common[ 'ip_address' ], $this->action_data[ 'ip' ] ),
				'thanks'           => __( 'Thanks.', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'role_name',
			'home_url',
			'username',
			'user_email',
			'ip',
		];
	}
}
