<?php

/**
 * Class ICWP_WPSF_AuditTrail_Auditor_Base
 * @deprecated 8
 */
class ICWP_WPSF_AuditTrail_Auditor_Base extends \FernleafSystems\Wordpress\Plugin\Shield\Deprecated\Foundation {

	/**
	 * @param string $sContext
	 * @param string $sEvent
	 * @param int    $nCategory
	 * @param string $sMessage
	 * @param array  $aData
	 * @deprecated
	 */
	public function add( $sContext, $sEvent, $nCategory, $sMessage = '', $aData = [] ) {
	}
}