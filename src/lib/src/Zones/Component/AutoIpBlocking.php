<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class AutoIpBlocking extends Base {

	public function title() :string {
		return __( 'Automatic IP Blocking', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Monitor for malicious visitors and automatically block their IP addresses.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return self::con()->comps->opts_lookup->enabledIpAutoBlock() ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}