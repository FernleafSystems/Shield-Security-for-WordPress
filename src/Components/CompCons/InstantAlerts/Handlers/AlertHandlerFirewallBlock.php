<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertFirewallBlock;

class AlertHandlerFirewallBlock extends AlertHandlerBase {

	public function alertAction() :string {
		return EmailInstantAlertFirewallBlock::class;
	}

	public function alertTitle() :string {
		return __( 'Firewall Block Detected', 'wp-simple-firewall' );
	}

	public function alertDataKeys() :array {
		return [
			'firewall_block',
		];
	}

	public function isImmediateAlert() :bool {
		return true;
	}
}
