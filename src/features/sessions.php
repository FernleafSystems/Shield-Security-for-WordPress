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
	 * @return Shield\Databases\Session\Handler
	 */
	protected function loadDbHandler() {
		return new Shield\Databases\Session\Handler();
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'Sessions';
	}
}