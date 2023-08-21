<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\SetScanCompleted;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Store;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanActionFromSlug;
use FernleafSystems\Wordpress\Services\Services;

class ProcessQueueItem {

	use Shield\Modules\HackGuard\ModConsumer;

	public function run( QueueItemVO $item ) {
		$this->mod()
			 ->getDbH_ScanItems()
			 ->getQueryUpdater()
			 ->updateById( $item->qitem_id, [
				 'started_at' => Services::Request()->ts()
			 ] );

		try {
			$results = $this->runScanOnItem( $item );

			( new Store() )->store( $item, $results );

			$this->mod()
				 ->getDbH_ScanItems()
				 ->getQueryUpdater()
				 ->updateById( $item->qitem_id, [
					 'finished_at' => Services::Request()->ts()
				 ] );

			( new SetScanCompleted() )->run( $item->scan );
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
	}

	/**
	 * @throws \Exception
	 */
	private function runScanOnItem( QueueItemVO $item ) :array {
		$action = ScanActionFromSlug::GetAction( $item->scan )->applyFromArray( $item->meta );
		$action->items = $item->items;

		$this->getScanner( $action )
			 ->setScanActionVO( $action )
			 ->run();

		if ( $action->usleep > 0 ) {
			usleep( $action->usleep );
		}

		return \is_array( $action->results ) ? $action->results : [];
	}

	/**
	 * @param Shield\Scans\Base\BaseScanActionVO $action
	 * @return Shield\Scans\Base\BaseScan
	 */
	private function getScanner( $action ) {
		$class = $action->getScanNamespace().'Scan';
		/** @var Shield\Scans\Base\BaseScan $o */
		$o = new $class();
		return $o->setScanController( $this->mod()->getScansCon()->getScanCon( $action->scan ) )
				 ->setScanActionVO( $action );
	}
}
