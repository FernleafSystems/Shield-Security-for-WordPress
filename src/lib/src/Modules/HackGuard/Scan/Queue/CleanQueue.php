<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\{
	ScanItems as ScanItemsDB,
	Scans as ScansDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanQueue {

	use ExecOnce;
	use ModConsumer;

	protected function run() {
		$this->resetStaleScanItems();
		$this->deleteStaleScans();
		$this->deleteStaleResultItems();
	}

	private function resetStaleScanItems() {
		Services::WpDb()->doSql(
			sprintf( "UPDATE `%s` 
				SET `started_at`=0
				WHERE `started_at` > 0 AND `started_at` < %s",
				$this->mod()->getDbH_ScanItems()->getTableSchema()->table,
				Services::Request()->carbon()->subMinutes( 5 )->timestamp
			)
		);
	}

	private function deleteStaleScans() {
		$this->deleteStaleScansForTime();
		$this->deleteScansWithNoScanItems();
	}

	private function deleteStaleResultItems() {
		$resultItemIds = $this->mod()
							  ->getDbH_ScanResults()
							  ->getQuerySelector()
							  ->getDistinctForColumn( 'resultitem_ref' );
		if ( !empty( $resultItemIds ) ) {
			$dbhResultsItems = $this->mod()->getDbH_ResultItems();
			// 1. Get IDs for all scan items
			Services::WpDb()->doSql(
				sprintf( "DELETE FROM `%s` WHERE `id` NOT IN (%s)",
					$dbhResultsItems->getTableSchema()->table,
					implode( ',', $resultItemIds )
				)
			);
		}
	}

	/**
	 * Stale: Scan has been ready for 20 minutes
	 */
	private function deleteStaleScansForTime() {
		/** @var ScansDB\Ops\Delete $deleter */
		$deleter = $this->mod()->getDbH_Scans()->getQueryDeleter();

		// Scan created but hasn't been set to ready within 10 minutes.
		$deleter->filterByNotReady()
				->addWhereOlderThan(
					Services::Request()->carbon()->subMinutes( 5 )->timestamp
				)->query();

		// Scan set to ready for longer than 20 minutes but never finished.
		$deleter->reset()
				->filterByNotFinished()
				->filterByReady()
				->addWhereOlderThan(
					Services::Request()->carbon()->subMinutes( 10 )->timestamp,
					'ready_at'
				)->query();
	}

	/**
	 * Scan set to ready but no scan items available.
	 */
	private function deleteScansWithNoScanItems() {
		$mod = $this->mod();
		/** @var ScansDB\Ops\Select $selector */
		$selector = $mod->getDbH_Scans()->getQuerySelector();
		/** @var ScansDB\Ops\Record[] $scans */
		$scans = $selector->filterByReady()
						  ->filterByNotFinished()
						  ->queryWithResult();
		foreach ( $scans as $scan ) {
			/** @var ScanItemsDB\Ops\Select $selectorSI */
			$selectorSI = $mod->getDbH_ScanItems()->getQuerySelector();
			if ( $selectorSI->filterByScan( $scan->id )->count() === 0 ) {
				$mod->getDbH_Scans()
					->getQueryDeleter()
					->deleteById( $scan->id );
			}
		}
	}
}