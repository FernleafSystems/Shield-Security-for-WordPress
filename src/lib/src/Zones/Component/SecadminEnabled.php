<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class SecadminEnabled extends Base {

	public function title() :string {
		return __( 'Security Admin Protection', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return sprintf( __( 'The Security Admin system protects WordPress and the %s plugin against tampering.', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	public function enabledStatus() :string {
		return self::con()->comps->sec_admin->isEnabledSecAdmin() ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}