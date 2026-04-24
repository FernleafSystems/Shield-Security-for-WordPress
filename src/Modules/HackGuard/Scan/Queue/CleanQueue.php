<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	ScanItems\Ops as ScanItemsDB,
	Scans\Ops as ScansDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\SetScanCompleted;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanQueue {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function run() {
		$this->resetStaleScanItems();
		$this->resolveStaleScans();
	}

	private function resetStaleScanItems() {
		Services::WpDb()->doSql(
			sprintf( "UPDATE `%s` SET `started_at`=0 WHERE `finished_at`=0 AND `started_at` > 0 AND `started_at` < %s",
				self::con()->db_con->scan_items->getTable(),
				Services::Request()->carbon()->subMinutes( 2 )->timestamp
			)
		);
	}

	private function resolveStaleScans() {
		$this->resolveStaleScansForTime();
		$this->failScansWithNoScanItems();
	}

	/**
	 * Stale scans are resolved according to their current lifecycle state.
	 */
	private function resolveStaleScansForTime() {
		$selector = self::con()->db_con->scans->getQuerySelector();

		foreach ( \array_unique( $this->idsFromRecords( $selector->reset()
			->filterByStatus( 'building' )
			->filterByNotFinished()
			->addWhereOlderThan(
				Services::Request()->carbon()->subMinutes( 9 )->timestamp,
				'last_process_at'
			)->queryWithResult() ) ) as $scanID ) {
			if ( $scanID > 0 ) {
				$this->markTimedOut( $scanID );
			}
		}

		$staleReadyIDs = [];
		foreach ( [
			$selector->reset()
				->filterByStatus( 'built' )
				->filterByNotFinished()
				->addWhereOlderThan(
					Services::Request()->carbon()->subMinutes( 9 )->timestamp,
					'ready_at'
				)->queryWithResult(),
			$selector->reset()
				->filterByStatus( 'built' )
				->filterByNotFinished()
				->addWhereOlderThan(
					Services::Request()->carbon()->subMinutes( 9 )->timestamp,
					'last_process_at'
				)->queryWithResult(),
			$selector->reset()
				->filterByNotFinished()
				->filterByStatus( 'running' )
				->addWhereOlderThan(
					Services::Request()->carbon()->subMinutes( 9 )->timestamp,
					'last_process_at'
				)->queryWithResult(),
		] as $matchingIDs ) {
			$staleReadyIDs = \array_merge( $staleReadyIDs, $this->idsFromRecords( \is_array( $matchingIDs ) ? $matchingIDs : [] ) );
		}

		foreach ( \array_unique( \array_map( '\intval', $staleReadyIDs ) ) as $scanID ) {
			if ( $scanID > 0 ) {
				if ( !$this->hasAnyScanItems( $scanID ) ) {
					$this->markTimedOut( $scanID );
				}
				elseif ( !$this->hasUnfinishedScanItems( $scanID ) ) {
					( new SetScanCompleted() )->run( $scanID );
				}
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

	private function hasAnyScanItems( int $scanID ) :bool {
		/** @var ScanItemsDB\Select $selectorSI */
		$selectorSI = self::con()->db_con->scan_items->getQuerySelector();
		return $selectorSI->filterByScan( $scanID )->count() > 0;
	}

	private function hasUnfinishedScanItems( int $scanID ) :bool {
		/** @var ScanItemsDB\Select $selectorSI */
		$selectorSI = self::con()->db_con->scan_items->getQuerySelector();
		return $selectorSI->filterByScan( $scanID )->filterByNotFinished()->count() > 0;
	}

	private function markTimedOut( int $scanID ) :void {
		( new RunState() )->markFailed( $scanID, 'Scan timed out before it could finish.' );
	}

	private function idsFromRecords( array $records ) :array {
		return \array_map(
			static fn( $record ) :int => (int)( $record->id ?? 0 ),
			$records
		);
	}
}
