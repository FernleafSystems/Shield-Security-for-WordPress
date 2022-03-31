<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	/**
	 * @var Lib\RequestLogger
	 */
	private $requestLogger;

	public function getRequestLogger() :Lib\RequestLogger {
		if ( !isset( $this->requestLogger ) ) {
			$this->requestLogger = ( new Lib\RequestLogger() )->setMod( $this );
		}
		return $this->requestLogger;
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
	}

	protected function isReadyToExecute() :bool {
		$IP = Services::IP();
		return $IP->isValidIp_PublicRange( $IP->getRequestIp() )
			   && $this->getCon()->getModule_Data()->getDbH_ReqLogs()->isReady()
			   && parent::isReadyToExecute();
	}
}