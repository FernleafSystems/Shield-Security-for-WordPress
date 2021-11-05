<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\SetScanCompleted;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Store;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanActionFromSlug;
use FernleafSystems\Wordpress\Services\Services;

class ProcessQueueItem {

	use Shield\Modules\ModConsumer;

	public function run( QueueItemVO $item ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$mod->getDbH_ScanItems()
			->getQueryUpdater()
			->updateById( $item->qitem_id, [
				'started_at' => Services::Request()->ts()
			] );

		try {
			$results = $this->runScanOnItem( $item );

			( new Store() )
				->setMod( $this->getMod() )
				->store( $item, $results );

			$mod->getDbH_ScanItems()
				->getQueryUpdater()
				->updateById( $item->qitem_id, [
					'finished_at' => Services::Request()->ts()
				] );

			( new SetScanCompleted() )
				->setMod( $this->getMod() )
				->run();
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
	}

	/**
	 * @throws \Exception
	 */
	private function runScanOnItem( QueueItemVO $item ) :array {
		$action = ScanActionFromSlug::GetAction( $item->scan )
									->applyFromArray( $item->meta );
		$action->items = $item->items;

		$this->getScanner( $action )
			 ->setScanActionVO( $action )
			 ->setMod( $this->getMod() )
			 ->run();

		if ( $action->usleep > 0 ) {
			usleep( $action->usleep );
		}

		return is_array( $action->results ) ? $action->results : [];
	}

	/**
	 * @param Shield\Scans\Base\BaseScanActionVO $action
	 * @return Shield\Scans\Base\BaseScan
	 */
	private function getScanner( $action ) {
		/** @var Shield\Modules\HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$class = $action->getScanNamespace().'Scan';
		/** @var Shield\Scans\Base\BaseScan $o */
		$o = new $class();
		return $o->setScanController( $mod->getScanCon( $action->scan ) )
				 ->setMod( $mod )
				 ->setScanActionVO( $action );
	}
}
