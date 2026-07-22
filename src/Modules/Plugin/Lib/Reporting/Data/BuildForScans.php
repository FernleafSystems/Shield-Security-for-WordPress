<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	ActivityLogs\Ops as LogsDB,
	ActivityLogsMeta\Ops as MetaDB,
	Event\Ops as EventsDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\{
	Afs,
	Apc,
	Wpv
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\{
	Counts,
	Retrieve\RetrieveCount,
	Retrieve\RetrieveItems
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScansController;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\{
	Processing\MalwareStatus,
	ResultItem
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type ScanReportItem array{label:string,is_new:bool}
 * @phpstan-type ScanReportRowBase array{
 *   name:string,
 *   count:int,
 *   new_count:int,
 *   available:bool,
 *   items:list<ScanReportItem>,
 *   items_total:int,
 *   notification_target_ids:list<int>
 * }
 * @phpstan-type ScanReportRow array{
 *   name:string,
 *   count:int,
 *   new_count:int,
 *   available:bool,
 *   items:list<ScanReportItem>,
 *   items_total:int,
 *   notification_target_ids:list<int>,
 *   slug:string,
 *   has_count:bool,
 *   colour:'warning'|'success'
 * }
 */
class BuildForScans extends BuildBase {

	private const ITEMS_CAP = 20;

	public function build() :array {
		$data = [];
		foreach ( $this->requestedSections() as $section ) {
			switch ( $section ) {
				case 'scan_results':
					$data[ 'scan_results' ] = $this->buildMergedResults();
					break;
				case 'scan_repairs':
					$data[ 'scan_repairs' ] = $this->buildForRepairs();
					break;
			}
		}
		return $data;
	}

	/**
	 * @phpstan-return list<ScanReportRow>
	 */
	protected function buildMergedResults() :array {
		$scansCon = self::con()->comps->scans;
		$cActive = new Counts( RetrieveCount::CONTEXT_ACTIVE_PROBLEMS );
		$cNew = new Counts( RetrieveCount::CONTEXT_NOT_YET_NOTIFIED );

		$afsItems = [];
		if ( !$scansCon->AFS()->isRestricted() ) {
			foreach ( ( new RetrieveItems() )
				->setScanController( $scansCon->AFS() )
				->retrieveActiveProblems()
				->getAllItems() as $item ) {
				if ( !$item instanceof ResultItem ) {
					throw new \UnexpectedValueException( 'AFS result set contained an invalid item type.' );
				}
				$afsItems[] = $item;
			}
		}
		$afsMalwareItems = $afsItems;
		$afsAssetItems = $afsItems;
		if ( $this->report->type === Constants::REPORT_TYPE_ALERT && self::con()->caps->canScanMalwareMalai() ) {
			$now = Services::Request()->ts();
			$pendingIDs = [];
			foreach ( $afsItems as $item ) {
				if ( $this->shouldDeferPendingMalwareAlert( $item, $now ) ) {
					$pendingIDs[ (int)$item->VO->resultitem_id ] = true;
				}
			}
			$afsMalwareItems = $afsAssetItems = \array_values( \array_filter(
				$afsItems,
				fn( ResultItem $item ) :bool => !isset( $pendingIDs[ (int)$item->VO->resultitem_id ] )
			) );
		}

		$scanCounts = [
			'file_locker'             => $this->buildFileLockerEntry(),
			Wpv::SCAN_SLUG            => $this->buildWpvEntry( $scansCon, $cActive, $cNew ),
			Apc::SCAN_SLUG            => $this->buildApcEntry( $scansCon, $cActive, $cNew ),
			Afs::SCAN_SLUG.'_malware' => $this->buildAfsEntry(
				$afsMalwareItems, 'is_mal', __( 'Potential Malware', 'wp-simple-firewall' ),
				$scansCon->AFS()->isEnabledMalwareScanPHP()
			),
			Afs::SCAN_SLUG.'_wp'      => $this->buildAfsEntry(
				$afsAssetItems, 'is_in_core', __( 'WordPress Files', 'wp-simple-firewall' ),
				$scansCon->AFS()->isScanEnabledWpCore()
			),
			Afs::SCAN_SLUG.'_plugin'  => $this->buildAfsEntry(
				$afsAssetItems, 'is_in_plugin', __( 'Plugin Files', 'wp-simple-firewall' ),
				$scansCon->AFS()->isScanEnabledPlugins()
			),
			Afs::SCAN_SLUG.'_theme'   => $this->buildAfsEntry(
				$afsAssetItems, 'is_in_theme', __( 'Theme Files', 'wp-simple-firewall' ),
				$scansCon->AFS()->isScanEnabledThemes()
			),
		];

		$rows = [];
		foreach ( $scanCounts as $slug => $scanCount ) {
			if ( $scanCount[ 'available' ] ) {
				$rows[] = \array_merge( $scanCount, [
					'slug'      => $slug,
					'has_count' => $scanCount[ 'count' ] > 0,
					'colour'    => $scanCount[ 'count' ] > 0 ? 'warning' : 'success',
				] );
			}
		}

		\usort( $rows, function ( $a, $b ) {
			$countA = $a[ 'count' ];
			$countB = $b[ 'count' ];
			return $countA == $countB ? 0 : ( ( $countA > $countB ) ? -1 : 1 );
		} );

		return $rows;
	}

	/**
	 * @phpstan-return ScanReportRowBase
	 */
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
			'notification_target_ids' => [],
		];
	}

	/**
	 * @phpstan-return ScanReportRowBase
	 */
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
			'notification_target_ids' => $this->loadNotYetNotifiedResultItemIdsForMeta( Wpv::SCAN_SLUG, 'is_vulnerable' ),
		];
	}

	/**
	 * @phpstan-return ScanReportRowBase
	 */
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
			'notification_target_ids' => $this->loadNotYetNotifiedResultItemIdsForMeta( Apc::SCAN_SLUG, 'is_abandoned' ),
		];
	}

	/**
	 * @param list<ResultItem> $allAfsItems
	 * @phpstan-param 'is_mal'|'is_in_core'|'is_in_plugin'|'is_in_theme' $filterField
	 * @phpstan-return ScanReportRowBase
	 */
	private function buildAfsEntry( array $allAfsItems, string $filterField, string $name, bool $available ) :array {
		$items = [];
		$notificationTargetIDs = [];
		foreach ( $allAfsItems as $item ) {
			if ( $item->{$filterField} ) {
				$isNew = $item->VO->notified_at === 0;
				$items[] = [
					'label'  => $item->path_fragment,
					'is_new' => $isNew,
				];
				if ( $isNew ) {
					$notificationTargetIDs[] = (int)$item->VO->resultitem_id;
				}
			}
		}

		\usort( $items, fn( $a, $b ) => (int)$b[ 'is_new' ] - (int)$a[ 'is_new' ] );
		$itemsTotal = \count( $items );

		return [
			'name'        => $name,
			'count'       => $itemsTotal,
			'new_count'   => \count( $notificationTargetIDs ),
			'available'   => $available,
			'items'       => \array_slice( $items, 0, self::ITEMS_CAP ),
			'items_total' => $itemsTotal,
			'notification_target_ids' => $notificationTargetIDs,
		];
	}

	private function shouldDeferPendingMalwareAlert( ResultItem $item, int $now ) :bool {
		$createdAt = (int)$item->VO->created_at;
		if ( !$item->is_mal
			 || $createdAt < 1
			 || $createdAt > $now
			 || $createdAt <= $now - \HOUR_IN_SECONDS ) {
			return false;
		}
		$record = $item->getMalwareRecord();
		return $record === null || ( new MalwareStatus() )->isPending( $record->malai_status );
	}

	/**
	 * @return list<int>
	 */
	private function loadNotYetNotifiedResultItemIdsForMeta( string $scanSlug, string $metaKey ) :array {
		$dbCon = self::con()->db_con;
		$wheres = ( new \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\LatestScanResultWheresBuilder() )
			->forNotYetNotified( $scanSlug );
		$wheres[] = \sprintf( "`rim`.`meta_key`='%s'", \preg_replace( '#[^a-z0-9_]#i', '', $metaKey ) );
		$wheres[] = "`rim`.`meta_value`=1";
		$rows = Services::WpDb()->selectCustom( sprintf(
			"SELECT `ri`.`id`
				FROM `%s` AS `ri`
				INNER JOIN `%s` AS `rim` ON `rim`.`ri_ref`=`ri`.`id`
				WHERE %s;",
			$dbCon->scan_result_items->getTable(),
			$dbCon->scan_result_item_meta->getTable(),
			\implode( ' AND ', $wheres )
		) );

		return \array_values( \array_map(
			'intval',
			\array_column( \is_array( $rows ) ? $rows : [], 'id' )
		) );
	}

	protected function buildForRepairs() :array {
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
				/** @var LogsDB\Record[] $logIDs */
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

	/**
	 * @return list<string>
	 */
	protected function requestedSections() :array {
		$sections = $this->report->areas[ Constants::REPORT_AREA_SCANS ] ?? [];
		if ( !\is_array( $sections ) ) {
			$sections = [ 'scan_results', 'scan_repairs' ];
		}

		return \array_values( \array_intersect(
			[ 'scan_results', 'scan_repairs' ],
			$sections
		) );
	}
}
