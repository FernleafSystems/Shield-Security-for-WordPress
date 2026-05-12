<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\LogHandlers;

use Monolog\Handler\AbstractProcessingHandler;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops as ReqLogsDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\RequestRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\{
	RequestLogger,
	RequestLogRetentionPolicy
};

/**
 * Request logs are tiered during write so pruning can retain high-signal requests longer without user configuration.
 */
class LocalDbWriter extends AbstractProcessingHandler {

	use PluginControllerConsumer;

	private ?RequestLogger $requestLogger = null;

	public function setRequestLogger( RequestLogger $requestLogger ) :self {
		$this->requestLogger = $requestLogger;
		return $this;
	}

	protected function write( array $record ) :void {
		try {
			$reqRecord = $this->createPrimaryLogRecord( $record );
			if ( $this->requestLogger instanceof RequestLogger ) {
				$this->requestLogger->setLastLoggedRecord( $reqRecord );
			}
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function createPrimaryLogRecord( array $logData ) :ReqLogsDB\Record {
		$requestMeta = $logData[ 'extra' ][ 'meta_request' ];
		$ipRecord = ( new IPRecords() )->loadIP( $requestMeta[ 'ip' ] );
		$reqID = $requestMeta[ 'rid' ];

		// A record will only exist if the Activity Log created it, or it's not excluded.
		// anything stored in the primary log record doesn't need stored in meta
		unset( $logData[ 'extra' ][ 'meta_request' ][ 'ip' ] );
		unset( $logData[ 'extra' ][ 'meta_request' ][ 'rid' ] );

		$meta = \array_merge(
			$logData[ 'extra' ][ 'meta_shield' ],
			$logData[ 'extra' ][ 'meta_request' ],
			$logData[ 'extra' ][ 'meta_user' ],
			$logData[ 'extra' ][ 'meta_wp' ]
		);

		$recordData = \array_intersect_key( $meta, \array_flip( [ 'verb', 'code', 'path', 'type', 'uid', 'offense' ] ) );
		$recordData[ 'req_id' ] = $reqID;
		$recordData[ 'ip_ref' ] = $ipRecord->id;
		$recordData[ 'meta' ] = \base64_encode( \wp_json_encode( \array_diff_key( $meta, $recordData ) ) );
		$recordData[ 'transient' ] = ( new RequestLogRetentionPolicy() )->shouldMarkAsTransient( [
			'has_params' => !empty( $meta[ 'has_params' ] ),
			'offense'    => !empty( $meta[ 'offense' ] ),
		] );

		if ( !$this->isDependentLog() ) {
			$inserted = $this->insertCompleteRequestRecord( $recordData );
			if ( $inserted instanceof ReqLogsDB\Record ) {
				return $inserted;
			}
		}

		$reqRecord = ( new RequestRecords() )->loadReq( $reqID, $ipRecord->id );
		if ( !$reqRecord instanceof ReqLogsDB\Record ) {
			throw new \Exception( 'Failed to load/create Request Record' );
		}

		$this->updateExistingRequestRecord( $reqRecord, $recordData );
		return $reqRecord;
	}

	private function insertCompleteRequestRecord( array $recordData ) :?ReqLogsDB\Record {
		$dbh = self::con()->db_con->req_logs;
		/** @var ReqLogsDB\Record $record */
		$record = $dbh->getRecord()->applyFromArray( $recordData );
		/** @var ReqLogsDB\Insert $insert */
		$insert = $dbh->getQueryInserter();
		return $insert->setIgnore()->insertGetRecord( $record );
	}

	/**
	 * @throws \Exception
	 */
	private function updateExistingRequestRecord( ReqLogsDB\Record $record, array $recordData ) :void {
		$update = \array_diff_key( $recordData, \array_flip( [ 'req_id', 'ip_ref' ] ) );
		if ( !self::con()->db_con->req_logs->getQueryUpdater()->updateById( $record->id, $update ) ) {
			throw new \Exception( 'Failed to update request log' );
		}
		$record->applyFromArray( \array_merge( $record->getRawData(), $update ) );
	}

	private function isDependentLog() :bool {
		return $this->requestLogger instanceof RequestLogger && $this->requestLogger->isDependentLog();
	}
}
