<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\{
	Scans as ScansDB,
	ScanItems as ScanItemsDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;

class CleanQueue extends ExecOnceModConsumer {

	protected function run() {
		$this->resetStaleScanItems();
		$this->deleteStaleScans();
	}

	private function resetStaleScanItems() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_ScanItems();
		Services::WpDb()->doSql(
			sprintf( "UPDATE `%s` 
				SET `started_at`=0
				WHERE `started_at` > 0 AND `started_at` < %s",
				$dbh->getTableSchema()->table,
				Services::Request()->carbon()->subMinutes( 5 )->timestamp
			)
		);
	}

	private function deleteStaleScans() {
		$this->deleteStaleScansForTime();
		$this->deleteScansWithNoScanItems();
	}

	/**
	 * Stale: Scan has been ready for 20 minutes
	 */
	private function deleteStaleScansForTime() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var ScansDB\Ops\Delete $deleter */
		$deleter = $mod->getDbH_Scans()->getQueryDeleter();

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
		/** @var ModCon $mod */
		$mod = $this->getMod();
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