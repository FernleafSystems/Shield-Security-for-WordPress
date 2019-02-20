<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_AuditTrail extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isEnabledAuditing() ) {
			$this->getSubProAuditor()->run();
		}
		if ( $oFO->isEnabledChangeTracking() ) {
			$this->getSubProChangeTracking()->run();
		}
	}

	/**
	 * @return ICWP_WPSF_Processor_AuditTrail_Auditor|mixed
	 */
	public function getSubProAuditor() {
		return $this->getSubPro( 'auditor' );
	}

	/**
	 * @return ICWP_WPSF_Processor_AuditTrail_ChangeTracking|mixed
	 */
	public function getSubProChangeTracking() {
		return $this->getSubPro( 'changetracking' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'auditor'        => 'ICWP_WPSF_Processor_AuditTrail_Auditor',
			'changetracking' => 'ICWP_WPSF_Processor_AuditTrail_ChangeTracking',
		];
	}
}