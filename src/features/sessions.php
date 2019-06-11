<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Sessions extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * Override this and adapt per feature
	 * @return ICWP_WPSF_Processor_Sessions
	 * @deprecated 7.5
	 */
	protected function loadProcessor() {
		$oP = parent::loadProcessor();
		self::$oSessProcessor = $oP;
		return $oP;
	}

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
	 * @return Shield\Modules\Sessions\Options
	 */
	protected function loadOptions() {
		return new Shield\Modules\Sessions\Options();
	}

	/**
	 * @return Shield\Modules\Sessions\Strings
	 */
	protected function loadStrings() {
		return new Shield\Modules\Sessions\Strings();
	}
}