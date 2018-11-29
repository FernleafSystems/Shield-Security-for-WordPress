<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\AuditTrail;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Trait AuditorConsumer
 *
 * @package FernleafSystems\Wordpress\Plugin\Shield\AuditTrail
 */
trait AuditorConsumer {

	/**
	 * @var Auditor
	 */
	static private $oAuditor;

	/**
	 * @return Auditor
	 */
	public function getAuditor() {
		if ( !self::$oAuditor instanceof Auditor ) {
			self::$oAuditor = new Auditor();
		}
		return self::$oAuditor;
	}

	/**
	 * @param string $sContext
	 * @param string $sMsg
	 * @param int    $nCategory
	 * @param string $sEvent
	 * @param array  $aEventData
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\EntryVO
	 */
	public function createNewAudit( $sContext, $sMsg, $nCategory = 1, $sEvent = '', $aEventData = array() ) {
		$oEntry = $this->getAuditor()->newAudit();
		$oEntry->context = $sContext;
		$oEntry->message = $sMsg;
		$oEntry->category = $nCategory;
		$oEntry->event = $sEvent;
		$oEntry->setAuditData( $aEventData );
		if ( Services::WpGeneral()->getIsCron() ) {
			$oEntry->wp_username = 'WP Cron';
		}
		return $oEntry;
	}

	/**
	 * @param Auditor $oAuditor
	 * @return $this
	 */
	protected function setAuditor( $oAuditor ) {
		self::$oAuditor = $oAuditor;
		return $this;
	}
}