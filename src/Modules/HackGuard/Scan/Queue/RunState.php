<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class RunState {

	use PluginControllerConsumer;

	public const META_KEY_LAST_ERROR = 'last_error';
	public const META_KEY_WATCHDOG_RECOVERY = 'watchdog_recovery';
	private const QUEUE_ITEM_EXCEPTION_PREFIX = 'Queue item exception:';
	private const QUEUE_ITEM_EXCEPTION_MAX_LENGTH = 500;
	private const META_MUTATION_MAX_ATTEMPTS = 10;

	public function markBuilding( Record $scan ) :void {
		$now = Services::Request()->ts();
		$update = [
			'status'          => ScanStatus::BUILDING,
			'last_process_at' => $now,
		];
		$meta = \is_array( $scan->meta ) ? $scan->meta : [];
		$originalMeta = $meta;
		unset( $meta[ self::META_KEY_LAST_ERROR ], $meta[ self::META_KEY_WATCHDOG_RECOVERY ] );
		if ( $meta !== $originalMeta ) {
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
		self::con()->db_con->scans->getQueryUpdater()->updateById( $item->scan_id, $update );
		try {
			$item->meta = $this->mutateScanMeta(
				$item->scan_id,
				function ( array $meta ) :array {
					if ( isset( $meta[ self::META_KEY_LAST_ERROR ] )
						 && !$this->isQueueItemException( $meta[ self::META_KEY_LAST_ERROR ] ) ) {
						unset( $meta[ self::META_KEY_LAST_ERROR ] );
					}
					unset( $meta[ self::META_KEY_WATCHDOG_RECOVERY ] );
					return $meta;
				}
			);
		}
		catch ( \Throwable $metadataFailure ) {
			unset( $metadataFailure );
		}
		QueueHeartbeat::primeRunning( $item->scan_id, $now );
	}

	public function recordQueueItemException( QueueItemVO $item, \Throwable $e ) :void {
		try {
			$message = $this->buildQueueItemExceptionMessage( $item, $e );
			$this->mutateScanMeta(
				$item->scan_id,
				static function ( array $meta ) use ( $message ) :array {
					$meta[ self::META_KEY_LAST_ERROR ] = $message;
					return $meta;
				}
			);
		}
		catch ( \Throwable $diagnosticsFailure ) {
			unset( $diagnosticsFailure );
		}
	}

	public function clearQueueItemExceptionForFinishedItem( QueueItemVO $item ) :void {
		try {
			$this->mutateScanMeta(
				$item->scan_id,
				function ( array $meta ) use ( $item ) :array {
					if ( $this->queueItemIDFromException( $meta[ self::META_KEY_LAST_ERROR ] ?? null ) === $item->qitem_id ) {
						unset( $meta[ self::META_KEY_LAST_ERROR ] );
					}
					return $meta;
				}
			);
		}
		catch ( \Throwable $diagnosticsFailure ) {
			unset( $diagnosticsFailure );
		}
	}

	/**
	 * @param array{plugin:list<string>,theme:list<string>} $incompleteBefore
	 * @return array{plugin:list<string>,theme:list<string>}
	 */
	public function persistAssetComparisonIncomplete(
		QueueItemVO $item,
		Scans\Afs\ScanActionVO $action,
		array $incompleteBefore
	) :array {
		$executionNew = $action->hasValidAssetComparisonIncomplete()
			? $this->assetSetDifference( $action->getAssetComparisonIncomplete(), $incompleteBefore )
			: [ 'plugin' => [], 'theme' => [] ];
		$writtenDelta = [ 'plugin' => [], 'theme' => [] ];
		$persistedMarkerIsValid = false;

		$effectiveMeta = $this->mutateScanMeta(
			$item->scan_id,
			function ( array $meta ) use ( $item, $executionNew, &$writtenDelta, &$persistedMarkerIsValid ) :array {
				$writtenDelta = [ 'plugin' => [], 'theme' => [] ];
				$persistedAction = $this->afsActionFromMeta( $item, $meta );
				$persistedMarkerIsValid = $persistedAction->hasValidAssetComparisonIncomplete();
				if ( !$persistedMarkerIsValid ) {
					return $meta;
				}

				$writtenDelta = $this->assetSetDifference(
					$executionNew,
					$persistedAction->getAssetComparisonIncomplete()
				);
				if ( $this->isAssetSetEmpty( $writtenDelta ) ) {
					return $meta;
				}

				foreach ( [ 'plugin', 'theme' ] as $assetType ) {
					foreach ( $writtenDelta[ $assetType ] as $assetKey ) {
						$persistedAction->markAssetComparisonIncomplete( $assetType, $assetKey );
					}
				}
				$meta[ 'asset_comparison_incomplete' ] = $persistedAction->getAssetComparisonIncomplete();
				return $meta;
			}
		);

		$this->applyEffectiveScanMeta( $item, $action, $effectiveMeta );
		if ( $persistedMarkerIsValid ) {
			$effectiveAction = $this->afsActionFromMeta( $item, $effectiveMeta );
			if ( !$effectiveAction->hasValidAssetComparisonIncomplete()
				 || !$this->isAssetSetEmpty( $this->assetSetDifference(
					$executionNew,
					$effectiveAction->getAssetComparisonIncomplete()
				) ) ) {
				throw new \RuntimeException( 'Asset comparison incomplete marker verification failed.' );
			}
		}

		return $writtenDelta;
	}

	/**
	 * @return array{persistence_succeeded:bool,should_dispatch:bool,should_fail:bool}
	 */
	public function recoverReadyUnstartedScan(
		int $scanID,
		int $now,
		int $cooldown,
		int $maxAttempts
	) :array {
		$result = [
			'persistence_succeeded' => true,
			'should_dispatch'       => false,
			'should_fail'           => false,
		];

		try {
			$this->mutateScanMeta(
				$scanID,
				static function ( array $meta ) use ( $now, $cooldown, $maxAttempts, &$result ) :array {
					$result = [
						'persistence_succeeded' => true,
						'should_dispatch'       => false,
						'should_fail'           => false,
					];
					$recovery = \is_array( $meta[ self::META_KEY_WATCHDOG_RECOVERY ] ?? null )
						? $meta[ self::META_KEY_WATCHDOG_RECOVERY ]
						: [];
					if ( (int)( $recovery[ 'last_attempt_at' ] ?? 0 ) > $now - $cooldown ) {
						return $meta;
					}

					$attempts = (int)( $recovery[ 'attempts' ] ?? 0 );
					if ( $attempts >= $maxAttempts ) {
						$result[ 'should_fail' ] = true;
						return $meta;
					}

					$meta[ self::META_KEY_WATCHDOG_RECOVERY ] = [
						'attempts'        => $attempts + 1,
						'last_attempt_at' => $now,
					];
					$result[ 'should_dispatch' ] = true;
					return $meta;
				},
				$now
			);
		}
		catch ( \Throwable $persistenceFailure ) {
			unset( $persistenceFailure );
			$result = [
				'persistence_succeeded' => false,
				'should_dispatch'       => false,
				'should_fail'           => false,
			];
		}

		return $result;
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
			   && $this->isQueueItemException( $existingMessage );
	}

	private function isQueueItemException( $message ) :bool {
		return \is_string( $message )
			   && \strpos( $message, self::QUEUE_ITEM_EXCEPTION_PREFIX ) === 0;
	}

	private function queueItemIDFromException( $message ) :?int {
		if ( !$this->isQueueItemException( $message )
			 || \preg_match(
				 '#^'.\preg_quote( self::QUEUE_ITEM_EXCEPTION_PREFIX, '#' ).' scan=\S+ qitem_id=([1-9][0-9]*) attempt=#',
				 $message,
				 $matches
			 ) !== 1 ) {
			return null;
		}

		$itemID = (int)$matches[ 1 ];
		return (string)$itemID === $matches[ 1 ] ? $itemID : null;
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

	/**
	 * @param callable(array):array $mutation
	 */
	private function mutateScanMeta( int $scanID, callable $mutation, ?int $lastProcessAt = null ) :array {
		for ( $attempt = 0; $attempt < self::META_MUTATION_MAX_ATTEMPTS; $attempt++ ) {
			$observed = $this->loadScanMeta( $scanID );
			$nextMeta = $mutation( $observed[ 'meta' ] );
			if ( $nextMeta === $observed[ 'meta' ] ) {
				return $observed[ 'meta' ];
			}

			$record = new Record();
			$record->meta = $nextMeta;
			$nextRaw = $record->getRawData()[ 'meta' ] ?? null;
			if ( !\is_string( $nextRaw ) || $nextRaw === '' ) {
				throw new \RuntimeException( 'Scan metadata serialization failed.' );
			}

			$set = "`meta`='".esc_sql( $nextRaw )."'";
			if ( $lastProcessAt !== null ) {
				$set .= ', `last_process_at`='.(int)$lastProcessAt;
			}
			$updated = Services::WpDb()->doSql( \sprintf(
				"UPDATE `%s`
					SET %s
					WHERE `id`=%d
					  AND BINARY `meta`=BINARY '%s';",
				self::con()->db_con->scans->getTable(),
				$set,
				$scanID,
				esc_sql( $observed[ 'raw_meta' ] )
			) );
			if ( $updated === false ) {
				throw new \RuntimeException( 'Scan metadata update failed.' );
			}
			if ( (int)$updated === 0 ) {
				continue;
			}

			return $this->loadScanMeta( $scanID )[ 'meta' ];
		}

		throw new \RuntimeException( 'Scan metadata update conflicts were exhausted.' );
	}

	/**
	 * @return array{meta:array,raw_meta:string}
	 */
	private function loadScanMeta( int $scanID ) :array {
		/** @var ?Record $scan */
		$scan = self::con()->db_con->scans->getQuerySelector()->byId( $scanID );
		if ( empty( $scan ) || (int)$scan->id !== $scanID ) {
			throw new \RuntimeException( 'Scan metadata reload failed.' );
		}

		$rawMeta = $scan->getRawData()[ 'meta' ] ?? null;
		$meta = $scan->meta;
		if ( !\is_string( $rawMeta ) || !\is_array( $meta ) ) {
			throw new \RuntimeException( 'Scan metadata reload failed.' );
		}
		return [
			'meta'     => $meta,
			'raw_meta' => $rawMeta,
		];
	}

	private function afsActionFromMeta( QueueItemVO $item, array $meta ) :Scans\Afs\ScanActionVO {
		return ( new Scans\Afs\ScanActionVO() )->applyFromArray( \array_merge(
			$meta,
			[
				'scan'       => $item->scan,
				'scope_type' => $item->scope_type,
				'scope_key'  => $item->scope_key,
			]
		) );
	}

	private function applyEffectiveScanMeta(
		QueueItemVO $item,
		Scans\Afs\ScanActionVO $action,
		array $meta
	) :void {
		$item->meta = $meta;
		if ( \array_key_exists( 'asset_comparison_incomplete', $meta ) ) {
			$action->asset_comparison_incomplete = $meta[ 'asset_comparison_incomplete' ];
		}
		else {
			unset( $action->asset_comparison_incomplete );
		}
	}

	/**
	 * @param array{plugin:list<string>,theme:list<string>} $left
	 * @param array{plugin:list<string>,theme:list<string>} $right
	 * @return array{plugin:list<string>,theme:list<string>}
	 */
	private function assetSetDifference( array $left, array $right ) :array {
		$difference = [ 'plugin' => [], 'theme' => [] ];
		foreach ( [ 'plugin', 'theme' ] as $assetType ) {
			foreach ( $left[ $assetType ] as $assetKey ) {
				if ( !\in_array( $assetKey, $right[ $assetType ], true ) ) {
					$difference[ $assetType ][] = $assetKey;
				}
			}
		}
		return $difference;
	}

	/**
	 * @param array{plugin:list<string>,theme:list<string>} $assets
	 */
	private function isAssetSetEmpty( array $assets ) :bool {
		return empty( $assets[ 'plugin' ] ) && empty( $assets[ 'theme' ] );
	}
}
