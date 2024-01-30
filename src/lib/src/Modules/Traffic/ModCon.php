<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ModCon {

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

	protected function isReadyToExecute() :bool {
		return self::con()->getModule_Data()->getDbH_ReqLogs()->isReady() && parent::isReadyToExecute();
	}
}