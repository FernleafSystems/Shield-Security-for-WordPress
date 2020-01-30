<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Ops\Commit;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\EventsListener;

class AuditWriter extends EventsListener {

	use HandlerConsumer;

	/**
	 * @var AuditTrail\EntryVO[]
	 */
	private $aAuditLogs;

	/**
	 * @param string $sEvent
	 * @param array  $aMeta
	 */
	protected function captureEvent( $sEvent, $aMeta = [] ) {
		$oCon = $this->getCon();
		$aDef = $oCon->loadEventsService()->getEventDef( $sEvent );
		if ( $aDef[ 'audit' ] && empty( $aMeta[ 'suppress_audit' ] ) ) { // only audit if it's an auditable event
			$oEntry = new AuditTrail\EntryVO();
			$oEntry->rid = $this->getCon()->getShortRequestId();
			$oEntry->event = $sEvent;
			$oEntry->category = $aDef[ 'cat' ];
			$oEntry->context = $aDef[ 'context' ];
			$oEntry->meta = isset( $aMeta[ 'audit' ] ) ? $aMeta[ 'audit' ] : [];

			$aLogs = $this->getLogs();

			// cater for where certain events may happen more than once in the same request
			if ( !empty( $aDef[ 'audit_multiple' ] ) ) {
				$aLogs[] = $oEntry;
			}
			else {
				$aLogs[ $sEvent ] = $oEntry;
			}

			$this->setLogs( $aLogs );
		}
	}

	protected function onShutdown() {
		if ( !$this->getCon()->isPluginDeleting() ) {
			( new Commit() )
				->setDbHandler( $this->getDbHandler() )
				->commitAudits( $this->getLogs() );
			$this->setLogs();
		}
	}

	/**
	 * @return AuditTrail\EntryVO[]
	 */
	public function getLogs() {
		return is_array( $this->aAuditLogs ) ? $this->aAuditLogs : [];
	}

	/**
	 * @param AuditTrail\EntryVO[] $aLogs
	 * @return $this
	 */
	public function setLogs( $aLogs = [] ) {
		$this->aAuditLogs = $aLogs;
		return $this;
	}
}