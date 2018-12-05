<?php

class ICWP_WPSF_AuditTrail_Auditor_Base extends ICWP_WPSF_Foundation {

	use \FernleafSystems\Wordpress\Plugin\Shield\AuditTrail\Auditor;

	/**
	 * @param string $sContext
	 * @param string $sEvent
	 * @param int    $nCategory
	 * @param string $sMessage
	 * @param array  $aData
	 */
	public function add( $sContext, $sEvent, $nCategory, $sMessage = '', $aData = array() ) {
		$this->createNewAudit( $sContext, $sMessage, $nCategory, $sEvent, $aData );
	}
}