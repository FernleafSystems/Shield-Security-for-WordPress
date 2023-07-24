<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs\Ops as LogsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Meta\Ops as MetaDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;
use FernleafSystems\Wordpress\Services\Services;
use Monolog\Handler\AbstractProcessingHandler;

class LocalDbWriter extends AbstractProcessingHandler {

	use ModConsumer;

	/**
	 * @var array
	 */
	private $log;

	protected function write( array $record ) :void {
		$dbhMeta = $this->mod()->getDbH_Meta();

		$this->log = $record;

		try {
			if ( $record[ 'context' ][ 'event_def' ][ 'audit_countable' ] && $this->updateRecentLogEntry() ) {
				return; // event is countable
			}

			$log = $this->createPrimaryLogRecord();

			// anything stored in the primary log record doesn't need stored in meta
			unset( $record[ 'extra' ][ 'meta_wp' ] );
			unset( $record[ 'extra' ][ 'meta_wp' ] );
			unset( $record[ 'extra' ][ 'meta_request' ] );

			$metas = \array_merge(
				$record[ 'context' ][ 'audit_params' ] ?? [],
				$record[ 'extra' ][ 'meta_user' ]
			);
			if ( $record[ 'context' ][ 'event_def' ][ 'audit_countable' ] ?? false ) {
				$metas[ 'audit_count' ] = 1;
			}

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
		}
	}

	private function triggerRequestLogger() {
		add_filter( 'shield/is_log_traffic', '__return_true', PHP_INT_MAX );
	}

	protected function updateRecentLogEntry() :bool {

		$ipRecordID = ( new IPRecords() )
			->loadIP( $this->log[ 'extra' ][ 'meta_request' ][ 'ip' ] )
			->id;
		/** @var ReqLogs\Ops\Select $reqSelector */
		$reqSelector = $this->con()
							->getModule_Data()
							->getDbH_ReqLogs()
							->getQuerySelector();
		$reqIDs = \array_map(
			function ( $rawRecord ) {
				return $rawRecord->id;
			},
			(array)$reqSelector->filterByIP( $ipRecordID )
							   ->setColumnsToSelect( [ 'id' ] )
							   ->queryWithResult()
		);

		/** @var LogsDB\Select $select */
		$select = $this->mod()->getDbH_Logs()->getQuerySelector();
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
				", $this->mod()->getDbH_Meta()->getTableSchema()->table, $existingLog->id )
			);
			// this can fail under load, but doesn't actually matter:
			$this->mod()
				 ->getDbH_Logs()
				 ->getQueryUpdater()
				 ->updateById( $existingLog->id, [ 'updated_at' => Services::Request()->ts() ] );
		}
		return !empty( $existingLog );
	}

	/**
	 * @throws \Exception
	 */
	protected function createPrimaryLogRecord() :LogsDB\Record {
		$dbh = $this->mod()->getDbH_Logs();
		/** @var LogsDB\Record $record */
		$record = $dbh->getRecord();
		$record->event_slug = $this->log[ 'context' ][ 'event_slug' ];
		$record->site_id = $this->log[ 'extra' ][ 'meta_wp' ][ 'site_id' ];

		$ipRecordID = ( new IPRecords() )
			->loadIP( $this->log[ 'extra' ][ 'meta_request' ][ 'ip' ] ?? '' )
			->id;
		$record->req_ref = ( new ReqLogs\RequestRecords() )
			->loadReq( $this->log[ 'extra' ][ 'meta_request' ][ 'rid' ], $ipRecordID )
			->id;

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
