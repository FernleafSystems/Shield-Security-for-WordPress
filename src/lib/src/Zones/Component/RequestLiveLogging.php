<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class RequestLiveLogging extends Base {

	public function title() :string {
		return __( 'Request Live Logging', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'View details of web requests sent to your WordPress site as they happen.', 'wp-simple-firewall' );
	}
}