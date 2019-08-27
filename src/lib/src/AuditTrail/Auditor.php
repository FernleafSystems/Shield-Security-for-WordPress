<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\EntryVO;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Trait Auditor
 * @package FernleafSystems\Wordpress\Plugin\Shield\AuditTrail
 * @deprecated
 */
trait Auditor {

	/**
	 * @param string $sContext
	 * @param string $sMsg
	 * @param int    $nCategory
	 * @param string $sEvent
	 * @param array  $aEventData
	 * @return EntryVO
	 * @deprecated 7.5
	 */
	public function createNewAudit( $sContext, $sMsg, $nCategory = 1, $sEvent = '', $aEventData = [] ) {
		return null;
	}
}