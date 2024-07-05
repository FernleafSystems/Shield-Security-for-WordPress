<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class CrowdsecBlocking extends Base {

	public function explanation() :array {
		return [
				   EnumEnabledStatus::GOOD => [
				   ],
				   EnumEnabledStatus::BAD  => [
					   __( 'Switch-on the CrowdSec feature to block known (crowd-sourced) malicious IPs.', 'wp-simple-firewall' ),
				   ],
			   ][ $this->enabledStatus() ];
	}

	public function title() :string {
		return __( 'CrowdSec IP Blocking', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Crowd-Sourced IP address blocking in partnership with CrowdSec.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return self::con()->comps->opts_lookup->enabledCrowdSecAutoBlock()? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}