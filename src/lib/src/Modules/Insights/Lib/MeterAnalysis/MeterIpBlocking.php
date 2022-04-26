<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterIpBlocking extends MeterBase {

	const SLUG = 'ips';

	protected function title() :string {
		return __( 'IP Blocking and Bot Detection', 'wp-simple-firewall' );
	}

	protected function subtitle() :string {
		return __( 'How repeat-offenders and malicious bots are handled', 'wp-simple-firewall' );
	}

	protected function description() :array {
		return [
			__( "Bot detection & IP Blocking is the core foundation to reliable, long-term, powerful WordPress protection.", 'wp-simple-firewall' ),
			__( "Your biggest threat comes from automated bots, so detecting them quickly and blocking them early is your greatest source of protection.", 'wp-simple-firewall' ),
			__( "When the security plugin detects enough bad behaviours it'll block the IP from accessing the site altogether.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponentSlugs() :array {
		return [
			'ip_autoblock',
			'ip_autoblock_limit',
			'ade_threshold',
			'author_discovery',
			'traffic_rate_limiting',
			'track_404',
			'track_loginfail',
			'track_logininvalid',
			'track_xml',
			'track_fake',
			'track_cheese',
			'track_script',
		];
	}
}