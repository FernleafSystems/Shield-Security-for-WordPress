<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_AuditTrail extends Modules\BaseShield\ShieldProcessor {

	public function run() {
		/** @var AuditTrail\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isEnabledAuditing() ) {
			$this->getSubProAuditor()->execute();
		}
		if ( false && $oOpts->isEnabledChangeTracking() ) {
			$this->getSubProChangeTracking()->execute();
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