<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class PasswordStrength extends Base {

	public function title() :string {
		return __( 'Enforce Minimum Password Strength', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Enforce strong passwords for all users.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return self::con()->opts->optGet( 'pass_min_strength' ) >= 3 ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}