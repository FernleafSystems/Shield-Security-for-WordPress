<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	public const SLUG = 'traffic';

	/**
	 * @var Lib\RequestLogger
	 */
	private $requestLogger;

	public function getRequestLogger() :Lib\RequestLogger {
		return $this->requestLogger ?? $this->requestLogger = new Lib\RequestLogger();
	}

	protected function enumRuleBuilders() :array {
		/** @var Options $opts */
		$opts = $this->opts();
		return [
			$opts->isTrafficLimitEnabled() ? Rules\Build\IsRateLimitExceeded::class : null,
		];
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->opts();
		$opts->setOpt( 'custom_exclusions', \array_filter( \array_map(
			function ( $excl ) {
				return \trim( esc_js( $excl ) );
			},
			$opts->getCustomExclusions()
		) ) );

		if ( $opts->isOpt( 'enable_limiter', 'Y' ) && !$opts->isTrafficLoggerEnabled() ) {
			$opts->setOpt( 'enable_logger', 'Y' );
			if ( $opts->getAutoCleanDays() === 0 ) {
				$opts->resetOptToDefault( 'auto_clean' );
			}
		}

		$this->autoSwitchLiveTraffic();
	}

	protected function autoSwitchLiveTraffic() :void {
		/** @var Options $opts */
		$opts = $this->opts();
		if ( $opts->isLiveTrafficEnabled() ) {
			$now = Services::Request()->ts();
			if ( $opts->getOpt( 'live_log_started_at' ) === 0 ) {
				$opts->setOpt( 'live_log_started_at', $now );
			}
			elseif ( $opts->getOpt( 'live_log_started_at' ) < $now - \HOUR_IN_SECONDS ) {
				$opts->setOpt( 'live_log_started_at', 0 )
					 ->setOpt( 'enable_live_log', 'N' );
			}
		}
		error_log( var_export( $opts->getOpt( 'live_log_started_at' ), true ) );
	}

	public function runHourlyCron() {
		$this->autoSwitchLiveTraffic();
	}

	protected function isReadyToExecute() :bool {
		return self::con()->getModule_Data()->getDbH_ReqLogs()->isReady() && parent::isReadyToExecute();
	}
}