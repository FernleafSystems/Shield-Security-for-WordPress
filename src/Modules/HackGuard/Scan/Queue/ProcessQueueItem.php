<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\SetScanCompleted;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Store;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanActionFromSlug;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class ProcessQueueItem {

	use PluginControllerConsumer;

	public function run( QueueItemVO $item ) {
		$runState = new RunState();
		$runState->markRunning( $item );

		try {
			$scan = $this->runScanOnItem( $item );
			$action = $scan[ 'action' ];
			if ( $action instanceof Scans\Afs\ScanActionVO && $action->scope_type === 'full' ) {
				$this->persistAssetComparisonIncomplete(
					$item,
					$action,
					$scan[ 'asset_comparison_incomplete_before' ]
				);
			}

			( new Store() )->store( $item, $scan[ 'results' ] );

			$itemFinished = self::con()
				->db_con
				->scan_items
				->getQueryUpdater()
				->updateById( $item->qitem_id, [
					'finished_at' => Services::Request()->ts()
				] );
			if ( $itemFinished ) {
				$runState->clearQueueItemExceptionForFinishedItem( $item );
			}

			( new SetScanCompleted() )->runForQueueItem( $item );
		}
		catch ( \Throwable $e ) {
			error_log( \sprintf(
				'Shield scan processing exception: scan_id=%d qitem_id=%d scan=%s message=%s',
				$item->scan_id,
				$item->qitem_id,
				$item->scan,
				$e->getMessage()
			) );
			$runState->recordQueueItemException( $item, $e );
		}
	}

	/**
	 * @throws \Exception
	 * @return array{
	 *     action:Scans\Base\BaseScanActionVO,
	 *     results:array,
	 *     asset_comparison_incomplete_before:array{plugin:list<string>,theme:list<string>}
	 * }
	 */
	private function runScanOnItem( QueueItemVO $item ) :array {
		$action = ScanActionFromSlug::GetAction( $item->scan )->applyFromArray( \array_merge(
			$item->meta,
			[
				'scan'       => $item->scan,
				'scope_type' => $item->scope_type,
				'scope_key'  => $item->scope_key,
			]
		) );
		$action->items = $item->items;
		$incompleteBefore = $action instanceof Scans\Afs\ScanActionVO
			&& $action->scope_type === 'full'
			&& $action->hasValidAssetComparisonIncomplete()
			? $action->getAssetComparisonIncomplete()
			: [ 'plugin' => [], 'theme' => [] ];
		$heartbeat = new QueueHeartbeat();
		$action->progress_callback = static function () use ( $heartbeat, $item ) :void {
			$heartbeat->tick( $item->scan_id );
		};

		$this->getScanner( $action )
			 ->setScanActionVO( $action )
			 ->run();

		if ( $action->usleep > 0 ) {
			\usleep( $action->usleep );
		}

		return [
			'action'                             => $action,
			'results'                            => \is_array( $action->results ) ? $action->results : [],
			'asset_comparison_incomplete_before' => $incompleteBefore,
		];
	}

	/**
	 * @param array{plugin:list<string>,theme:list<string>} $incompleteBefore
	 */
	private function persistAssetComparisonIncomplete(
		QueueItemVO $item,
		Scans\Afs\ScanActionVO $action,
		array $incompleteBefore
	) :void {
		$executionNew = $action->hasValidAssetComparisonIncomplete()
			? $this->assetSetDifference( $action->getAssetComparisonIncomplete(), $incompleteBefore )
			: [ 'plugin' => [], 'theme' => [] ];
		$writtenDelta = [ 'plugin' => [], 'theme' => [] ];

		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$persisted = $this->loadPersistedAfsAction( $item );
			$persistedAction = $persisted[ 'action' ];
			if ( !$persistedAction->hasValidAssetComparisonIncomplete() ) {
				$this->applyEffectiveScanMeta( $item, $action, $persisted[ 'meta' ] );
				return;
			}

			$remaining = $this->assetSetDifference(
				$executionNew,
				$persistedAction->getAssetComparisonIncomplete()
			);
			if ( $this->isAssetSetEmpty( $remaining ) ) {
				$this->applyEffectiveScanMeta( $item, $action, $persisted[ 'meta' ] );
				break;
			}

			foreach ( [ 'plugin', 'theme' ] as $assetType ) {
				foreach ( $remaining[ $assetType ] as $assetKey ) {
					$persistedAction->markAssetComparisonIncomplete( $assetType, $assetKey );
				}
			}
			$expectedMeta = $persisted[ 'meta' ];
			$expectedMeta[ 'asset_comparison_incomplete' ] = $persistedAction->getAssetComparisonIncomplete();
			$scan = $persisted[ 'scan' ];
			$scan->meta = $expectedMeta;
			$expectedRaw = $scan->getRawData()[ 'meta' ] ?? null;
			if ( !\is_string( $expectedRaw ) ) {
				throw new \RuntimeException( 'Asset comparison incomplete marker serialization failed.' );
			}

			$updated = Services::WpDb()->doSql( \sprintf(
				"UPDATE `%s`
					SET `meta`='%s'
					WHERE `id`=%d
					  AND BINARY `meta`=BINARY '%s';",
				self::con()->db_con->scans->getTable(),
				esc_sql( $expectedRaw ),
				$item->scan_id,
				esc_sql( $persisted[ 'raw_meta' ] )
			) );
			if ( $updated === false ) {
				throw new \RuntimeException( 'Asset comparison incomplete marker update failed.' );
			}
			if ( (int)$updated === 0 ) {
				continue;
			}

			$writtenDelta = $remaining;
			$persisted = $this->loadPersistedAfsAction( $item );
			if ( !$persisted[ 'action' ]->hasValidAssetComparisonIncomplete() ) {
				$this->applyEffectiveScanMeta( $item, $action, $persisted[ 'meta' ] );
				return;
			}
			if ( !$this->isAssetSetEmpty( $this->assetSetDifference(
				$executionNew,
				$persisted[ 'action' ]->getAssetComparisonIncomplete()
			) ) ) {
				throw new \RuntimeException( 'Asset comparison incomplete marker verification failed.' );
			}
			$this->applyEffectiveScanMeta( $item, $action, $persisted[ 'meta' ] );
			break;
		}
		if ( $attempt === 10 ) {
			throw new \RuntimeException( 'Asset comparison incomplete marker update conflicts were exhausted.' );
		}

		foreach ( [ 'plugin', 'theme' ] as $assetType ) {
			foreach ( $writtenDelta[ $assetType ] as $assetKey ) {
				try {
					if ( !self::con()->comps->asset_coordinator->enqueueAsset( $assetType, $assetKey ) ) {
						$this->logAssetEnqueueFailure( $assetType, $assetKey, 'returned false' );
					}
				}
				catch ( \Throwable $e ) {
					$this->logAssetEnqueueFailure( $assetType, $assetKey, $e->getMessage() );
				}
			}
		}
	}

	/**
	 * @return array{scan:object,action:Scans\Afs\ScanActionVO,meta:array,raw_meta:string}
	 */
	private function loadPersistedAfsAction( QueueItemVO $item ) :array {
		$scan = self::con()->db_con->scans->getQuerySelector()->byId( $item->scan_id );
		if ( empty( $scan ) || (int)$scan->id !== $item->scan_id ) {
			throw new \RuntimeException( 'Scan row reload failed before asset comparison marker persistence.' );
		}
		$meta = $scan->meta;
		$rawMeta = $scan->getRawData()[ 'meta' ] ?? null;
		if ( !\is_array( $meta ) || !\is_string( $rawMeta ) ) {
			throw new \RuntimeException( 'Scan metadata reload failed before asset comparison marker persistence.' );
		}

		return [
			'scan'     => $scan,
			'action'   => ( new Scans\Afs\ScanActionVO() )->applyFromArray( \array_merge(
				$meta,
				[
					'scan'       => $item->scan,
					'scope_type' => $item->scope_type,
					'scope_key'  => $item->scope_key,
				]
			) ),
			'meta'     => $meta,
			'raw_meta' => $rawMeta,
		];
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

	private function logAssetEnqueueFailure( string $assetType, string $assetKey, string $message ) :void {
		$message = \trim( (string)\preg_replace( '#\s+#', ' ', $message ) );
		error_log( \sprintf(
			'Shield AFS asset follow-up enqueue failed: type=%s key=%s message=%s',
			$assetType,
			$assetKey,
			\substr( $message, 0, 200 )
		) );
	}

	/**
	 * @param Scans\Base\BaseScanActionVO $action
	 * @return Scans\Base\BaseScan
	 */
	private function getScanner( $action ) {
		$class = $action->getScanNamespace().'Scan';
		/** @var Scans\Base\BaseScan $o */
		$o = new $class();
		return $o->setScanActionVO( $action );
	}
}
