<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RunState {

	use PluginControllerConsumer;

	public const META_KEY_LAST_ERROR = 'last_error';

	public function markBuilding( int $scanID ) :void {
		$now = Services::Request()->ts();
		/** @var ?Record $scan */
		$scan = self::con()->db_con->scans->getQuerySelector()->byId( $scanID );
		$update = [
			'status'          => 'building',
			'last_process_at' => $now,
		];
		if ( !empty( $scan ) ) {
			$meta = \is_array( $scan->meta ) ? $scan->meta : [];
			if ( isset( $meta[ self::META_KEY_LAST_ERROR ] ) ) {
				unset( $meta[ self::META_KEY_LAST_ERROR ] );
				$scan->meta = $meta;
				$update[ 'meta' ] = $scan->getRawData()[ 'meta' ];
			}
		}
		self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, [
			...$update,
		] );
	}

	public function markBuilt( int $scanID ) :void {
		$now = Services::Request()->ts();
		self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, [
			'status'          => 'built',
			'ready_at'        => $now,
			'last_process_at' => $now,
		] );
	}

	public function touch( int $scanID ) :void {
		self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, [
			'last_process_at' => Services::Request()->ts(),
		] );
	}

	public function markCompleted( int $scanID ) :void {
		$now = Services::Request()->ts();
		self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, [
			'finished_at'     => $now,
			'status'          => 'completed',
			'last_process_at' => $now,
		] );
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
			'status'          => 'failed',
			'last_process_at' => $now,
		];
		/** @var ?Record $scan */
		$scan = self::con()->db_con->scans->getQuerySelector()->byId( $scanID );
		if ( !empty( $scan ) ) {
			$meta = \is_array( $scan->meta ) ? $scan->meta : [];
			if ( $failureMessage === '' ) {
				unset( $meta[ self::META_KEY_LAST_ERROR ] );
			}
			else {
				$meta[ self::META_KEY_LAST_ERROR ] = $failureMessage;
			}
			$scan->meta = $meta;
			$update[ 'meta' ] = $scan->getRawData()[ 'meta' ];
		}

		self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, $update );
		$this->deleteUnfinishedItems( $scanID );
	}

	public function markRunning( int $scanID ) :void {
		$now = Services::Request()->ts();
		/** @var ?Record $scan */
		$scan = self::con()->db_con->scans->getQuerySelector()->byId( $scanID );
		$update = [
			'status'          => 'running',
			'last_process_at' => $now,
		];
		if ( empty( $scan ) || (int)$scan->started_at === 0 ) {
			$update[ 'started_at' ] = $now;
		}
		if ( !empty( $scan ) ) {
			$meta = \is_array( $scan->meta ) ? $scan->meta : [];
			if ( isset( $meta[ self::META_KEY_LAST_ERROR ] ) ) {
				unset( $meta[ self::META_KEY_LAST_ERROR ] );
				$scan->meta = $meta;
				$update[ 'meta' ] = $scan->getRawData()[ 'meta' ];
			}
		}
		self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, $update );
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
}
