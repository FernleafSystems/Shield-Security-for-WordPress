<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

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
		return isset( self::con()->comps ) ? self::con()->comps->requests_log :
			( $this->requestLogger ?? $this->requestLogger = new Lib\RequestLogger() );
	}
}