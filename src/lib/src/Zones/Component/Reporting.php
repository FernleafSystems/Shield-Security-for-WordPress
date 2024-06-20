<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class Reporting extends Base {

	public function title() :string {
		return __( 'Reporting', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( "See what's happening with reports.", 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return EnumEnabledStatus::GOOD;
	}
}