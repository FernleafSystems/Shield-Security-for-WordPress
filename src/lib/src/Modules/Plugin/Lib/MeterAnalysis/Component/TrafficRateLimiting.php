<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Options;

class TrafficRateLimiting extends Base {

	public const SLUG = 'traffic_rate_limiting';
	public const WEIGHT = 35;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_Traffic();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->isTrafficLimitEnabled();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_Traffic();
		return $mod->isModOptEnabled() ? $this->link( 'enable_limiter' ) : $this->link( 'enable_traffic' );
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