<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\LogHandlers;

use AptowebDeps\Monolog\Handler\AbstractProcessingHandler;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\RequestRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

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

	use PluginControllerConsumer;

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
		$ipRecord = ( new IPRecords() )->loadIP( $logData[ 'extra' ][ 'meta_request' ][ 'ip' ] );

		$reqRecord = ( new RequestRecords() )->loadReq(
			$logData[ 'extra' ][ 'meta_request' ][ 'rid' ],
			$ipRecord->id
		);
		if ( empty( $reqRecord ) ) {
			throw new \Exception( 'Failed to load/create Request Record' );
		}

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

		$update = \array_intersect_key( $meta, \array_flip( [ 'verb', 'code', 'path', 'type', 'uid', 'offense' ] ) );
		$update[ 'meta' ] = \base64_encode( \wp_json_encode( \array_diff_key( $meta, $update ) ) );
		$update[ 'transient' ] = false;

		if ( !self::con()->db_con->req_logs->getQueryUpdater()->updateById( $reqRecord->id, $update ) ) {
			throw new \Exception( 'Failed to insert' );
		}

		return true;
	}
}