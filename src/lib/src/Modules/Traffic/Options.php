<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Services\Services;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	public function preSave() :void {
		if ( $this->isOptChanged( 'custom_exclusions' ) ) {
			$this->setOpt( 'custom_exclusions', \array_filter( \array_map(
				function ( $excl ) {
					return \trim( esc_js( $excl ) );
				},
				$this->getOpt( 'custom_exclusions' )
			) ) );
		}

		if ( $this->isOpt( 'enable_limiter', 'Y' ) && !$this->isTrafficLoggerEnabled() ) {
			$this->setOpt( 'enable_logger', 'Y' );
			if ( $this->getAutoCleanDays() === 0 ) {
				$this->resetOptToDefault( 'auto_clean' );
			}
		}

		if ( $this->isOpt( 'enable_live_log', 'Y' ) && !$this->isTrafficLoggerEnabled() ) {
			$this->setOpt( 'enable_live_log', 'N' )
				 ->setOpt( 'live_log_started_at', 0 );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function preSetOptChecks( string $key, $newValue ) {
		if ( $key === 'auto_clean' && $newValue > self::con()->caps->getMaxLogRetentionDays() ) {
			throw new \Exception( 'Cannot set log retention days to anything longer than max' );
		}
	}

	public function getAutoCleanDays() :int {
		$days = (int)\min( $this->getOpt( 'auto_clean' ), self::con()->caps->getMaxLogRetentionDays() );
		$this->setOpt( 'auto_clean', $days );
		return $days;
	}

	public function getCustomExclusions() :array {
		return $this->getOpt( 'custom_exclusions' );
	}

	public function getLimitRequestCount() :int {
		return (int)$this->getOpt( 'limit_requests' );
	}

	public function getLimitTimeSpan() :int {
		return (int)$this->getOpt( 'limit_time_span' );
	}

	public function isTrafficLoggerEnabled() :bool {
		return $this->isOpt( 'enable_traffic', 'Y' )
			   && $this->isOpt( 'enable_logger', 'Y' )
			   && $this->getAutoCleanDays() > 0;
	}

	public function isTrafficLimitEnabled() :bool {
		return $this->isTrafficLoggerEnabled() && $this->isOpt( 'enable_limiter', 'Y' )
			   && ( $this->getLimitTimeSpan() > 0 ) && ( $this->getLimitRequestCount() > 0 );
	}

	public function liveLoggingTimeRemaining() :int {
		$now = Services::Request()->ts();

		if ( $this->isOpt( 'enable_live_log', 'Y' ) ) {
			if ( $this->getOpt( 'live_log_started_at' ) > 0 ) {
				if ( $this->liveLoggingDuration() <= $now - $this->getOpt( 'live_log_started_at' ) ) {
					$this->setOpt( 'live_log_started_at', 0 )
						 ->setOpt( 'enable_live_log', 'N' );
				}
			}
			elseif ( $this->getOpt( 'live_log_started_at' ) === 0 ) {
				$this->setOpt( 'live_log_started_at', $now );
			}
		}
		else {
			$this->setOpt( 'live_log_started_at', 0 );
		}

		$startedAt = $this->getOpt( 'live_log_started_at' );
		return $startedAt > 0 ? \max( 0, $this->liveLoggingDuration() - ( $now - $startedAt ) ) : 0;
	}

	public function liveLoggingDuration() :int {
		return (int)\min( \DAY_IN_SECONDS, \max( \MINUTE_IN_SECONDS, apply_filters( 'shield/live_traffic_log_duration', \HOUR_IN_SECONDS/2 ) ) );
	}
}