<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Sessions extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return bool
	 */
	public function isAutoAddSessions() {
		$oReq = Services::Request();
		$nStartedAt = $this->getOpt( 'autoadd_sessions_started_at', 0 );
		if ( $nStartedAt < 1 ) {
			$nStartedAt = $oReq->ts();
			$this->setOpt( 'autoadd_sessions_started_at', $nStartedAt );
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
	protected function getNamespaceBase() {
		return 'Sessions';
	}

	/**
	 * @return Shield\Databases\Session\Handler
	 * @deprecated 8.4
	 */
	protected function loadDbHandler() {
		return new Shield\Databases\Session\Handler();
	}
}