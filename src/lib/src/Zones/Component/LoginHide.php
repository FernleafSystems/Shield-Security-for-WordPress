<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class LoginHide extends Base {

	public function title() :string {
		return __( 'Hide WP Login', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Hide The WP Login Page.', 'wp-simple-firewall' );
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