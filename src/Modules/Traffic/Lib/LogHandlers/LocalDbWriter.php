<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\LogHandlers;

use Monolog\Handler\AbstractProcessingHandler;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops as ReqLogsDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\RequestRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\{
	RequestLogger,
	RequestLogRetentionPolicy
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * Request logs are tiered during write so pruning can retain high-signal requests longer without user configuration.
 */
class LocalDbWriter extends AbstractProcessingHandler {

	use PluginControllerConsumer;

	private ?RequestLogger $requestLogger = null;

	private bool $failureLogged = false;

	public function setRequestLogger( RequestLogger $requestLogger ) :self {
		$this->requestLogger = $requestLogger;
		return $this;
	}

	protected function write( array $record ) :void {
		$this->failureLogged = false;
		try {
			$reqRecord = $this->createPrimaryLogRecord( $record );
			if ( $this->requestLogger instanceof RequestLogger ) {
				$this->requestLogger->setLastLoggedRecord( $reqRecord );
			}
		}
		catch ( \Exception $e ) {
			if ( !$this->hasLoggedFailure() ) {
				$this->logRequestFailure( $this->buildFailureContext(
					'request_log_write',
					$e->getMessage(),
					$this->buildRequestContext( $record )
				) );
			}
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function createPrimaryLogRecord( array $logData ) :ReqLogsDB\Record {
		$requestMeta = $logData[ 'extra' ][ 'meta_request' ];
		$requestContext = $this->buildRequestContext( $logData );
		try {
			$ipRecord = ( new IPRecords() )->loadIP( $requestMeta[ 'ip' ] );
		}
		catch ( \Exception $e ) {
			$this->logRequestFailure( $this->buildFailureContext(
				'ip_record_load',
				'Failed to load/create IP record: '.$e->getMessage(),
				$requestContext
			) );
			throw $e;
		}
		$reqID = $requestMeta[ 'rid' ];

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

		$recordData = \array_intersect_key( $meta, \array_flip( [ 'verb', 'code', 'path', 'type', 'uid', 'offense' ] ) );
		$recordData[ 'req_id' ] = $reqID;
		$recordData[ 'ip_ref' ] = $ipRecord->id;
		$recordData[ 'meta' ] = \base64_encode( \wp_json_encode( \array_diff_key( $meta, $recordData ) ) );
		$recordData[ 'transient' ] = ( new RequestLogRetentionPolicy() )->shouldMarkAsTransient( [
			'has_params' => !empty( $meta[ 'has_params' ] ),
			'offense'    => !empty( $meta[ 'offense' ] ),
		] );

		if ( !$this->isDependentLog() ) {
			$inserted = $this->insertCompleteRequestRecord( $recordData );
			if ( $inserted instanceof ReqLogsDB\Record ) {
				return $inserted;
			}
		}

		$requestRecords = new RequestRecords();
		try {
			$reqRecord = $requestRecords->loadReq( $reqID, $ipRecord->id );
		}
		catch ( \Exception $e ) {
			$this->logRequestFailure( $this->buildFailureContext(
				'request_record_load',
				'Failed to load/create Request Record: '.$e->getMessage(),
				$requestContext
			) );
			throw $e;
		}
		if ( !$reqRecord instanceof ReqLogsDB\Record ) {
			$lastFailure = $requestRecords->getLastFailure();
			$this->logRequestFailure( $this->buildFailureContext(
				(string)( $lastFailure[ 'stage' ] ?? 'request_record_load' ),
				(string)( $lastFailure[ 'message' ] ?? 'Failed to load/create Request Record' ),
				$requestContext,
				$lastFailure
			) );
			throw new \Exception( 'Failed to load/create Request Record' );
		}

		$this->updateExistingRequestRecord( $reqRecord, $recordData, $requestContext );
		return $reqRecord;
	}

	private function insertCompleteRequestRecord( array $recordData ) :?ReqLogsDB\Record {
		$dbh = self::con()->db_con->req_logs;
		/** @var ReqLogsDB\Record $record */
		$record = $dbh->getRecord()->applyFromArray( $recordData );
		/** @var ReqLogsDB\Insert $insert */
		$insert = $dbh->getQueryInserter();
		return $insert->setIgnore()->insertGetRecord( $record );
	}

	/**
	 * @throws \Exception
	 */
	private function updateExistingRequestRecord( ReqLogsDB\Record $record, array $recordData, array $requestContext ) :void {
		$update = \array_diff_key( $recordData, \array_flip( [ 'req_id', 'ip_ref' ] ) );
		if ( !self::con()->db_con->req_logs->getQueryUpdater()->updateById( $record->id, $update ) ) {
			$this->logRequestFailure( $this->buildFailureContext(
				'request_record_update',
				'Failed to update request log',
				$requestContext
			) );
			throw new \Exception( 'Failed to update request log' );
		}
		$record->applyFromArray( \array_merge( $record->getRawData(), $update ) );
	}

	private function isDependentLog() :bool {
		return $this->requestLogger instanceof RequestLogger && $this->requestLogger->isDependentLog();
	}

	private function logRequestFailure( array $context ) :void {
		$this->failureLogged = true;
		error_log( 'Shield request log write failed: '.$this->formatLogContext( $context ) );
	}

	private function hasLoggedFailure() :bool {
		return $this->failureLogged;
	}

	private function buildFailureContext( string $stage, string $message, array $requestContext, array $context = [] ) :array {
		$failure = \array_merge(
			[
				'stage'    => $stage,
				'message'  => $message,
				'db_error' => $this->currentDbError(),
			],
			$context,
			$requestContext
		);
		$failure[ 'stage' ] = $stage;
		$failure[ 'message' ] = $message;
		return $failure;
	}

	private function buildRequestContext( array $logData ) :array {
		$requestMeta = \is_array( $logData[ 'extra' ][ 'meta_request' ] ?? null )
			? $logData[ 'extra' ][ 'meta_request' ]
			: [];

		return [
			'type'   => $requestMeta[ 'type' ] ?? '',
			'path'   => $requestMeta[ 'path' ] ?? '',
			'req_id' => $requestMeta[ 'rid' ] ?? '',
			'ip'     => $requestMeta[ 'ip' ] ?? '',
		];
	}

	private function formatLogContext( array $context ) :string {
		$parts = [];
		foreach ( [ 'stage', 'message', 'db_error', 'req_id', 'type', 'path', 'ip' ] as $key ) {
			$parts[] = \sprintf( '%s=%s', $key, $this->normaliseLogValue( $context[ $key ] ?? '' ) );
		}
		return \implode( '; ', $parts );
	}

	private function normaliseLogValue( $value ) :string {
		$value = \is_scalar( $value ) ? (string)$value : '';
		$value = \preg_replace( '/[\r\n\t;]+/', ' ', \trim( $value ) );
		if ( \is_string( $value ) ) {
			$queryPos = \strpos( $value, '?' );
			if ( $queryPos !== false && \str_starts_with( $value, '/' ) ) {
				$value = \substr( $value, 0, $queryPos );
			}
		}
		return \is_string( $value ) ? \substr( $value, 0, 300 ) : '';
	}

	private function currentDbError() :string {
		$wpdb = Services::WpDb()->loadWpdb();
		return (string)$wpdb->last_error;
	}
}
