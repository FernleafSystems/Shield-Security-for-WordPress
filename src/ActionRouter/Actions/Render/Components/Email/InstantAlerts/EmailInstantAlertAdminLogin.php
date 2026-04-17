<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Services\Services;

class EmailInstantAlertAdminLogin extends EmailInstantAlertBase {

	public const SLUG = 'email_instant_alert_admin_login';

	protected function getBodyData() :array {
		$alertData = $this->loginAlertData();

		return Services::DataManipulation()->mergeArraysRecursive( parent::getBodyData(), [
			'strings' => [
				'intro' => [
					sprintf(
						/* translators: %1$s: plugin name, %2$s: user role */
						__( 'As requested, %1$s is notifying you of a successful %2$s login to a WordPress site that you manage.', 'wp-simple-firewall' ),
						self::con()->labels->Name,
						$alertData['role_name']
					),
					sprintf(
						__( 'Important: %s', 'wp-simple-firewall' ),
						__( 'This user may now be subject to additional Two-Factor Authentication before completing their login.', 'wp-simple-firewall' )
					),
				],
			],
		] );
	}

	protected function buildAlertGroups() :array {
		$alertData = $this->loginAlertData();
		$labels = CommonDisplayStrings::pick( [
			'username',
			'ip_address_label',
		] );

		return [
			'admin_login' => [
				'title' => __( 'Login Details', 'wp-simple-firewall' ),
				'items' => [
					[
						'text' => sprintf( '%s: %s', $labels[ 'username' ], $alertData[ 'username' ] ),
						'href' => '',
					],
					[
						'text' => sprintf( '%s: %s', __( 'Email', 'wp-simple-firewall' ), $alertData[ 'user_email' ] ),
						'href' => '',
					],
					[
						'text' => sprintf( '%s: %s', $labels[ 'ip_address_label' ], $alertData[ 'ip' ] ),
						'href' => '',
					],
				],
			],
		];
	}

	/**
	 * @return array{role_name:string,username:string,user_email:string,ip:string}
	 */
	private function loginAlertData() :array {
		/** @var array{role_name:string,username:string,user_email:string,ip:string} $alertData */
		$alertData = $this->action_data['alert_data']['admin_login'];
		return $alertData;
	}
}
