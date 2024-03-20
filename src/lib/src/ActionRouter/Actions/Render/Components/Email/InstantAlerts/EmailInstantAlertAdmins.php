<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts;

use FernleafSystems\Wordpress\Services\Services;

class EmailInstantAlertAdmins extends InstantAlertBase {

	public const SLUG = 'email_instant_alert_admins';
	public const TEMPLATE = '/email/instant_alerts/instant_alert_admins.twig';

	protected function getBodyData() :array {
		return [
			'strings' => [
				'added'      => 'New Admin(s)',
				'removed'    => 'Deleted Admin(s)',
				'user_pass'  => 'Password Updated',
				'user_email' => 'Email Updated',
				'promoted'   => 'Promoted To Admin',
				'demoted'    => 'Demoted From Admin',
			],
			'vars'    => [
				'url_site'  => Services::WpGeneral()->getHomeUrl(),
				'url_users' => Services::WpGeneral()->getAdminUrl( 'users.php' ),
				'admins'    => $this->action_data[ 'alert_data' ],
			]
		];
	}
}