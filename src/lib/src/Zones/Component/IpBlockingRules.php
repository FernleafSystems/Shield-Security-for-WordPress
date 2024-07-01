<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class IpBlockingRules extends Base {

	public function title() :string {
		return __( 'IP Blocking Rules', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Block IPs that persistently trigger offenses against your site.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return EnumEnabledStatus::GOOD;
	}
}