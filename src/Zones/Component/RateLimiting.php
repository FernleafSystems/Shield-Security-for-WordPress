<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Services\Services;

class RateLimiting extends Base {

	public function title() :string {
		return __( 'Rate Limit Abusive Requests', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Apply rate limiting restrictions to high-volume requests.', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();

		if ( self::con()->comps->opts_lookup->enabledTrafficLimiter() ) {
			$source = Services::Request()->getIpDetector()->getPublicRequestSource();
			if ( $source === 'REMOTE_ADDR' ) {
				$status[ 'level' ] = EnumEnabledStatus::GOOD;
			}
			else {
				$status[ 'level' ] = EnumEnabledStatus::OKAY;
				/* translators: %s: name of the IP address source setting */
				$status[ 'exp' ][] = sprintf( __( 'Traffic rate limiting is enabled, but the current IP source (%s) is not recommended for accurate visitor tracking.', 'wp-simple-firewall' ), $source );
			}
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = $this->explanationFixedBy( __( "There's no limit to the number of requests that a single visitor may make against your site.", 'wp-simple-firewall' ), 'enable_limiter' );
		}

		return $status;
	}

	protected function configureTreatsBadStatusAsWarning() :bool {
		return true;
	}
}
