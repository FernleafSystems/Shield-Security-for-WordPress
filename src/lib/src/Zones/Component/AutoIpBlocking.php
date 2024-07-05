<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class AutoIpBlocking extends Base {

	public function explanation() :array {
		return [
				   EnumEnabledStatus::GOOD => [
				   ],
				   EnumEnabledStatus::OKAY => [
					   __( 'The offense limit is quite high - you may want to consider decreasing it.', 'wp-simple-firewall' ),
				   ],
				   EnumEnabledStatus::BAD  => [
					   __( 'Set a limit to offenses allowed before visitor IP is automatically blocked.', 'wp-simple-firewall' ),
				   ],
			   ][ $this->enabledStatus() ];
	}

	public function title() :string {
		return __( 'Automatic IP Blocking', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Monitor for malicious visitors and automatically block their IP addresses.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		$lookup = self::con()->comps->opts_lookup;
		return $lookup->enabledIpAutoBlock() ? ( $lookup->getIpAutoBlockOffenseLimit() < 20 ? EnumEnabledStatus::GOOD : EnumEnabledStatus::OKAY ) : EnumEnabledStatus::BAD;
	}
}