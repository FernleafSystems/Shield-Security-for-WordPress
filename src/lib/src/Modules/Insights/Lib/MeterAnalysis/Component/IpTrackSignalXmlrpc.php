<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class IpTrackSignalXmlrpc extends IpTrackSignalBase {

	protected const SIGNAL_KEY = 'track_xmlrpc';
	public const WEIGHT = 40;

	public function title() :string {
		return sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'XML-RPC Access', 'wp-simple-firewall' ) );
	}

	public function descProtected() :string {
		return __( 'Bots that attempt to access XML-RPC are penalised.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Bots that attempt to access XML-RPC aren't penalised.", 'wp-simple-firewall' );
	}
}