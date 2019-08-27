<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_AuditTrail extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		/** @var AuditTrail\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();
		if ( $oOpts->isEnabledAuditing() ) {
			$this->getSubProAuditor()->run();
		}
		if ( $oOpts->isEnabledChangeTracking() ) {
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