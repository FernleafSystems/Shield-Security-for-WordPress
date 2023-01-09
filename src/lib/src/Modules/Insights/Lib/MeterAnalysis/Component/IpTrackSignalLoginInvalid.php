<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class IpTrackSignalLoginInvalid extends IpTrackSignalBase {

	protected const SIGNAL_KEY = 'track_logininvalid';
	public const WEIGHT = 40;

	public function title() :string {
		return sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'Invalid Logins', 'wp-simple-firewall' ) );
	}

	public function descProtected() :string {
		return __( 'Bots that attempt to login with non-existent usernames are penalised.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Bots that attempt to login with non-existent usernames aren't penalised.", 'wp-simple-firewall' );
	}
}