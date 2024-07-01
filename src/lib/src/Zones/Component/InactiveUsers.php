<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class InactiveUsers extends Base {

	public function title() :string {
		return __( 'Auto-Suspend Inactive Users', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Disable account access for inactive users.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return self::con()->comps->user_suspend->isSuspendAutoIdleEnabled() ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}