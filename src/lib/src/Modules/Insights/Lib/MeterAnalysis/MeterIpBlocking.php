<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterIpBlocking extends MeterBase {

	const SLUG = 'ips';

	protected function title() :string {
		return __( 'IP Blocking and Bot Detection', 'wp-simple-firewall' );
	}

	protected function getComponentSlugs() :array {
		return [
			'ip_auto_block',
			'ade_threshold',
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