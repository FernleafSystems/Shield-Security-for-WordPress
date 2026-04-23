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
		$this->failStaleScans();
	}

	private function resetStaleScanItems() {
		Services::WpDb()->doSql(
			sprintf( "UPDATE `%s` SET `started_at`=0 WHERE `finished_at`=0 AND `started_at` > 0 AND `started_at` < %s",
				self::con()->db_con->scan_items->getTable(),
				Services::Request()->carbon()->subMinutes( 2 )->timestamp
			)
		);
	}

	private function failStaleScans() {
		$this->failStaleScansForTime();
		$this->failScansWithNoScanItems();
	}

	/**
	 * Stale scans are failed according to their current lifecycle state.
	 */
	private function failStaleScansForTime() {
		$selector = self::con()->db_con->scans->getQuerySelector();
		$scanIDs = [];

		if ( $selector->reset()
					 ->filterByNotFinished()
					 ->addWhereIn( 'status', [ 'building', 'built', 'running' ] )
					 ->count() === 0 ) {
			$queuedIDs = $selector->reset()
				->filterByStatus( 'queued' )
				->filterByNotFinished()
				->addWhereOlderThan( Services::Request()->carbon()->subMinutes( 5 )->timestamp )
				->getDistinctForColumn( 'id' );
			$scanIDs = \array_merge( $scanIDs, \is_array( $queuedIDs ) ? $queuedIDs : [] );
		}

		foreach ( [
			$selector->reset()
				->filterByStatus( 'building' )
				->filterByNotFinished()
				->addWhereOlderThan(
					Services::Request()->carbon()->subMinutes( 9 )->timestamp,
					'last_process_at'
				)->getDistinctForColumn( 'id' ),
			$selector->reset()
				->filterByStatus( 'built' )
				->filterByNotFinished()
				->addWhereOlderThan(
					Services::Request()->carbon()->subMinutes( 9 )->timestamp,
					'ready_at'
				)->getDistinctForColumn( 'id' ),
			$selector->reset()
				->filterByStatus( 'built' )
				->filterByNotFinished()
				->addWhereOlderThan(
					Services::Request()->carbon()->subMinutes( 9 )->timestamp,
					'last_process_at'
				)->getDistinctForColumn( 'id' ),
			$selector->reset()
				->filterByNotFinished()
				->filterByStatus( 'running' )
				->addWhereOlderThan(
					Services::Request()->carbon()->subMinutes( 9 )->timestamp,
					'last_process_at'
				)->getDistinctForColumn( 'id' ),
		] as $matchingIDs ) {
			$scanIDs = \array_merge( $scanIDs, \is_array( $matchingIDs ) ? $matchingIDs : [] );
		}

		$runState = new RunState();
		foreach ( \array_unique( \array_map( '\intval', $scanIDs ) ) as $scanID ) {
			if ( $scanID > 0 ) {
				$runState->markFailed( $scanID, 'Scan timed out before it could finish.' );
			}
		}
	}

	/**
	 * Scan set to ready but no scan items available.
	 */
	private function failScansWithNoScanItems() {
		$dbCon = self::con()->db_con;
		/** @var ScansDB\Select $selector */
		$selector = $dbCon->scans->getQuerySelector();
		/** @var ScansDB\Record[] $scans */
		$scans = $selector->addWhereIn( 'status', [ 'built', 'running' ] )
						  ->filterByNotFinished()
						  ->filterByReady()
						  ->queryWithResult();
		foreach ( $scans as $scan ) {
			/** @var ScanItemsDB\Select $selectorSI */
			$selectorSI = $dbCon->scan_items->getQuerySelector();
			if ( $selectorSI->filterByScan( $scan->id )->count() === 0 ) {
				( new RunState() )->markFailed( (int)$scan->id, 'Scan queue was ready but no queue items were available.' );
			}
		}
	}
}
