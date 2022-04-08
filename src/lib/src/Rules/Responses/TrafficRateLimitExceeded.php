<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class TrafficRateLimitExceeded extends Base {

	const SLUG = 'traffic_rate_limit_exceeded';

	protected function execResponse() :bool {
//		wp_die( 'Too many requests' );
		return true;
	}
}