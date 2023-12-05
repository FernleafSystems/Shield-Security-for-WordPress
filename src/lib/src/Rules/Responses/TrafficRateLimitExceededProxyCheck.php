<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class TrafficRateLimitExceededProxyCheck extends Base {

	public const SLUG = 'traffic_rate_limit_exceeded_proxy_check';

	public function execResponse() :bool {
//		wp_die( 'Too many requests' );
		return true;
	}
}