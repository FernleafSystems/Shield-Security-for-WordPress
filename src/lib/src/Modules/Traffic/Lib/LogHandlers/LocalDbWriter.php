<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\LogHandlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\IsRequestLogged;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\ModConsumer;
use AptowebDeps\Monolog\Handler\AbstractProcessingHandler;

/**
 * Logic is a bit convoluted here. Basically a request is logged when:
 * - The activity log needs it (handled in the activity logger)
 * - The request isn't excluded by the various exclusion mechanisms
 * - The admin has selected to log all traffic (live logging)
 *
 * When live logging is enabled, we mark the request as transient if the request wouldn't otherwise normally have been
 * logged.
 */
class LocalDbWriter extends AbstractProcessingHandler {

	use ModConsumer;

	protected function write( array $record ) :void {
		try {
			$this->createPrimaryLogRecord( $record );
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function createPrimaryLogRecord( array $logData ) :bool {
		$modData = self::con()->getModule_Data();

		$ipRecord = ( new IPRecords() )->loadIP( $logData[ 'extra' ][ 'meta_request' ][ 'ip' ] );

		$reqRecord = ( new ReqLogs\RequestRecords() )->loadReq(
			$logData[ 'extra' ][ 'meta_request' ][ 'rid' ],
			$ipRecord->id
		);

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

		$updateData = [];
		foreach ( [ 'verb', 'code', 'path', 'type', 'uid', 'offense' ] as $item ) {
			if ( !empty( $meta[ $item ] ) ) {
				$updateData[ $item ] = $meta[ $item ];
				unset( $meta[ $item ] );
			}
		}
		$updateData[ 'transient' ] = !( $this->mod()->getRequestLogger()->isDependentLog()
										|| ( new IsRequestLogged() )->isLogged() );
		$updateData[ 'meta' ] = \base64_encode( \json_encode( $meta ) );

		$success = $modData->getDbH_ReqLogs()
						   ->getQueryUpdater()
						   ->updateById( $reqRecord->id, $updateData );

		if ( !$success ) {
			throw new \Exception( 'Failed to insert' );
		}

		return true;
	}
}