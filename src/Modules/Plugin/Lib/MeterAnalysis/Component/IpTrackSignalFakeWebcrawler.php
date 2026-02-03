<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class IpTrackSignalFakeWebcrawler extends IpTrackSignalBase {

	public const MINIMUM_EDITION = 'starter';
	protected const SIGNAL_KEY = 'track_fakewebcrawler';

	public function title() :string {
		return sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'Fake Web Crawlers', 'wp-simple-firewall' ) );
	}

	public function descProtected() :string {
		return __( 'Bots that pretend to be official web crawlers such as Google are penalised.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Bots that pretend to be official web crawlers such as Google aren't penalised.", 'wp-simple-firewall' );
	}
}