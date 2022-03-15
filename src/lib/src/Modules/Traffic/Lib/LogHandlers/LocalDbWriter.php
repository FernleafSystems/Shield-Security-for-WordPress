<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\LogHandlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
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

		$meta = array_merge(
			$logData[ 'extra' ][ 'meta_shield' ],
			$logData[ 'extra' ][ 'meta_request' ],
			$logData[ 'extra' ][ 'meta_user' ],
			$logData[ 'extra' ][ 'meta_wp' ]
		);

		$updateData = [];
		foreach ( [ 'verb', 'code', 'path', 'type' ] as $item ) {
			if ( !empty( $meta[ $item ] ) ) {
				$updateData[ $item ] = $meta[ $item ];
				unset( $meta[ $item ] );
			}
		}
		$updateData[ 'meta' ] = base64_encode( json_encode( $meta ) );

		$success = $modData->getDbH_ReqLogs()
						   ->getQueryUpdater()
						   ->updateById( $reqRecord->id, $updateData );

		if ( !$success ) {
			throw new \Exception( 'Failed to insert' );
		}
		return true;
	}
}
