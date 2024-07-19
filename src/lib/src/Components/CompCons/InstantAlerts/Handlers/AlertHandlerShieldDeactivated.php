<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertShieldDeactivated;
use FernleafSystems\Wordpress\Services\Services;

class AlertHandlerShieldDeactivated extends AlertHandlerBase {

	public function alertAction() :string {
		return EmailInstantAlertShieldDeactivated::class;
	}

	public function alertTitle() :string {
		return __( 'Shield Deactivated', 'wp-simple-firewall' );
	}

	public function alertDataKeys() :array {
		return [
			'shield_deactivated',
		];
	}

	protected function run() {
		add_action( self::con()->prefix( 'deactivate_plugin' ), function () {
			$thisReq = self::con()->this_req;
			$user = Services::WpUsers()->getCurrentWpUser();
			self::con()->comps->instant_alerts->updateAlertDataFor( $this, [
				'shield_deactivated' => [
					'time' => $thisReq->carbon_tz->toIso8601String(),
					'path' => $thisReq->wp_is_wpcli ? 'WP-CLI' : esc_html( $thisReq->path ),
					'user' => $user instanceof \WP_User ? $user->user_login : 'unknown',
					'ip'   => $thisReq->wp_is_wpcli ? 'n/a' : $thisReq->ip,
				]
			] );
		} );
	}

	public function isImmediateAlert() :bool {
		return true;
	}
}