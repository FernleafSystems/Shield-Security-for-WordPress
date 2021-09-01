<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\LogHandlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\DB\ReqLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\DB\ReqMeta;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\ModCon;
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
		$WP = Services::WpGeneral();

		try {
			$reqLog = $this->createPrimaryLogRecord( $record );

			// anything stored in the primary log record doesn't need stored in meta
			if ( !$WP->isMultisite() ) {
				unset( $record[ 'extra' ][ 'meta_wp' ][ 'site_id' ] );
			}
			unset( $record[ 'extra' ][ 'meta_request' ][ 'ip' ] );
			unset( $record[ 'extra' ][ 'meta_request' ][ 'rid' ] );

			$metas = array_merge(
				$record[ 'extra' ][ 'meta_shield' ],
				$record[ 'extra' ][ 'meta_request' ],
				$record[ 'extra' ][ 'meta_user' ]
			);
			$metaRecord = new ReqMeta\Ops\Record();
			$metaRecord->log_ref = $reqLog->id;
			foreach ( $metas as $metaKey => $metaValue ) {
				$metaRecord->meta_key = $metaKey;
				$metaRecord->meta_value = $metaValue;
				$mod->getDbH_ReqMeta()
					->getQueryInserter()
					->insert( $metaRecord );
			}
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @param array $logData
	 * @return ReqLogs\Ops\Record
	 * @throws \Exception
	 */
	protected function createPrimaryLogRecord( array $logData ) :ReqLogs\Ops\Record {
		$ipRecordID = ( new IPRecords() )
			->setMod( $this->getCon()->getModule_Plugin() )
			->loadIP( $logData[ 'extra' ][ 'meta_request' ][ 'ip' ] )
			->id;
		$record = ( new RequestRecords() )
			->setMod( $this->getCon()->getModule_Traffic() )
			->loadReq( $logData[ 'extra' ][ 'meta_request' ][ 'rid' ], $ipRecordID );

		if ( empty( $record ) ) {
			throw new \Exception( 'Failed to insert' );
		}
		return $record;
	}
}
