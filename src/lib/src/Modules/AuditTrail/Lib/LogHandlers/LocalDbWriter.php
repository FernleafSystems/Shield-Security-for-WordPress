<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Meta;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Services\Services;
use Monolog\Handler\AbstractProcessingHandler;

class LocalDbWriter extends AbstractProcessingHandler {

	use ModConsumer;

	/**
	 * @var array
	 */
	private $log;

	/**
	 * @inheritDoc
	 */
	protected function write( array $record ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

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

			$metas = array_merge(
				$record[ 'context' ][ 'audit_params' ] ?? [],
				$record[ 'extra' ][ 'meta_user' ]
			);
			if ( $record[ 'context' ][ 'event_def' ][ 'audit_countable' ] ?? false ) {
				$metas[ 'audit_count' ] = 1;
			}

			$metaRecord = new Meta\Ops\Record();
			$metaRecord->log_ref = $log->id;
			foreach ( $metas as $metaKey => $metaValue ) {
				$metaRecord->meta_key = $metaKey;
				$metaRecord->meta_value = $metaValue;
				$mod->getDbH_Meta()
					->getQueryInserter()
					->insert( $metaRecord );
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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$modData = $this->getCon()->getModule_Data();

		$ipRecordID = ( new IPRecords() )
			->setMod( $modData )
			->loadIP( $this->log[ 'extra' ][ 'meta_request' ][ 'ip' ] )
			->id;
		/** @var ReqLogs\Ops\Select $reqSelector */
		$reqSelector = $modData->getDbH_ReqLogs()->getQuerySelector();
		$reqIDs = array_map(
			function ( $rawRecord ) {
				return $rawRecord->id;
			},
			(array)$reqSelector->filterByIP( $ipRecordID )
							   ->setColumnsToSelect( [ 'id' ] )
							   ->queryWithResult()
		);

		/** @var Logs\Ops\Select $select */
		$select = $mod->getDbH_Logs()->getQuerySelector();
		/** @var Logs\Ops\Record $existingLog */
		$existingLog = $select->filterByEvent( $this->log[ 'context' ][ 'event_slug' ] )
							  ->filterByRequestRefs( $reqIDs )
							  ->filterByCreatedAt( Services::Request()->carbon()->subDay()->timestamp, '>' )
							  ->setOrderBy( 'updated_at', 'DESC', true )
							  ->setOrderBy( 'created_at', 'DESC' )
							  ->first();

		if ( !empty( $existingLog ) ) {
			Services::WpDb()->doSql(
				sprintf( "UPDATE `%s` SET `meta_value` = `meta_value`+1
					WHERE `log_ref`=%s
						AND `meta_key`='audit_count'
				", $mod->getDbH_Meta()->getTableSchema()->table, $existingLog->id )
			);
			// this can fail under load, but doesn't actually matter:
			$mod->getDbH_Logs()
				->getQueryUpdater()
				->updateById( $existingLog->id, [ 'updated_at' => Services::Request()->ts() ] );
		}
		return !empty( $existingLog );
	}

	/**
	 * @throws \Exception
	 */
	protected function createPrimaryLogRecord() :Logs\Ops\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$record = new Logs\Ops\Record();
		$record->event_slug = $this->log[ 'context' ][ 'event_slug' ];
		$record->site_id = $this->log[ 'extra' ][ 'meta_wp' ][ 'site_id' ];

		$ipRecordID = ( new IPRecords() )
			->setMod( $this->getCon()->getModule_Data() )
			->loadIP( $this->log[ 'extra' ][ 'meta_request' ][ 'ip' ] ?? '' )
			->id;
		$record->req_ref = ( new ReqLogs\RequestRecords() )
			->setMod( $this->getCon()->getModule_Data() )
			->loadReq( $this->log[ 'extra' ][ 'meta_request' ][ 'rid' ], $ipRecordID )
			->id;

		$success = $mod->getDbH_Logs()
					   ->getQueryInserter()
					   ->insert( $record );
		if ( !$success ) {
			throw new \Exception( 'Failed to insert' );
		}

		/** @var Logs\Ops\Record $log */
		$log = $mod->getDbH_Logs()
				   ->getQuerySelector()
				   ->byId( Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
		if ( empty( $log ) ) {
			throw new \Exception( 'Could not load log record' );
		}
		return $log;
	}
}
