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
	}

	protected function isReadyToExecute() :bool {
		return self::con()->getModule_Data()->getDbH_ReqLogs()->isReady() && parent::isReadyToExecute();
	}
}