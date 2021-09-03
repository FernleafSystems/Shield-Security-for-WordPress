<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\LogHandlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use Monolog\Handler\AbstractProcessingHandler;

class LocalDbWriter extends AbstractProcessingHandler {

	use ModConsumer;

	/**
	 * @inheritDoc
	 */
	protected function write( array $record ) {
		try {
			$this->createPrimaryLogRecord( $record );
		}
		catch ( \Exception $e ) {
		}
	}

	protected function createPrimaryLogRecord( array $logData ) :bool {
		$modData = $this->getCon()->getModule_Data();

		$ipRecord = ( new IPRecords() )
			->setMod( $modData )
			->loadIP( $logData[ 'extra' ][ 'meta_request' ][ 'ip' ] );

		$reqRecord = ( new ReqLogs\RequestRecords() )
			->setMod( $modData )
			->loadReq( $logData[ 'extra' ][ 'meta_request' ][ 'rid' ], $ipRecord->id );

		// anything stored in the primary log record doesn't need stored in meta
		unset( $logData[ 'extra' ][ 'meta_request' ][ 'ip' ] );
		unset( $logData[ 'extra' ][ 'meta_request' ][ 'rid' ] );

		$success = $modData->getDbH_ReqLogs()
						   ->getQueryUpdater()
						   ->updateById( $reqRecord->id, [
							   'meta' => base64_encode( json_encode( array_merge(
								   $logData[ 'extra' ][ 'meta_shield' ],
								   $logData[ 'extra' ][ 'meta_request' ],
								   $logData[ 'extra' ][ 'meta_user' ],
								   $logData[ 'extra' ][ 'meta_wp' ]
							   ) ) )
						   ] );

		if ( !$success ) {
			throw new \Exception( 'Failed to insert' );
		}
		return true;
	}
}
