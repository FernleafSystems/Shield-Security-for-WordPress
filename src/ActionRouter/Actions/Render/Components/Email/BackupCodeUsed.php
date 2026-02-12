<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;

class BackupCodeUsed extends EmailBase {

	use Traits\UserEmail;

	public const SLUG = 'email_backup_code_used';
	public const TEMPLATE = '/email/backup_code_used.twig';

	protected function getBodyData() :array {
		$common = CommonDisplayStrings::pick( [
			'url_label',
			'username',
			'ip_address',
		] );

		return [
			'strings' => [
				'intro'            => __( 'This is a quick notice to inform you that your Backup Login code was just used.', 'wp-simple-firewall' ),
				'warning'          => __( 'Your WordPress account had only 1 backup login code.', 'wp-simple-firewall' )
				                      .' '.__( 'You must go to your profile and regenerate a new code if you want to use this method again.', 'wp-simple-firewall' ),
				'details_heading'  => __( 'Login Details', 'wp-simple-firewall' ),
				'details_url'      => sprintf( '%s: %s', $common[ 'url_label' ], $this->action_data[ 'home_url' ] ),
				'details_username' => sprintf( '%s: %s', $common[ 'username' ], $this->action_data[ 'username' ] ),
				'details_ip'       => sprintf( '%s: %s', $common[ 'ip_address' ], $this->action_data[ 'ip' ] ),
				'thanks'           => __( 'Thank You.', 'wp-simple-firewall' ),
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'home_url',
			'username',
			'ip',
		];
	}
}
