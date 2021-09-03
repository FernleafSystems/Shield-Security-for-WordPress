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

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$aExcls = $opts->getCustomExclusions();
		foreach ( $aExcls as &$sExcl ) {
			$sExcl = trim( esc_js( $sExcl ) );
		}
		$opts->setOpt( 'custom_exclusions', array_filter( $aExcls ) );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		$IP = Services::IP();
		return $IP->isValidIp_PublicRange( $IP->getRequestIp() )
			   && $this->getCon()->getModule_Data()->getDbH_ReqLogs()->isReady()
			   && parent::isReadyToExecute();
	}

	/**
	 * @deprecated 12.0
	 */
	protected function cleanupDatabases() {
	}

	/**
	 * @deprecated 12.0
	 */
	public function getDbHandler_Traffic() :Databases\Traffic\Handler {
		return $this->getDbH( 'traffic' );
	}
}