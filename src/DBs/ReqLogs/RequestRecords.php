<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops as ReqLogsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RequestRecords {

	use PluginControllerConsumer;

	private array $lastFailure = [];

	public function loadReq( string $reqID, int $ipRefID, bool $autoCreate = true ) :?ReqLogsDB\Record {
		if ( $autoCreate ) {
			$this->clearLastFailure();
		}

		/** @var ReqLogsDB\Select $select */
		$select = self::con()->db_con->req_logs->getQuerySelector();
		/** @var ReqLogsDB\Record|null $record */
		$record = $select->filterByReqID( $reqID )->first();

		if ( empty( $record ) && $autoCreate ) {
			$record = $this->createReq( $reqID, $ipRefID );
			if ( empty( $record ) ) {
				$record = $this->loadReq( $reqID, $ipRefID, false );
			}
		}

		if ( !$record instanceof ReqLogsDB\Record ) {
			if ( empty( $this->lastFailure ) ) {
				$this->lastFailure = [
					'stage'    => 'request_record_load',
					'message'  => 'Failed to load request log record',
					'db_error' => $this->currentDbError(),
					'req_id'   => $reqID,
					'ip_ref'   => $ipRefID,
				];
			}
			$record = null;
		}
		else {
			$this->clearLastFailure();
		}

		return $record;
	}

	public function getLastFailure() :array {
		return $this->lastFailure;
	}

	public function clearLastFailure() :void {
		$this->lastFailure = [];
	}

	public function addReq( string $reqID, int $ipRef ) :bool {
		return $this->createReq( $reqID, $ipRef ) instanceof ReqLogsDB\Record;
	}

	public function createReq( string $reqID, int $ipRef ) :?ReqLogsDB\Record {
		$dbh = self::con()->db_con->req_logs;
		/** @var ReqLogsDB\Insert $insert */
		$insert = $dbh->getQueryInserter();
		/** @var ReqLogsDB\Record $record */
		$record = $dbh->getRecord();
		$record->req_id = $reqID;
		$record->ip_ref = $ipRef;
		$created = $insert->insertGetRecord( $record );
		if ( !$created instanceof ReqLogsDB\Record ) {
			$this->lastFailure = [
				'stage'    => 'request_record_insert',
				'message'  => 'Failed to insert request log record',
				'db_error' => $this->currentDbError(),
				'req_id'   => $reqID,
				'ip_ref'   => $ipRef,
			];
		}
		else {
			$this->clearLastFailure();
		}
		return $created;
	}

	private function currentDbError() :string {
		$wpdb = Services::WpDb()->loadWpdb();
		return (string)$wpdb->last_error;
	}
}
