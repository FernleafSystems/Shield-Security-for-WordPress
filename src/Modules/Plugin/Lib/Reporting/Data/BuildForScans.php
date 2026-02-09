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
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\{
	Counts,
	Retrieve\RetrieveCount
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScansController;
use FernleafSystems\Wordpress\Services\Services;

class BuildForScans extends BuildBase {

	private const ITEMS_CAP = 20;

	public function build() :array {
		return [
			'scan_results' => $this->buildMergedResults(),
			'scan_repairs' => $this->buildForRepairs(),
		];
	}

	private function buildMergedResults() :array {
		$scansCon = self::con()->comps->scans;
		$cActive = new Counts( RetrieveCount::CONTEXT_ACTIVE_PROBLEMS );
		$cNew = new Counts( RetrieveCount::CONTEXT_NOT_YET_NOTIFIED );

		$afsItems = [];
		if ( !$scansCon->AFS()->isRestricted() ) {
			$afsItems = $scansCon->AFS()->getResultsForDisplay()->getAllItems();
		}

		$scanCounts = [
			'file_locker'             => $this->buildFileLockerEntry(),
			Wpv::SCAN_SLUG            => $this->buildWpvEntry( $scansCon, $cActive, $cNew ),
			Apc::SCAN_SLUG            => $this->buildApcEntry( $scansCon, $cActive, $cNew ),
			Afs::SCAN_SLUG.'_malware' => $this->buildAfsEntry(
				$afsItems, 'is_mal', __( 'Potential Malware', 'wp-simple-firewall' ),
				$cActive->countMalware(), $cNew->countMalware(),
				$scansCon->AFS()->isEnabledMalwareScanPHP()
			),
			Afs::SCAN_SLUG.'_wp'      => $this->buildAfsEntry(
				$afsItems, 'is_in_core', __( 'WordPress Files', 'wp-simple-firewall' ),
				$cActive->countWPFiles(), $cNew->countWPFiles(),
				$scansCon->AFS()->isScanEnabledWpCore()
			),
			Afs::SCAN_SLUG.'_plugin'  => $this->buildAfsEntry(
				$afsItems, 'is_in_plugin', __( 'Plugin Files', 'wp-simple-firewall' ),
				$cActive->countPluginFiles(), $cNew->countPluginFiles(),
				$scansCon->AFS()->isScanEnabledPlugins()
			),
			Afs::SCAN_SLUG.'_theme'   => $this->buildAfsEntry(
				$afsItems, 'is_in_theme', __( 'Theme Files', 'wp-simple-firewall' ),
				$cActive->countThemeFiles(), $cNew->countThemeFiles(),
				$scansCon->AFS()->isScanEnabledThemes()
			),
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

		\usort( $scanCounts, function ( $a, $b ) {
			$countA = $a[ 'count' ];
			$countB = $b[ 'count' ];
			return $countA == $countB ? 0 : ( ( $countA > $countB ) ? -1 : 1 );
		} );

		return $scanCounts;
	}

	private function buildFileLockerEntry() :array {
		$flEnabled = self::con()->comps->file_locker->isEnabled();
		$allProblems = $flEnabled ? ( new LoadFileLocks() )->withProblems() : [];
		$newProblems = $flEnabled ? ( new LoadFileLocks() )->withProblemsNotNotified() : [];
		$newIds = \array_map( fn( $lock ) => $lock->id, $newProblems );

		$items = [];
		foreach ( $allProblems as $lock ) {
			$items[] = [
				'label'  => \str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $lock->path ) ),
				'is_new' => \in_array( $lock->id, $newIds ),
			];
		}

		\usort( $items, fn( $a, $b ) => (int)$b[ 'is_new' ] - (int)$a[ 'is_new' ] );
		$itemsTotal = \count( $items );

		return [
			'name'        => 'File Locker',
			'count'       => \count( $allProblems ),
			'new_count'   => \count( $newProblems ),
			'available'   => $flEnabled,
			'items'       => \array_slice( $items, 0, self::ITEMS_CAP ),
			'items_total' => $itemsTotal,
		];
	}

	private function buildWpvEntry( ScansController $scansCon, Counts $cActive, Counts $cNew ) :array {
		$items = [];
		if ( $scansCon->WPV()->isEnabled() && !$scansCon->WPV()->isRestricted() ) {
			$wpvResults = $scansCon->WPV()->getResultsForDisplay();
			$slugs = $wpvResults->getUniqueSlugs();
			foreach ( $slugs as $slug ) {
				$slugItems = $wpvResults->getItemsForSlug( $slug );
				$isNew = false;
				foreach ( $slugItems as $si ) {
					if ( $si->VO->notified_at === 0 ) {
						$isNew = true;
						break;
					}
				}
				if ( \strpos( $slug, '/' ) !== false ) {
					$asset = Services::WpPlugins()->getPluginAsVo( $slug );
					$label = !empty( $asset ) ? $asset->Title.' v'.$asset->Version : $slug;
				}
				else {
					$asset = Services::WpThemes()->getThemeAsVo( $slug );
					$label = !empty( $asset ) ? $asset->Name.' v'.$asset->Version : $slug;
				}
				$items[] = [ 'label' => $label, 'is_new' => $isNew ];
			}
		}

		\usort( $items, fn( $a, $b ) => (int)$b[ 'is_new' ] - (int)$a[ 'is_new' ] );
		$itemsTotal = \count( $items );

		return [
			'name'        => $scansCon->WPV()->getScanName(),
			'count'       => $cActive->countVulnerableAssets(),
			'new_count'   => $cNew->countVulnerableAssets(),
			'available'   => $scansCon->WPV()->isEnabled(),
			'items'       => \array_slice( $items, 0, self::ITEMS_CAP ),
			'items_total' => $itemsTotal,
		];
	}

	private function buildApcEntry( ScansController $scansCon, Counts $cActive, Counts $cNew ) :array {
		$items = [];
		if ( !$scansCon->APC()->isRestricted() ) {
			$apcItems = $scansCon->APC()->getResultsForDisplay()->getAllItems();
			foreach ( $apcItems as $item ) {
				$slug = $item->VO->item_id;
				$isNew = $item->VO->notified_at === 0;
				if ( \strpos( $slug, '/' ) !== false ) {
					$asset = Services::WpPlugins()->getPluginAsVo( $slug );
					$label = !empty( $asset ) ? $asset->Title : $slug;
				}
				else {
					$asset = Services::WpThemes()->getThemeAsVo( $slug );
					$label = !empty( $asset ) ? $asset->Name : $slug;
				}
				$items[] = [ 'label' => $label, 'is_new' => $isNew ];
			}
		}

		\usort( $items, fn( $a, $b ) => (int)$b[ 'is_new' ] - (int)$a[ 'is_new' ] );
		$itemsTotal = \count( $items );

		return [
			'name'        => $scansCon->APC()->getScanName(),
			'count'       => $cActive->countAbandoned(),
			'new_count'   => $cNew->countAbandoned(),
			'available'   => $scansCon->APC()->isEnabled(),
			'items'       => \array_slice( $items, 0, self::ITEMS_CAP ),
			'items_total' => $itemsTotal,
		];
	}

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem[] $allAfsItems
	 */
	private function buildAfsEntry( array $allAfsItems, string $filterField, string $name, int $activeCount, int $newCount, bool $available ) :array {
		$items = [];
		foreach ( $allAfsItems as $item ) {
			if ( $item->{$filterField} ) {
				$items[] = [
					'label'  => $item->path_fragment,
					'is_new' => $item->VO->notified_at === 0,
				];
			}
		}

		\usort( $items, fn( $a, $b ) => (int)$b[ 'is_new' ] - (int)$a[ 'is_new' ] );
		$itemsTotal = \count( $items );

		return [
			'name'        => $name,
			'count'       => $activeCount,
			'new_count'   => $newCount,
			'available'   => $available,
			'items'       => \array_slice( $items, 0, self::ITEMS_CAP ),
			'items_total' => $itemsTotal,
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
					fn( $log ) => $log->id,
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
						fn( $meta ) => /** @var MetaDB\Record $meta */ \str_replace( ABSPATH, '', $meta->meta_value ),
						$metaSelect->filterByLogRefs( $logIDs )
								   ->filterByMetaKey( 'path_full' )
								   ->queryWithResult()
					) ),
				];
			}
		}

		return $repairs;
	}
}
