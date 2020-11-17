<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 10.1
 */
class ICWP_WPSF_FeatureHandler_Sessions extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return bool
	 */
	public function isAutoAddSessions() {
		$opts = $this->getOptions();
		$oReq = Services::Request();
		$nStartedAt = $opts->getOpt( 'autoadd_sessions_started_at', 0 );
		if ( $nStartedAt < 1 ) {
			$nStartedAt = $oReq->ts();
			$opts->setOpt( 'autoadd_sessions_started_at', $nStartedAt );
		}
		return ( $oReq->ts() - $nStartedAt ) < 20;
	}

	/**
	 * @return false|Shield\Databases\Session\Handler
	 */
	public function getDbHandler_Sessions() {
		return $this->getDbH( 'session' );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() {
		return ( $this->getDbHandler_Sessions() instanceof Shield\Databases\Session\Handler )
			   && $this->getDbHandler_Sessions()->isReady()
			   && parent::isReadyToExecute();
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() :string {
		return 'Sessions';
	}
}