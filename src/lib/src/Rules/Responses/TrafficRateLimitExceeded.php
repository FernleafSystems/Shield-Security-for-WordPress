<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

/**
 * @deprecated 18.6
 */
class TrafficRateLimitExceeded extends Base {

	public const SLUG = 'traffic_rate_limit_exceeded';

	public function execResponse() :void {
//		wp_die( 'Too many requests' );
	}
}