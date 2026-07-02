<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertCloakedPlugins;

class AlertHandlerCloakedPlugins extends AlertHandlerBase {

	public function alertAction() :string {
		return EmailInstantAlertCloakedPlugins::class;
	}

	public function alertTitle() :string {
		return __( 'Cloaked Plugin Detected', 'wp-simple-firewall' );
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
