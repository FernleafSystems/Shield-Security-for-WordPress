<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class IpTrackSignalInvalidScript extends IpTrackSignalBase {

	protected const SIGNAL_KEY = 'track_invalidscript';
	public const WEIGHT = 20;

	public function title() :string {
		return sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'Invalid Scripts', 'wp-simple-firewall' ) );
	}

	public function descProtected() :string {
		return __( 'Bots that attempt to access invalid scripts or WordPress files are penalised.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Bots that attempt to access invalid scripts or WordPress files aren't penalised.", 'wp-simple-firewall' );
	}
}