<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	ScanItems\Ops as ScanItemsDB,
	Scans\Ops as ScansDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanQueue {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function run() {
		$this->resetStaleScanItems();
		$this->deleteStaleScans();
		$this->deleteStaleResultItems();
	}

	private function resetStaleScanItems() {
		Services::WpDb()->doSql(
			sprintf( "UPDATE `%s` SET `started_at`=0 WHERE `finished_at`=0 AND `started_at` > 0 AND `started_at` < %s",
				self::con()->db_con->scan_items->getTable(),
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
			->scan_results
			->getQuerySelector()
			->getDistinctForColumn( 'resultitem_ref' );
		if ( !empty( $resultItemIds ) ) {
			// 1. Get IDs for all scan items
			Services::WpDb()->doSql(
				sprintf( "DELETE FROM `%s` WHERE `id` NOT IN (%s)",
					self::con()->db_con->scan_result_items->getTable(),
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
		$deleter = self::con()->db_con->scans->getQueryDeleter();

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
		$selector = $dbCon->scans->getQuerySelector();
		/** @var ScansDB\Record[] $scans */
		$scans = $selector->filterByReady()
						  ->filterByNotFinished()
						  ->queryWithResult();
		foreach ( $scans as $scan ) {
			/** @var ScanItemsDB\Select $selectorSI */
			$selectorSI = $dbCon->scan_items->getQuerySelector();
			if ( $selectorSI->filterByScan( $scan->id )->count() === 0 ) {
				$dbCon->scans->getQueryDeleter()->deleteById( $scan->id );
			}
		}
	}
}