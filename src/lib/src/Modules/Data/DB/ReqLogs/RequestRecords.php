<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\Ops as ReqLogsDB;

class RequestRecords {

	use ModConsumer;

	public function loadReq( string $reqID, int $ipRefID, bool $autoCreate = true ) :ReqLogsDB\Record {
		/** @var ReqLogsDB\Select $select */
		$select = $this->con()->getModule_Data()->getDbH_ReqLogs()->getQuerySelector();
		/** @var ReqLogsDB\Record|null $record */
		$record = $select->filterByReqID( $reqID )->first();

		if ( empty( $record ) && $autoCreate && $this->addReq( $reqID, $ipRefID ) ) {
			$record = $this->loadReq( $reqID, $ipRefID, false );
		}

		return $record;
	}

	public function addReq( string $reqID, int $ipRef ) :bool {
		$dbh = $this->con()->getModule_Data()->getDbH_ReqLogs();
		/** @var ReqLogsDB\Insert $insert */
		$insert = $dbh->getQueryInserter();
		/** @var ReqLogsDB\Record $record */
		$record = $dbh->getRecord();
		$record->req_id = $reqID;
		$record->ip_ref = $ipRef;
		return $insert->insert( $record );
	}
}