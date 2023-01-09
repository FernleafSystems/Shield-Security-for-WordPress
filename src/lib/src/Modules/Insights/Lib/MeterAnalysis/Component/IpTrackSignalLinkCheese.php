<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class IpTrackSignalLinkCheese extends IpTrackSignalBase {

	protected const SIGNAL_KEY = 'track_linkcheese';
	public const WEIGHT = 20;

	public function title() :string {
		return sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'Link-Cheese', 'wp-simple-firewall' ) );
	}

	public function descProtected() :string {
		return __( 'Bots that trigger the link-cheese bait are penalised.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Bots that trigger the link-cheese bait aren't penalised.", 'wp-simple-firewall' );
	}
}