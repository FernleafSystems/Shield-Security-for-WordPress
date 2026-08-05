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
				$this->enqueueIncompleteAssets( $runState->persistAssetComparisonIncomplete(
					$item,
					$action,
					$scan[ 'asset_comparison_incomplete_before' ]
				) );
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
	 * @param array{plugin:list<string>,theme:list<string>} $assets
	 */
	private function enqueueIncompleteAssets( array $assets ) :void {
		foreach ( [ 'plugin', 'theme' ] as $assetType ) {
			foreach ( $assets[ $assetType ] as $assetKey ) {
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
