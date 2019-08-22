<?php

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ICWP_WPSF_Processor_AuditTrail_Wordpress
 * @deprecated
 */
class ICWP_WPSF_Processor_AuditTrail_Wordpress extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
	}

	/**
	 * @param string $sNewCoreVersion
	 * @deprecated 8
	 */
	public function auditCoreUpdated( $sNewCoreVersion ) {
	}

	/**
	 * @param string $sOld
	 * @param string $sNew
	 * @deprecated 8
	 */
	public function auditPermalinkStructure( $sOld, $sNew ) {
	}
}