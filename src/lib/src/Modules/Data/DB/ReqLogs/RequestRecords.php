<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModConsumer;

class RequestRecords {

	use ModConsumer;

	public function loadReq( string $reqID, int $ipRefID, bool $autoCreate = true ) :Ops\Record {
		/** @var Ops\Select $select */
		$select = $this->con()->getModule_Data()->getDbH_ReqLogs()->getQuerySelector();
		/** @var Ops\Record|null $record */
		$record = $select->filterByReqID( $reqID )->first();

		if ( empty( $record ) && $autoCreate && $this->addReq( $reqID, $ipRefID ) ) {
			$record = $this->loadReq( $reqID, $ipRefID, false );
		}

		return $record;
	}

	public function addReq( string $reqID, int $ipRef ) :bool {
		$dbh = $this->con()->getModule_Data()->getDbH_ReqLogs();
		/** @var Ops\Insert $insert */
		$insert = $dbh->getQueryInserter();
		$record = new Ops\Record();
		$record->req_id = $reqID;
		$record->ip_ref = $ipRef;
		return $insert->insert( $record );
	}
}