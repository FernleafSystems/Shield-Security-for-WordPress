<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers;

use AptowebDeps\Monolog\Handler\AbstractProcessingHandler;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	ActivityLogs\Ops as LogsDB,
	ActivityLogsMeta\Ops as MetaDB,
	ReqLogs\Ops as ReqLogsDB,
	ReqLogs\RequestRecords
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LocalDbWriter extends AbstractProcessingHandler {

	use PluginControllerConsumer;

	/**
	 * @var array
	 */
	private $log;

	protected function write( array $record ) :void {
		$this->log = $record;
		try {
			if ( $record[ 'context' ][ 'event_def' ][ 'audit_countable' ] && $this->updateRecentLogEntry() ) {
				return; // event is countable
			}

			$log = $this->createPrimaryLogRecord();

			// anything stored in the primary log record doesn't need stored in meta
			unset( $record[ 'extra' ][ 'meta_wp' ] );
			unset( $record[ 'extra' ][ 'meta_request' ] );

			$metas = \array_merge(
				$record[ 'context' ][ 'audit_params' ] ?? [],
				$record[ 'extra' ][ 'meta_user' ]
			);
			if ( $record[ 'context' ][ 'event_def' ][ 'audit_countable' ] ?? false ) {
				$metas[ 'audit_count' ] = 1;
			}

			$dbhMeta = self::con()->db_con->activity_logs_meta;
			/** @var MetaDB\Record $metaRecord */
			$metaRecord = $dbhMeta->getRecord();
			$metaRecord->log_ref = $log->id;
			foreach ( $metas as $metaKey => $metaValue ) {
				$metaRecord->meta_key = $metaKey;
				$metaRecord->meta_value = $metaValue;
				$dbhMeta->getQueryInserter()->insert( $metaRecord );
			}
			$this->triggerRequestLogger();
		}
		catch ( \Exception $e ) {
			error_log( 'DEBUG::EXCEPTION: '.$e->getMessage() );
		}
	}

	private function triggerRequestLogger() {
		add_filter( 'shield/is_log_traffic', '__return_true', \PHP_INT_MAX );
	}

	protected function updateRecentLogEntry() :bool {
		$dbCon = self::con()->db_con;

		$ipRecordID = ( new IPRecords() )
			->loadIP( $this->log[ 'extra' ][ 'meta_request' ][ 'ip' ] )
			->id;
		/** @var ReqLogsDB\Select $reqSelector */
		$reqSelector = $dbCon->req_logs->getQuerySelector();
		$reqIDs = \array_map(
			function ( $rawRecord ) {
				return $rawRecord->id;
			},
			(array)$reqSelector->filterByIP( $ipRecordID )
							   ->setColumnsToSelect( [ 'id' ] )
							   ->queryWithResult()
		);

		/** @var LogsDB\Select $select */
		$select = $dbCon->activity_logs->getQuerySelector();
		/** @var LogsDB\Record $existingLog */
		$existingLog = $select->filterByEvent( $this->log[ 'context' ][ 'event_slug' ] )
							  ->filterByRequestRefs( $reqIDs )
							  ->filterByCreatedAt( Services::Request()->carbon()->subDay()->timestamp, '>' )
							  ->setOrderBy( 'updated_at', 'DESC', true )
							  ->setOrderBy( 'created_at' )
							  ->first();

		if ( !empty( $existingLog ) ) {
			Services::WpDb()->doSql(
				sprintf( "UPDATE `%s` SET `meta_value` = `meta_value`+1
					WHERE `log_ref`=%s
						AND `meta_key`='audit_count'
				", $dbCon->activity_logs_meta->getTable(), $existingLog->id )
			);
			// this can fail under load, but doesn't actually matter:
			$dbCon->activity_logs->getQueryUpdater()->updateById( $existingLog->id, [
				'updated_at' => Services::Request()->ts()
			] );
		}
		return !empty( $existingLog );
	}

	/**
	 * @throws \Exception
	 */
	protected function createPrimaryLogRecord() :LogsDB\Record {
		$dbh = self::con()->db_con->activity_logs;
		/** @var LogsDB\Record $record */
		$record = $dbh->getRecord();
		$record->event_slug = $this->log[ 'context' ][ 'event_slug' ];
		$record->site_id = $this->log[ 'extra' ][ 'meta_wp' ][ 'site_id' ];

		// Create the underlying request log.
		self::con()->comps->requests_log->createDependentLog();

		$requestRecord = ( new RequestRecords() )->loadReq(
			$this->log[ 'extra' ][ 'meta_request' ][ 'rid' ],
			( new IPRecords() )
				->loadIP( $this->log[ 'extra' ][ 'meta_request' ][ 'ip' ] ?? '' )
				->id
		);
		if ( empty( $requestRecord ) ) {
			throw new \Exception( 'No dependent Request Record found/created' );
		}

		$record->req_ref = $requestRecord->id;

		$success = $dbh->getQueryInserter()->insert( $record );
		if ( !$success ) {
			throw new \Exception( 'Failed to insert' );
		}

		/** @var LogsDB\Record $log */
		$log = $dbh->getQuerySelector()->byId( Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
		if ( empty( $log ) ) {
			throw new \Exception( 'Could not load log record' );
		}
		return $log;
	}
}
