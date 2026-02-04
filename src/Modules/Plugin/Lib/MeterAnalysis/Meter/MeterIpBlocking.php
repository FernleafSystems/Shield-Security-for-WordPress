<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class MeterIpBlocking extends MeterBase {

	public const SLUG = 'ips';

	public function title() :string {
		return __( 'Bad-Bot Detection and IP Blocking', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( "How well you're protected against malicious bots and visitors", 'wp-simple-firewall' );
	}

	public function description() :array {
		$con = self::con();
		return [
			__( "Your #1 security threat is from automated bots.", 'wp-simple-firewall' ),
			\implode( ' ', [
				__( "Bot Detection & IP Blocking together form the core foundation to powerful WordPress protection that actually works.", 'wp-simple-firewall' ),
				__( "Detecting them early and blocking them, is your greatest source of protection.", 'wp-simple-firewall' ),
			] ),
			\implode( ' ', [
				sprintf( __( "%s creates a risk assessment very quickly as it tracks bad visitors in many separate areas.", 'wp-simple-firewall' ), $con->labels->Name ),
				__( "Think of it as 'Limit Login Attempts' but applied across all features on a WordPress site, not just the login page.", 'wp-simple-firewall' ),
				__( "When enough bad behaviours are detected, it'll block the IP from accessing the site altogether.", 'wp-simple-firewall' ),
				__( "Eventually, when the visitor leaves you alone, it'll clean the stale IPs from your block lists, keeping your site performance running optimally.", 'wp-simple-firewall' ),
			] ),
		];
	}

	protected function getComponents() :array {
		return [
			Component\IpAutoBlockShield::class,
			Component\IpAutoBlockOffenseLimit::class,
			Component\IpAutoBlockCrowdsec::class,
			Component\IpAdeThreshold::class,
			Component\LockdownAuthorDiscovery::class,
			Component\TrafficRateLimiting::class,
			Component\IpTrackSignal404::class,
			Component\IpTrackSignalLoginFailed::class,
			Component\IpTrackSignalLoginInvalid::class,
			Component\IpTrackSignalXmlrpc::class,
			Component\IpTrackSignalFakeWebcrawler::class,
			Component\IpTrackSignalLinkCheese::class,
			Component\IpTrackSignalInvalidScript::class,
		];
	}
}