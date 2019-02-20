<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_AuditTrail extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isEnabledAuditing() ) {
			$this->getSubProAuditor()->run();
		}
	}

	/**
	 * @return ICWP_WPSF_Processor_AuditTrail_Auditor|mixed
	 */
	public function getSubProAuditor() {
		return $this->getSubPro( 'auditor' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'auditor' => 'ICWP_WPSF_Processor_AuditTrail_Auditor',
		];
	}
}