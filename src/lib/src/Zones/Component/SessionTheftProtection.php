<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class SessionTheftProtection extends Base {

	public function title() :string {
		return __( 'Session Hijacking Protection', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Protect user sessions against the threat of hijacking and theft.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		$con = self::con();
		$lookup = $con->comps->opts_lookup;
		if ( !$lookup->isModFromOptEnabled( 'session_lock' )
			 || ( empty( $con->opts->optGet( 'session_lock' ) ) && $lookup->getSessionIdleInterval() === 0 )
		) {
			$status = EnumEnabledStatus::BAD;
		}
		elseif ( !empty( $con->opts->optGet( 'session_lock' ) ) && $lookup->getSessionIdleInterval() > 0 ) {
			$status = EnumEnabledStatus::GOOD;
		}
		else {
			$status = EnumEnabledStatus::OKAY;
		}
		return $status;
	}
}