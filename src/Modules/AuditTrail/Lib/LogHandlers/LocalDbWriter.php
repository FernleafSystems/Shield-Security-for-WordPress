<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers;

use Monolog\Handler\AbstractProcessingHandler;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	ActivityLogs\Ops as LogsDB,
	ActivityLogsMeta\Ops as MetaDB,
	ReqLogs\Ops as ReqLogsDB
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\RequestRecords;
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

			/** @var MetaDB\Insert $metaInserter */
			$metaInserter = self::con()->db_con->activity_logs_meta->getQueryInserter();
			$metaInserter->insertManyForLog( $log->id, $metas );
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
		$wpdb = Services::WpDb()->loadWpdb();
		$existingLogID = (int)$wpdb->get_var( $wpdb->prepare(
			sprintf(
				"SELECT `log`.`id`
					FROM `%s` AS `log`
					INNER JOIN `%s` AS `req` ON `req`.`id`=`log`.`req_ref`
					WHERE `log`.`event_slug`=%%s
						AND `req`.`ip_ref`=%%d
						AND `log`.`created_at`>%%d
					ORDER BY `log`.`updated_at` DESC, `log`.`created_at` DESC
					LIMIT 1",
				$dbCon->activity_logs->getTable(),
				$dbCon->req_logs->getTable()
			),
			$this->log[ 'context' ][ 'event_slug' ],
			$ipRecordID,
			Services::Request()->carbon()->subDay()->timestamp
		) );

		if ( $existingLogID > 0 ) {
			$wpdb->query( $wpdb->prepare(
				sprintf( "UPDATE `%s` SET `meta_value`=CAST(`meta_value` AS UNSIGNED)+1
					WHERE `log_ref`=%%d
						AND `meta_key`=%%s
				", $dbCon->activity_logs_meta->getTable() ),
				$existingLogID,
				'audit_count'
			) );
			// this can fail under load, but doesn't actually matter:
			$dbCon->activity_logs->getQueryUpdater()->updateById( $existingLogID, [
				'updated_at' => Services::Request()->ts()
			] );
		}
		return $existingLogID > 0;
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

		$requestRecord = self::con()->comps->requests_log->createDependentLog()
						 ?? $this->loadCurrentRequestRecordFromUpgradeCompatibilityPath();
		if ( empty( $requestRecord ) ) {
			throw new \Exception( __( 'No dependent request record found or created.', 'wp-simple-firewall' ) );
		}

		$record->req_ref = $requestRecord->id;

		/** @var LogsDB\Insert $inserter */
		$inserter = $dbh->getQueryInserter();
		$log = $inserter->insertGetRecord( $record );
		if ( empty( $log ) ) {
			throw new \Exception( __( 'Failed to insert.', 'wp-simple-firewall' ) );
		}
		return $log;
	}

	private function loadCurrentRequestRecordFromUpgradeCompatibilityPath() :?ReqLogsDB\Record {
		try {
			return ( new RequestRecords() )->loadReq(
				$this->log[ 'extra' ][ 'meta_request' ][ 'rid' ],
				( new IPRecords() )
					->loadIP( $this->log[ 'extra' ][ 'meta_request' ][ 'ip' ] ?? '' )
					->id
			);
		}
		catch ( \Exception $e ) {
			return null;
		}
	}
}
