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
		if ( !$action->hasValidAssetComparisonIncomplete() ) {
			return;
		}
		$executionNew = $this->assetSetDifference(
			$action->getAssetComparisonIncomplete(),
			$incompleteBefore
		);
		if ( $this->isAssetSetEmpty( $executionNew ) ) {
			return;
		}

		$scan = self::con()->db_con->scans->getQuerySelector()->byId( $item->scan_id );
		if ( empty( $scan ) || (int)$scan->id !== $item->scan_id ) {
			throw new \RuntimeException( 'Scan row reload failed before asset comparison marker persistence.' );
		}
		$persistedMeta = $scan->meta;
		if ( !\is_array( $persistedMeta ) ) {
			throw new \RuntimeException( 'Scan metadata reload failed before asset comparison marker persistence.' );
		}

		$persistedAction = ( new Scans\Afs\ScanActionVO() )->applyFromArray( \array_merge(
			$persistedMeta,
			[
				'scan'       => $item->scan,
				'scope_type' => $item->scope_type,
				'scope_key'  => $item->scope_key,
			]
		) );
		if ( !$persistedAction->hasValidAssetComparisonIncomplete() ) {
			throw new \RuntimeException( 'Persisted asset comparison incomplete metadata is malformed.' );
		}

		$remaining = $this->assetSetDifference(
			$executionNew,
			$persistedAction->getAssetComparisonIncomplete()
		);
		if ( !$this->isAssetSetEmpty( $remaining ) ) {
			foreach ( [ 'plugin', 'theme' ] as $assetType ) {
				foreach ( $remaining[ $assetType ] as $assetKey ) {
					$persistedAction->markAssetComparisonIncomplete( $assetType, $assetKey );
				}
			}
			$expectedMeta = $persistedMeta;
			$expectedMeta[ 'asset_comparison_incomplete' ] = $persistedAction->getAssetComparisonIncomplete();
			$scan->meta = $expectedMeta;
			$rawMeta = $scan->getRawData()[ 'meta' ] ?? null;
			if ( !\is_string( $rawMeta )
				 || !self::con()->db_con->scans->getQueryUpdater()->updateById( $item->scan_id, [ 'meta' => $rawMeta ] ) ) {
				throw new \RuntimeException( 'Asset comparison incomplete marker update failed.' );
			}

			$scan = self::con()->db_con->scans->getQuerySelector()->byId( $item->scan_id );
			if ( empty( $scan ) || !\is_array( $scan->meta ) || $scan->meta !== $expectedMeta ) {
				throw new \RuntimeException( 'Asset comparison incomplete marker verification failed.' );
			}
			$persistedMeta = $scan->meta;
		}

		$item->meta = $persistedMeta;
		foreach ( [ 'plugin', 'theme' ] as $assetType ) {
			foreach ( $remaining[ $assetType ] as $assetKey ) {
				try {
					$enqueued = self::con()->comps->asset_coordinator->enqueueAsset( $assetType, $assetKey );
					if ( !$enqueued ) {
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
