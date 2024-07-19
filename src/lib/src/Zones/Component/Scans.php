<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class Scans extends Base {

	public function title() :string {
		return __( 'Scans', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Shield Scanners.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return EnumEnabledStatus::GOOD;
	}
}