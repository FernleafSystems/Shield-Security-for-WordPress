<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class RequestRecords {

	use ModConsumer;

	public function loadReq( string $reqID, int $ipRefID, bool $autoCreate = true ) :Ops\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Ops\Select $select */
		$select = $mod->getDbH_ReqLogs()->getQuerySelector();
		/** @var Ops\Record|null $record */
		$record = $select->filterByReqID( $reqID )->first();

		if ( empty( $record ) && $autoCreate && $this->addReq( $reqID, $ipRefID ) ) {
			$record = $this->loadReq( $reqID, $ipRefID, false );
		}

		return $record;
	}

	public function addReq( string $reqID, int $ipRef ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_ReqLogs();
		/** @var Ops\Insert $insert */
		$insert = $dbh->getQueryInserter();
		$record = new Ops\Record();
		$record->req_id = $reqID;
		$record->ip_ref = $ipRef;
		return $insert->insert( $record );
	}
}