<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class MeterIpBlocking extends MeterBase {

	public const SLUG = 'ips';

	protected function getWorkingMods() :array {
		return [ $this->getCon()->getModule_IPs() ];
	}

	public function title() :string {
		return __( 'IP Blocking and Bot Detection', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'How repeat-offenders and malicious bots are handled', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( "Bot Detection & IP Blocking form the core foundation to reliable, powerful, and long-term WordPress protection.", 'wp-simple-firewall' ),
			__( "Your biggest threat comes from automated bots, so detecting them quickly and blocking them early is your greatest source of protection.", 'wp-simple-firewall' ),
			__( "When the security plugin detects enough bad behaviours it'll block the IP from accessing the site altogether.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponents() :array {
		return [
			Component\IpAutoBlock::class,
			Component\IpAutoBlockOffenseLimit::class,
			Component\IpAutoBlockCrowdsec::class,
			Component\AdeTreshold::class,
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