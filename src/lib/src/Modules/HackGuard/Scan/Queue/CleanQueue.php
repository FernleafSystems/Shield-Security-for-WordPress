<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\{
	ScanItems\Ops as ScanItemsDB,
	Scans\Ops as ScansDB
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
			sprintf( "UPDATE `%s` SET `started_at`=0 WHERE `started_at` > 0 AND `started_at` < %s",
				self::con()->db_con->dbhScanItems()->getTableSchema()->table,
				Services::Request()->carbon()->subMinutes( 2 )->timestamp
			)
		);
	}

	private function deleteStaleScans() {
		$this->deleteStaleScansForTime();
		$this->deleteScansWithNoScanItems();
	}

	private function deleteStaleResultItems() {
		$resultItemIds = self::con()
			->db_con
			->dbhScanResults()
			->getQuerySelector()
			->getDistinctForColumn( 'resultitem_ref' );
		if ( !empty( $resultItemIds ) ) {
			$dbhResultsItems = self::con()->db_con->dbhResultItems();
			// 1. Get IDs for all scan items
			Services::WpDb()->doSql(
				sprintf( "DELETE FROM `%s` WHERE `id` NOT IN (%s)",
					$dbhResultsItems->getTableSchema()->table,
					\implode( ',', $resultItemIds )
				)
			);
		}
	}

	/**
	 * Stale: Scan has been ready for 20 minutes
	 */
	private function deleteStaleScansForTime() {
		/** @var ScansDB\Delete $deleter */
		$deleter = self::con()->db_con->dbhScans()->getQueryDeleter();

		// Scan created but hasn't been set to ready within 10 minutes.
		$deleter->filterByNotReady()
				->addWhereOlderThan( Services::Request()->carbon()->subMinutes( 5 )->timestamp )
				->query();

		// Scan set to ready for longer than 9 minutes but never finished.
		$deleter->reset()
				->filterByNotFinished()
				->filterByReady()
				->addWhereOlderThan(
					Services::Request()->carbon()->subMinutes( 9 )->timestamp,
					'ready_at'
				)->query();
	}

	/**
	 * Scan set to ready but no scan items available.
	 */
	private function deleteScansWithNoScanItems() {
		$dbCon = self::con()->db_con;
		/** @var ScansDB\Select $selector */
		$selector = $dbCon->dbhScans()->getQuerySelector();
		/** @var ScansDB\Record[] $scans */
		$scans = $selector->filterByReady()
						  ->filterByNotFinished()
						  ->queryWithResult();
		foreach ( $scans as $scan ) {
			/** @var ScanItemsDB\Select $selectorSI */
			$selectorSI = $dbCon->dbhScanItems()->getQuerySelector();
			if ( $selectorSI->filterByScan( $scan->id )->count() === 0 ) {
				$dbCon->dbhScans()
					  ->getQueryDeleter()
					  ->deleteById( $scan->id );
			}
		}
	}
}