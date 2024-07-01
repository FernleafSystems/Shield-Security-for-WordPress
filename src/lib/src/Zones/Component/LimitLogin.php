<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class LimitLogin extends Base {

	public function title() :string {
		return __( 'Limit Login Attempts', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Protect the login page against bots and brute-force attacks.', 'wp-simple-firewall' );
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