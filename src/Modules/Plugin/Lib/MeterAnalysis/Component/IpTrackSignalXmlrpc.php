<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class IpTrackSignalXmlrpc extends IpTrackSignalBase {

	public const MINIMUM_EDITION = 'starter';
	public const WEIGHT = 6;
	protected const SIGNAL_KEY = 'track_xmlrpc';

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