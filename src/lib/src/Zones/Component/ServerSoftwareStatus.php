<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class ServerSoftwareStatus extends Base {

	public function title() :string {
		return __( 'Server Software Status', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'A high-level overview of your WordPress hosting server software.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return EnumEnabledStatus::GOOD;
	}

	protected function hasConfigAction() :bool {
		return false;
	}
}