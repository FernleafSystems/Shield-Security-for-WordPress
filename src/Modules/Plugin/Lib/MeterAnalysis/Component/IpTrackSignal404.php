<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class IpTrackSignal404 extends IpTrackSignalBase {

	public const MINIMUM_EDITION = 'starter';
	public const WEIGHT = 2;
	protected const SIGNAL_KEY = 'track_404';

	public function title() :string {
		return sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), '404s' );
	}

	public function descProtected() :string {
		return __( 'Bots that trigger 404 errors are penalised.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Bots that trigger 404 errors aren't penalised.", 'wp-simple-firewall' );
	}
}