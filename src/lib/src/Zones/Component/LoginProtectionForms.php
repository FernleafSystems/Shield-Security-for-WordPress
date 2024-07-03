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
		$con = self::con();
		$lookup = $con->comps->opts_lookup;

		$status = EnumEnabledStatus::BAD;
		if ( $lookup->isModFromOptEnabled( 'enable_antibot_check' ) && \in_array( 'login', $con->opts->optGet( 'bot_protection_locations' ) ) ) {
			if ( $lookup->enabledLoginGuardAntiBotCheck() ) {
				$status = EnumEnabledStatus::GOOD;
			}
			elseif ( $lookup->enabledLoginGuardCooldown() ) {
				$status = EnumEnabledStatus::OKAY;
			}
		}
		return $status;
	}
}