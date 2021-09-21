<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	public function getAutoCleanDays() :int {
		$days = $this->getOpt( 'auto_clean' );
		if ( !$this->isPremium() ) {
			$this->setOpt( 'auto_clean', min( $days, 7 ) );
		}
		return (int)$this->getOpt( 'auto_clean' );
	}

	public function getCustomExclusions() :array {
		$ex = $this->getOpt( 'custom_exclusions' );
		return is_array( $ex ) ? $ex : [];
	}

	public function getLimitRequestCount() :int {
		return (int)$this->getOpt( 'limit_requests' );
	}

	public function getLimitTimeSpan() :int {
		return (int)$this->getOpt( 'limit_time_span' );
	}

	public function getReqTypeExclusions() :array {
		$ex = $this->getOpt( 'type_exclusions' );
		return is_array( $ex ) ? $ex : [];
	}

	public function isTrafficLoggerEnabled() :bool {
		return $this->isOpt( 'enable_traffic', 'Y' ) && $this->isOpt( 'enable_logger', 'Y' )
			   && $this->getAutoCleanDays() > 0;
	}

	public function isTrafficLimitEnabled() :bool {
		return $this->isPremium() && $this->isTrafficLoggerEnabled() && $this->isOpt( 'enable_limiter', 'Y' )
			   && ( $this->getLimitTimeSpan() > 0 ) && ( $this->getLimitRequestCount() > 0 );
	}

	/**
	 * @deprecated 12.0
	 */
	public function getMaxEntries() :int {
		return PHP_INT_MAX;
	}
}