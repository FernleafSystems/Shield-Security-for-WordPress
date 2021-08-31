<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Ops\Commit;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\EventsListener;

/**
 * @deprecated 12.0
 */
class AuditWriter extends EventsListener {

	use HandlerConsumer;

	/**
	 * @var AuditTrail\EntryVO[]
	 */
	private $aAuditLogs;

	/**
	 * @param string $evt
	 * @param array  $meta
	 * @param array  $def
	 */
	protected function captureEvent( string $evt, $meta = [], $def = [] ) {
		$con = $this->getCon();

		$meta = apply_filters( 'shield/audit_event_meta', $meta, $evt );

		if ( $def[ 'audit' ] && empty( $meta[ 'suppress_audit' ] ) ) { // only audit if it's an auditable event
			$entry = new AuditTrail\EntryVO();
			$entry->rid = $con->getShortRequestId();
			$entry->event = $evt;
			$entry->category = $def[ 'cat' ];
			$entry->context = $def[ 'context' ];
			$entry->meta = $meta[ 'audit' ] ?? [];

			$logs = $this->getLogs();

			// cater for where certain events may happen more than once in the same request
			if ( !empty( $def[ 'audit_multiple' ] ) ) {
				$logs[] = $entry;
			}
			else {
				$logs[ $evt ] = $entry;
			}

			$this->setLogs( $logs );
		}
	}

	protected function onShutdown() {
		if ( !$this->getCon()->plugin_deleting ) {
			( new Commit() )
				->setDbHandler( $this->getDbHandler() )
				->commitAudits( $this->getLogs() );
			$this->setLogs();
		}
	}

	/**
	 * @return AuditTrail\EntryVO[]
	 */
	public function getLogs() :array {
		return is_array( $this->aAuditLogs ) ? $this->aAuditLogs : [];
	}

	/**
	 * @param AuditTrail\EntryVO[] $logs
	 * @return $this
	 */
	public function setLogs( array $logs = [] ) {
		$this->aAuditLogs = $logs;
		return $this;
	}
}