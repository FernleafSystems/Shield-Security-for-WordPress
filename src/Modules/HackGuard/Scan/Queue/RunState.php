<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RunState {

	use PluginControllerConsumer;

	public const META_KEY_LAST_ERROR = 'last_error';
	public const META_KEY_WATCHDOG_RECOVERY = 'watchdog_recovery';
	private const QUEUE_ITEM_EXCEPTION_PREFIX = 'Queue item exception:';
	private const QUEUE_ITEM_EXCEPTION_MAX_LENGTH = 500;

	public function markBuilding( Record $scan ) :void {
		$now = Services::Request()->ts();
		$update = [
			'status'          => ScanStatus::BUILDING,
			'last_process_at' => $now,
		];
		$meta = \is_array( $scan->meta ) ? $scan->meta : [];
		if ( isset( $meta[ self::META_KEY_LAST_ERROR ] ) ) {
			unset( $meta[ self::META_KEY_LAST_ERROR ] );
			$scan->meta = $meta;
			$update[ 'meta' ] = $scan->getRawData()[ 'meta' ];
		}

		self::con()->db_con->scans->getQueryUpdater()->updateById( (int)$scan->id, $update );
		QueueHeartbeat::primeBuilding( (int)$scan->id, $now );
	}

	public function markBuilt( Record $scan ) :void {
		$now = Services::Request()->ts();
		$update = [
			'status'          => ScanStatus::BUILT,
			'ready_at'        => $now,
			'last_process_at' => $now,
		];
		$raw = $scan->getRawData();
		if ( isset( $raw[ 'meta' ] ) ) {
			$update[ 'meta' ] = $raw[ 'meta' ];
		}
		self::con()->db_con->scans->getQueryUpdater()->updateById( (int)$scan->id, $update );
	}

	public function markFailed( int $scanID, string $failureMessage = '' ) :void {
		$now = Services::Request()->ts();
		error_log( \sprintf(
			'Shield scan marked failed: scan_id=%d message=%s',
			$scanID,
			$failureMessage
		) );
		$update = [
			'finished_at'     => $now,
			'status'          => ScanStatus::FAILED,
			'last_process_at' => $now,
		];
		/** @var ?Record $scan */
		$scan = self::con()->db_con->scans->getQuerySelector()->byId( $scanID );
		if ( !empty( $scan ) ) {
			$meta = \is_array( $scan->meta ) ? $scan->meta : [];
			if ( $failureMessage === '' ) {
				unset( $meta[ self::META_KEY_LAST_ERROR ] );
			}
			elseif ( !$this->shouldPreserveQueueItemException( $failureMessage, $meta[ self::META_KEY_LAST_ERROR ] ?? null ) ) {
				$meta[ self::META_KEY_LAST_ERROR ] = $failureMessage;
			}
			$scan->meta = $meta;
			$update[ 'meta' ] = $scan->getRawData()[ 'meta' ];
		}

		self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, $update );
		$this->deleteUnfinishedItems( $scanID );
	}

	public function markRunning( QueueItemVO $item ) :void {
		$now = Services::Request()->ts();
		$update = [
			'status'          => ScanStatus::RUNNING,
			'last_process_at' => $now,
		];
		if ( $item->scan_started_at === 0 ) {
			$update[ 'started_at' ] = $now;
		}
		$meta = $item->meta;
		if ( isset( $meta[ self::META_KEY_LAST_ERROR ] ) || isset( $meta[ self::META_KEY_WATCHDOG_RECOVERY ] ) ) {
			unset( $meta[ self::META_KEY_LAST_ERROR ] );
			unset( $meta[ self::META_KEY_WATCHDOG_RECOVERY ] );
			$item->meta = $meta;
			$scan = new Record();
			$scan->meta = $meta;
			$update[ 'meta' ] = $scan->getRawData()[ 'meta' ];
		}
		self::con()->db_con->scans->getQueryUpdater()->updateById( $item->scan_id, $update );
		QueueHeartbeat::primeRunning( $item->scan_id, $now );
	}

	public function recordQueueItemException( QueueItemVO $item, \Throwable $e ) :void {
		try {
			/** @var ?Record $scan */
			$scan = self::con()->db_con->scans->getQuerySelector()->byId( $item->scan_id );
			if ( empty( $scan ) ) {
				return;
			}

			$meta = \is_array( $scan->meta ) ? $scan->meta : [];
			$meta[ self::META_KEY_LAST_ERROR ] = $this->buildQueueItemExceptionMessage( $item, $e );
			$scan->meta = $meta;
			self::con()->db_con->scans->getQueryUpdater()->updateById( $item->scan_id, [
				'meta' => $scan->getRawData()[ 'meta' ],
			] );
		}
		catch ( \Throwable $diagnosticsFailure ) {
			unset( $diagnosticsFailure );
		}
	}

	public function markUnfinishedRunsFailed() :void {
		$scans = self::con()->db_con->scans->getQuerySelector()
					 ->filterByNotFinished()
					 ->queryWithResult();
		foreach ( \array_map(
			static fn( $scan ) :int => (int)( $scan->id ?? 0 ),
			\is_array( $scans ) ? $scans : []
		) as $scanID ) {
			if ( $scanID > 0 ) {
				$this->markFailed( $scanID );
			}
		}
	}

	public function deleteUnfinishedItems( int $scanID ) :void {
		self::con()->db_con->scan_items->getQueryDeleter()
			->filterByScan( $scanID )
			->filterByNotFinished()
			->query();
	}

	private function shouldPreserveQueueItemException( string $failureMessage, $existingMessage ) :bool {
		return $failureMessage === ReconcileQueue::MESSAGE_TIMED_OUT
			   && \is_string( $existingMessage )
			   && $existingMessage !== ''
			   && \strpos( $existingMessage, self::QUEUE_ITEM_EXCEPTION_PREFIX ) === 0;
	}

	private function buildQueueItemExceptionMessage( QueueItemVO $item, \Throwable $e ) :string {
		return $this->truncateQueueItemExceptionMessage( \sprintf(
			'%s scan=%s qitem_id=%d attempt=%d exception=%s message=%s',
			self::QUEUE_ITEM_EXCEPTION_PREFIX,
			$this->singleLine( $item->scan ),
			$item->qitem_id,
			$item->attempts,
			$this->exceptionShortClass( $e ),
			$this->singleLine( $e->getMessage() )
		) );
	}

	private function truncateQueueItemExceptionMessage( string $message ) :string {
		if ( \strlen( $message ) <= self::QUEUE_ITEM_EXCEPTION_MAX_LENGTH ) {
			return $message;
		}
		return \substr( $message, 0, self::QUEUE_ITEM_EXCEPTION_MAX_LENGTH - 3 ).'...';
	}

	private function singleLine( string $value ) :string {
		$value = (string)\preg_replace( '#\s+#', ' ', $value );
		return \trim( $value );
	}

	private function exceptionShortClass( \Throwable $e ) :string {
		$parts = \explode( '\\', \get_class( $e ) );
		$short = \end( $parts );
		return \is_string( $short ) && $short !== '' ? $short : \get_class( $e );
	}
}
