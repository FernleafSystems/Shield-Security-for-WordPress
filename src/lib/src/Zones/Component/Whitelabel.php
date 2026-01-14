<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class Whitelabel extends Base {

	public function title() :string {
		return __( 'White Label', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return sprintf( __( 'Re-brand the %s plugin.', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	public function enabledStatus() :string {
		return self::con()->comps->whitelabel->isEnabled() ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}