<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\EntryVO;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Trait Auditor
 * @package FernleafSystems\Wordpress\Plugin\Shield\AuditTrail
 */
trait Auditor {

	/**
	 * @param string $sContext
	 * @param string $sMsg
	 * @param int    $nCategory
	 * @param string $sEvent
	 * @param array  $aEventData
	 * @return EntryVO
	 */
	public function createNewAudit( $sContext, $sMsg, $nCategory = 1, $sEvent = '', $aEventData = array() ) {
		$oEntry = new EntryVO();
		$oEntry->context = $sContext;
		$oEntry->message = $sMsg;
		$oEntry->category = $nCategory;
		$oEntry->event = $sEvent;
		$oEntry->meta = $aEventData;
		if ( Services::WpGeneral()->getIsCron() ) {
			$oEntry->wp_username = 'WP Cron';
		}
		do_action( 'icwp-wpsf-add_new_audit_entry', $oEntry );
		return $oEntry;
	}
}