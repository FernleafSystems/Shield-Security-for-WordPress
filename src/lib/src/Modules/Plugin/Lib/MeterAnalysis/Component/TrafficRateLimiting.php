<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class TrafficRateLimiting extends Base {

	use Traits\OptConfigBased;

	public const MINIMUM_EDITION = 'business';
	public const SLUG = 'traffic_rate_limiting';
	public const WEIGHT = 2;

	protected function getOptConfigKey() :string {
		return 'enable_limiter';
	}

	protected function testIfProtected() :bool {
		return self::con()->comps->opts_lookup->enabledTrafficLimiter();
	}

	public function title() :string {
		return __( 'Traffic Rate Limiting', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Traffic rate limiting reduces the likelihood that bots can overwhelm your site.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Traffic is never rate limited meaning abusive bots and crawlers may consume resources without limits and potentially overload your site.", 'wp-simple-firewall' );
	}
}