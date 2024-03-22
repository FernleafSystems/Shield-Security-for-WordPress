<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts;

use FernleafSystems\Wordpress\Services\Services;

abstract class EmailInstantAlertBase extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\EmailBase {

	public const TEMPLATE = '/email/instant_alerts/instant_alert_standard.twig';

	protected function getBodyData() :array {
		return [
			'hrefs'   => [
				'url_site'  => Services::WpGeneral()->getHomeUrl(),
				'url_users' => Services::WpGeneral()->getAdminUrl( 'users.php' ),
			],
			'strings' => [
				'url_site' => __( 'Site Address (URL)', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'alert_groups' => $this->buildAlertGroups(),
			],
		];
	}

	protected function buildAlertGroups() :array {
		return [];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'alert_data',
		];
	}
}