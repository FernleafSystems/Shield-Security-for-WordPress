<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Meta;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\DB\ReqLogs\RequestRecords;
use FernleafSystems\Wordpress\Services\Services;
use Monolog\Handler\AbstractProcessingHandler;

class LocalDbWriter extends AbstractProcessingHandler {

	use ModConsumer;

	/**
	 * @inheritDoc
	 */
	protected function write( array $record ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		try {
			$log = $this->createPrimaryLogRecord( $record );

			// anything stored in the primary log record doesn't need stored in meta
			unset( $record[ 'extra' ][ 'meta_wp' ][ 'site_id' ] );
			unset( $record[ 'extra' ][ 'meta_request' ][ 'ip' ] );
			unset( $record[ 'extra' ][ 'meta_request' ][ 'rid' ] );

			$metas = array_merge(
				$record[ 'context' ][ 'audit_params' ] ?? [],
				$record[ 'extra' ][ 'meta_request' ],
				$record[ 'extra' ][ 'meta_user' ]
			);
			$metaRecord = new Meta\Ops\Record();
			$metaRecord->log_ref = $log->id;
			foreach ( $metas as $metaKey => $metaValue ) {
				$metaRecord->meta_key = $metaKey;
				$metaRecord->meta_value = $metaValue;
				$mod->getDbH_Meta()
					->getQueryInserter()
					->insert( $metaRecord );
			}
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @param array $logData
	 * @return Logs\Ops\Record
	 * @throws \Exception
	 */
	protected function createPrimaryLogRecord( array $logData ) :Logs\Ops\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$record = new Logs\Ops\Record();
		$record->event_slug = $logData[ 'context' ][ 'event_slug' ];
		$record->site_id = $logData[ 'extra' ][ 'meta_wp' ][ 'site_id' ];

		$ipRecordID = ( new IPRecords() )
			->setMod( $this->getCon()->getModule_Plugin() )
			->loadIP( $logData[ 'extra' ][ 'meta_request' ][ 'ip' ] )
			->id;
		$record->req_ref = ( new RequestRecords() )
			->setMod( $this->getCon()->getModule_Traffic() )
			->loadReq( $logData[ 'extra' ][ 'meta_request' ][ 'rid' ], $ipRecordID )
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
