<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\DB\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\ModCon;

class RequestRecords {

	use ModConsumer;

	private static $ReqCache = [];

	public function loadReq( string $reqID, int $ipRef, bool $autoCreate = true ) :Ops\Record {

		if ( empty( self::$ReqCache[ $reqID ] ) ) {

			/** @var ModCon $mod */
			$mod = $this->getMod();
			$dbh = $mod->getDbH_ReqLogs();
			/** @var Ops\Select $select */
			$select = $dbh->getQuerySelector();
			/** @var Ops\Record|null $record */
			$record = $select->filterByReqID( $reqID )->first();

			if ( empty( $record ) && $autoCreate && $this->addReq( $reqID, $ipRef ) ) {
				$record = $this->loadReq( $reqID, $ipRef, false );
			}

			self::$ReqCache[ $reqID ] = $record;
		}

		return self::$ReqCache[ $reqID ];
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