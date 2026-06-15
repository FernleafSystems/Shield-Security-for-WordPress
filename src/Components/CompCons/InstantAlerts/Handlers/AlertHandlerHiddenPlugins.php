<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertHiddenPlugins;

class AlertHandlerHiddenPlugins extends AlertHandlerBase {

	public function alertAction() :string {
		return EmailInstantAlertHiddenPlugins::class;
	}

	public function alertTitle() :string {
		return __( 'Hidden Plugin Detected', 'wp-simple-firewall' );
	}

	public function alertDataKeys() :array {
		return [
			'hidden_plugins',
		];
	}

	public function isImmediateAlert() :bool {
		return true;
	}
}
