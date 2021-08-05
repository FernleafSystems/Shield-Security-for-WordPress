<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Meta;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
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
			$log = $this->createPrimaryLogRecord( $record[ 'context' ] );
			$metas = array_merge(
				$logData[ 'audit' ] ?? [],
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
		$record->site_id = \get_current_blog_id();
		$record->event_slug = $logData[ 'event_slug' ];

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
