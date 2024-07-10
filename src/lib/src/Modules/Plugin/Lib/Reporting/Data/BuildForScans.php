<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	ActivityLogs\Ops as LogsDB,
	ActivityLogsMeta\Ops as MetaDB,
	Event\Ops as EventsDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\{
	Afs,
	Apc,
	Wpv
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Counts;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;

class BuildForScans extends BuildBase {

	public function build() :array {
		return [
			'scan_results_new'     => $this->buildForResultsContext( RetrieveCount::CONTEXT_NOT_YET_NOTIFIED ),
			'scan_results_current' => $this->buildForResultsContext( RetrieveCount::CONTEXT_ACTIVE_PROBLEMS ),
			'scan_repairs'         => $this->buildForRepairs(),
		];
	}

	private function buildForRepairs() :array {
		/** @var EventsDB\Select $selectorEvents */
		$selectorEvents = self::con()->db_con->events->getQuerySelector();

		$repairs = [];
		foreach ( [ 'scan_item_repair_success', 'scan_item_delete_success', /*'scan_item_repair_fail'*/ ] as $event ) {
			$eventTotal = $selectorEvents
				->filterByBoundary( $this->report->start_at, $this->report->end_at )
				->sumEvent( $event );

			if ( $eventTotal > 0 ) {
				/** @var LogsDB\Select $logSelect */
				$logSelect = self::con()->db_con->activity_logs->getQuerySelector();
				/** @var LogsDB\Record[] $logs */
				$logIDs = \array_map(
					function ( $log ) {
						return $log->id;
					},
					$logSelect->filterByEvent( $event )
							  ->filterByBoundary( $this->report->start_at, $this->report->end_at )
							  ->setLimit( $eventTotal )
							  ->queryWithResult()
				);

				/** @var MetaDB\Select $metaSelect */
				$metaSelect = self::con()->db_con->activity_logs_meta->getQuerySelector();

				$repairs[ $event ] = [
					'name'    => self::con()->comps->events->getEventName( $event ),
					'count'   => $eventTotal,
					'repairs' => \array_unique( \array_map(
						function ( $meta ) {
							/** @var MetaDB\Record $meta */
							return \str_replace( ABSPATH, '', $meta->meta_value );
						},
						$metaSelect->filterByLogRefs( $logIDs )
								   ->filterByMetaKey( 'path_full' )
								   ->queryWithResult()
					) ),
				];
			}
		}

		return $repairs;
	}

	private function buildForResultsContext( int $context ) :array {
		$scansCon = self::con()->comps->scans;
		$c = new Counts( $context );
		$scanCounts = [
			'file_locker'             => [
				'name'      => 'File Locker',
				'count'     => $context === RetrieveCount::CONTEXT_ACTIVE_PROBLEMS ?
					\count( ( new LoadFileLocks() )->withProblems() )
					: \count( ( new LoadFileLocks() )->withProblemsNotNotified() ),
				'available' => self::con()->comps->file_locker->isEnabled(),
			],
			Wpv::SCAN_SLUG            => [
				'name'      => $scansCon->WPV()->getScanName(),
				'count'     => $c->countVulnerableAssets(),
				'available' => $scansCon->WPV()->isEnabled(),
			],
			Apc::SCAN_SLUG            => [
				'name'      => $scansCon->APC()->getScanName(),
				'count'     => $c->countAbandoned(),
				'available' => $scansCon->APC()->isEnabled(),
			],
			Afs::SCAN_SLUG.'_malware' => [
				'name'      => __( 'Potential Malware' ),
				'count'     => $c->countMalware(),
				'available' => $scansCon->AFS()->isEnabledMalwareScanPHP(),
			],
			Afs::SCAN_SLUG.'_wp'      => [
				'name'      => __( 'WordPress Files' ),
				'count'     => $c->countWPFiles(),
				'available' => $scansCon->AFS()->isScanEnabledWpCore(),
			],
			Afs::SCAN_SLUG.'_plugin'  => [
				'name'      => __( 'Plugin Files' ),
				'count'     => $c->countPluginFiles(),
				'available' => $scansCon->AFS()->isScanEnabledPlugins(),
			],
			Afs::SCAN_SLUG.'_theme'   => [
				'name'      => __( 'Theme Files' ),
				'count'     => $c->countThemeFiles(),
				'available' => $scansCon->AFS()->isScanEnabledThemes(),
			],
		];

		foreach ( $scanCounts as $slug => &$scanCount ) {
			if ( $scanCount[ 'available' ] ) {
				$scanCount[ 'slug' ] = $slug;
				$scanCount[ 'has_count' ] = $scanCount[ 'count' ] > 0;
				$scanCount[ 'colour' ] = $scanCount[ 'count' ] > 0 ? 'warning' : 'success';
			}
			else {
				unset( $scanCounts[ $slug ] );
			}
		}

		// Ensure items with higher counts are ordered first.
		\usort( $scanCounts, function ( $a, $b ) {
			$countA = $a[ 'count' ];
			$countB = $b[ 'count' ];
			return $countA == $countB ? 0 : ( ( $countA > $countB ) ? -1 : 1 );
		} );

		return $scanCounts;
	}
}