<?php

class ICWP_WPSF_AuditTrail_Auditor_Base extends \FernleafSystems\Wordpress\Plugin\Shield\Deprecated\Foundation {

	use \FernleafSystems\Wordpress\Plugin\Shield\AuditTrail\Auditor;

	/**
	 * @param string $sContext
	 * @param string $sEvent
	 * @param int    $nCategory
	 * @param string $sMessage
	 * @param array  $aData
	 * @deprecated
	 */
	public function add( $sContext, $sEvent, $nCategory, $sMessage = '', $aData = [] ) {
		$this->createNewAudit( $sContext, $sMessage, $nCategory, $sEvent, $aData );
	}
}