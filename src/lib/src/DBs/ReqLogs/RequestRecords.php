<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops as ReqLogsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class RequestRecords {

	use PluginControllerConsumer;

	public function loadReq( string $reqID, int $ipRefID, bool $autoCreate = true ) :?ReqLogsDB\Record {
		/** @var ReqLogsDB\Select $select */
		$select = self::con()->db_con->req_logs->getQuerySelector();
		/** @var ReqLogsDB\Record|null $record */
		$record = $select->filterByReqID( $reqID )->first();

		if ( empty( $record ) && $autoCreate && $this->addReq( $reqID, $ipRefID ) ) {
			$record = $this->loadReq( $reqID, $ipRefID, false );
		}

		if ( !$record instanceof ReqLogsDB\Record ) {
			$record = null;
		}

		return $record;
	}

	public function addReq( string $reqID, int $ipRef ) :bool {
		$dbh = self::con()->db_con->req_logs;
		/** @var ReqLogsDB\Insert $insert */
		$insert = $dbh->getQueryInserter();
		/** @var ReqLogsDB\Record $record */
		$record = $dbh->getRecord();
		$record->req_id = $reqID;
		$record->ip_ref = $ipRef;
		return $insert->insert( $record );
	}
}