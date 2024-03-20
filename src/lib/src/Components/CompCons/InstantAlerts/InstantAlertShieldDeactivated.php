<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertShieldDeactivated;
use FernleafSystems\Wordpress\Services\Services;

class InstantAlertShieldDeactivated extends InstantAlertBase {

	public function __construct() {
		$this->alertActionData = [
			'details' => [],
		];
	}

	protected function alertAction() :string {
		return EmailInstantAlertShieldDeactivated::class;
	}

	protected function alertTitle() :string {
		return __( 'Shield Deactivated', 'wp-simple-firewall' );
	}

	protected function run() {
		parent::run();

		add_action( self::con()->prefix( 'deactivate_plugin' ), function () {
			$thisReq = self::con()->this_req;
			$user = Services::WpUsers()->getCurrentWpUser();
			$this->alertActionData = [
				'details' => [
					'time' => $thisReq->carbon_tz->toIso8601String(),
					'path' => $thisReq->wp_is_wpcli ? 'WP-CLI' : esc_html( $thisReq->path ),
					'user' => $user instanceof \WP_User ? $user->user_login : 'unknown',
					'ip'   => $thisReq->wp_is_wpcli ? 'n/a' : $thisReq->ip,
				],
			];
		} );
	}
}