<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class IpTrackSignalLoginFailed extends IpTrackSignalBase {

	public const WEIGHT = 5;
	protected const SIGNAL_KEY = 'track_loginfailed';

	public function title() :string {
		return sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'Failed Logins', 'wp-simple-firewall' ) );
	}

	public function descProtected() :string {
		return __( 'Bots that attempt to login and fail are penalised.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Bots that attempt to login and fail aren't penalised.", 'wp-simple-firewall' );
	}
}