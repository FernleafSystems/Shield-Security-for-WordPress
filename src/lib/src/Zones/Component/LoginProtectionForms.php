<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class LoginProtectionForms extends Base {

	public function title() :string {
		return __( 'Login, Register & Lost Password Forms', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return sprintf( __( 'Select which user forms should be protected against brute-force attacks.', 'wp-simple-firewall' ),
			self::con()->getHumanName() );
	}

	public function enabledStatus() :string {
		$forms = self::con()->opts->optGet( 'bot_protection_locations' );
		return \in_array( 'login', $forms ) ? EnumEnabledStatus::GOOD : ( empty( $forms ) ? EnumEnabledStatus::BAD : EnumEnabledStatus::OKAY );
	}
}