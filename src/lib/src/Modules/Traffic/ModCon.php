<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

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
		$opts = $this->getOptions();
		return [
			$opts->isTrafficLimitEnabled() ? Rules\Build\IsRateLimitExceeded::class : null,
		];
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$opts->setOpt( 'custom_exclusions', array_filter( array_map(
			function ( $excl ) {
				return trim( esc_js( $excl ) );
			},
			$opts->getCustomExclusions()
		) ) );

		if ( !$this->getCon()->isPremiumActive() && $opts->isOpt( 'enable_limiter', 'Y' ) ) {
			$opts->isOpt( 'enable_limiter', 'N' );
		}

		if ( $opts->isOpt( 'enable_limiter', 'Y' ) && !$opts->isTrafficLoggerEnabled() ) {
			$opts->setOpt( 'enable_logger', 'Y' );
			if ( $opts->getAutoCleanDays() === 0 ) {
				$opts->resetOptToDefault( 'auto_clean' );
			}
		}
	}

	protected function isReadyToExecute() :bool {
		$con = $this->getCon();
		return $con->getModule_Data()->getDbH_ReqLogs()->isReady()
			   && parent::isReadyToExecute();
	}
}