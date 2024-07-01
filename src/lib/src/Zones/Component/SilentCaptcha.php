<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class SilentCaptcha extends Base {

	public function title() :string {
		return __( 'silentCAPTCHA', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return sprintf( __( "silentCAPTCHA is %s's exclusive WordPress Bad Bot Detection technology.", 'wp-simple-firewall' ),
			self::con()->labels->Name );
	}

	public function enabledStatus() :string {
		$con = self::con();
		$complexity = $con->comps->altcha->complexityLevel();
		$minimum = $con->opts->optGet( 'antibot_minimum' );
		if ( \in_array( $complexity, [ 'none', 'legacy', 'low' ] ) || $minimum === 0 ) {
			$status = EnumEnabledStatus::BAD;
		}
		elseif ( $minimum < 30 ) {
			$status = EnumEnabledStatus::OKAY;
		}
		else {
			$status = EnumEnabledStatus::GOOD;
		}
		return $status;
	}
}