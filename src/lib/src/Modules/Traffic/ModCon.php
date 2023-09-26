<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	public const SLUG = 'traffic';

	/**
	 * @var Lib\RequestLogger
	 */
	private $requestLogger;

	public function onWpInit() {
		/** @var Options $opts */
		$opts = $this->opts();
		$opts->liveLoggingTimeRemaining();
	}

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

	public function preProcessOptions() {
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

		if ( $opts->isOpt( 'enable_live_log', 'Y' ) && !$opts->isTrafficLoggerEnabled() ) {
			$opts->setOpt( 'enable_live_log', 'N' )
				 ->setOpt( 'live_log_started_at', 0 );
		}
	}

	protected function isReadyToExecute() :bool {
		return self::con()->getModule_Data()->getDbH_ReqLogs()->isReady() && parent::isReadyToExecute();
	}
}