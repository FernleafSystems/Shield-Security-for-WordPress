<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Ips extends Base {

	public function tooltip() :string {
		return 'Edit settings for the entire IP Blocking Zone';
	}

	public function components() :array {
		return [
			Component\AutoIpBlocking::class,
			Component\CrowdsecBlocking::class,
			Component\SilentCaptcha::class,
			Component\BotActions::class,
		];
	}

	public function title() :string {
		return __( 'Bots & IPs', 'wp-simple-firewall' );
	}

	protected function getUnderlyingModuleZone() :?string {
		return Component\Modules\ModuleIps::class;
	}
}
