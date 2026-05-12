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
		$now = Services::Request()->ts();
		self::con()->db_con->scan_items->getQueryUpdater()->updateById( $item->qitem_id, [
			'started_at' => $now
		] );
		( new RunState() )->markRunning( $item );

		try {
			$results = $this->runScanOnItem( $item );

			( new Store() )->store( $item, $results );

			self::con()
				->db_con
				->scan_items
				->getQueryUpdater()
				->updateById( $item->qitem_id, [
					'finished_at' => Services::Request()->ts()
				] );

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
		}
	}

	/**
	 * @throws \Exception
	 */
	private function runScanOnItem( QueueItemVO $item ) :array {
		$action = ScanActionFromSlug::GetAction( $item->scan )->applyFromArray( \array_merge(
			$item->meta,
			[ 'scan' => $item->scan ]
		) );
		$action->items = $item->items;

		$this->getScanner( $action )
			 ->setScanActionVO( $action )
			 ->run();

		if ( $action->usleep > 0 ) {
			\usleep( $action->usleep );
		}

		return \is_array( $action->results ) ? $action->results : [];
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
