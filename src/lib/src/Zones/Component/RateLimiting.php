<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class RateLimiting extends Base {

	public function title() :string {
		return __( 'Rate Limit Abusive Requests', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Apply rate limiting restrictions to high-volume requests.', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( '.', 'wp-simple-firewall' ),
		];
	}

	protected function hasCapability() :bool {
		return self::con()->caps->canTrafficRateLimit();
	}

	public function enabledStatus() :string {
		return self::con()->comps->opts_lookup->enabledTrafficLimiter()? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}