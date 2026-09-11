<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\{
	Afs,
	Apc,
	Wpv
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-import-type ScanReportItem from \FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForScans
 * @phpstan-type AlertDigestSourceRow array{
 *   slug:string,
 *   name:string,
 *   count:int,
 *   new_count:int,
 *   items:list<ScanReportItem>,
 *   notification_target_ids:list<int>
 * }
 * @phpstan-type AlertDigestItem array{label:string}
 * @phpstan-type AlertDigestRow array{
 *   title:string,
 *   count:int,
 *   new_count:int,
 *   count_summary:string,
 *   outstanding_count:int,
 *   has_new:bool,
 *   new_items:list<AlertDigestItem>,
 *   outstanding_items:list<AlertDigestItem>,
 *   hidden_new_count:int,
 *   hidden_outstanding_count:int,
 *   notification_target_ids:list<int>,
 *   review_href:string,
 *   review_action:string
 * }
 * @phpstan-type AlertDigest array{
 *   has_new_items:bool,
 *   notification_target_ids:list<int>,
 *   summary:array{row_count:int,new_total:int,current_total:int,outstanding_total:int,actions_queue_href:string},
 *   rows:list<AlertDigestRow>
 * }
 */
class BuildAlertDigestContract {

	use PluginControllerConsumer;

	/**
	 * @phpstan-return AlertDigest
	 */
	public function build( ReportVO $report ) :array {
		$scanRows = $this->extractScanRows( $report );
		$rows = \array_values( \array_filter( \array_map(
			fn( array $scanRow ) :?array => $this->buildRowContract( $scanRow ),
			$scanRows
		), static fn( ?array $row ) :bool => $row !== null ) );

		\usort( $rows, static function ( array $a, array $b ) :int {
			$hasNewCmp = (int)$b[ 'has_new' ] <=> (int)$a[ 'has_new' ];
			if ( $hasNewCmp !== 0 ) {
				return $hasNewCmp;
			}

			$countCmp = $b[ 'count' ] <=> $a[ 'count' ];
			return $countCmp !== 0 ? $countCmp : \strcmp( $a[ 'title' ], $b[ 'title' ] );
		} );

		$newTotal = (int)\array_sum( \array_column( $rows, 'new_count' ) );
		$currentTotal = (int)\array_sum( \array_column( $rows, 'count' ) );
		$notificationTargetIds = $this->collectNotificationTargetIds( $rows );

		return [
			'has_new_items'          => $newTotal > 0,
			'notification_target_ids'=> $notificationTargetIds,
			'summary'                => [
				'row_count'          => \count( $rows ),
				'new_total'          => $newTotal,
				'current_total'      => $currentTotal,
				'outstanding_total'  => \max( 0, $currentTotal - $newTotal ),
				'actions_queue_href' => self::con()->plugin_urls->actionsQueueScans(),
			],
			'rows'          => $rows,
		];
	}

	/**
	 * @phpstan-return list<AlertDigestSourceRow>
	 */
	private function extractScanRows( ReportVO $report ) :array {
		$areasData = $report->areas_data;
		if ( !isset( $areasData[ Constants::REPORT_AREA_SCANS ][ 'scan_results' ] )
			 || !\is_array( $areasData[ Constants::REPORT_AREA_SCANS ][ 'scan_results' ] ) ) {
			throw new \UnexpectedValueException( 'Alert report scan results are missing.' );
		}

		$scanRows = [];
		foreach ( $areasData[ Constants::REPORT_AREA_SCANS ][ 'scan_results' ] as $row ) {
			if ( !\is_array( $row )
				 || !isset( $row[ 'slug' ], $row[ 'name' ], $row[ 'count' ], $row[ 'new_count' ], $row[ 'items' ], $row[ 'notification_target_ids' ] )
				 || !\is_string( $row[ 'slug' ] )
				 || !\is_string( $row[ 'name' ] )
				 || !\is_int( $row[ 'count' ] )
				 || !\is_int( $row[ 'new_count' ] )
				 || !\is_array( $row[ 'items' ] )
				 || !\is_array( $row[ 'notification_target_ids' ] ) ) {
				throw new \UnexpectedValueException( 'Alert report scan row is invalid.' );
			}

			$items = [];
			foreach ( $row[ 'items' ] as $item ) {
				if ( !\is_array( $item )
					 || !isset( $item[ 'label' ], $item[ 'is_new' ] )
					 || !\is_string( $item[ 'label' ] )
					 || !\is_bool( $item[ 'is_new' ] ) ) {
					throw new \UnexpectedValueException( 'Alert report scan item is invalid.' );
				}
				$items[] = [
					'label'  => $item[ 'label' ],
					'is_new' => $item[ 'is_new' ],
				];
			}

			$notificationTargetIDs = [];
			foreach ( $row[ 'notification_target_ids' ] as $id ) {
				if ( !\is_int( $id ) ) {
					throw new \UnexpectedValueException( 'Alert report notification target ID is invalid.' );
				}
				$notificationTargetIDs[] = $id;
			}

			$scanRows[] = [
				'slug'                    => $row[ 'slug' ],
				'name'                    => $row[ 'name' ],
				'count'                   => $row[ 'count' ],
				'new_count'               => $row[ 'new_count' ],
				'items'                   => $items,
				'notification_target_ids' => $notificationTargetIDs,
			];
		}

		return $scanRows;
	}

	/**
	 * @phpstan-param AlertDigestSourceRow $scanRow
	 * @phpstan-return ?AlertDigestRow
	 */
	protected function buildRowContract( array $scanRow ) :?array {
		$slug = $scanRow[ 'slug' ];
		if ( !$this->isCriticalScanSlug( $slug ) ) {
			return null;
		}

		$count = $scanRow[ 'count' ];
		if ( $count < 1 ) {
			return null;
		}

		$newCount = $scanRow[ 'new_count' ];
		$outstandingCount = \max( 0, $count - $newCount );
		$visibleItems = $scanRow[ 'items' ];
		$newItems = \array_values( \array_map(
			fn( array $item ) :array => [ 'label' => $item[ 'label' ] ],
			\array_filter( $visibleItems, static fn( array $item ) :bool => $item[ 'is_new' ] )
		) );
		$outstandingItems = \array_values( \array_map(
			fn( array $item ) :array => [ 'label' => $item[ 'label' ] ],
			\array_filter( $visibleItems, static fn( array $item ) :bool => !$item[ 'is_new' ] )
		) );

		return [
			'title'                   => $scanRow[ 'name' ],
			'count'                   => $count,
			'new_count'               => $newCount,
			'count_summary'           => \sprintf(
				__( '%1$s total, %2$s new', 'wp-simple-firewall' ),
				$count,
				$newCount
			),
			'outstanding_count'       => $outstandingCount,
			'has_new'                 => $newCount > 0,
			'new_items'               => $newItems,
			'outstanding_items'       => $outstandingItems,
			'hidden_new_count'        => \max( 0, $newCount - \count( $newItems ) ),
			'hidden_outstanding_count'=> \max( 0, $outstandingCount - \count( $outstandingItems ) ),
			'notification_target_ids' => $scanRow[ 'notification_target_ids' ],
			'review_href'             => self::con()->plugin_urls->actionsQueueScans(),
			'review_action'           => __( 'Review Scan Results', 'wp-simple-firewall' ),
		];
	}

	protected function isCriticalScanSlug( string $slug ) :bool {
		return \in_array( $slug, [
			Wpv::SCAN_SLUG,
			Apc::SCAN_SLUG,
			Afs::SCAN_SLUG.'_malware',
			Afs::SCAN_SLUG.'_wp',
			Afs::SCAN_SLUG.'_plugin',
			Afs::SCAN_SLUG.'_theme',
		], true );
	}

	/**
	 * @phpstan-param list<AlertDigestRow> $rows
	 * @return list<int>
	 */
	private function collectNotificationTargetIds( array $rows ) :array {
		$ids = [];
		foreach ( $rows as $row ) {
			foreach ( $row[ 'notification_target_ids' ] as $id ) {
				$ids[ $id ] = $id;
			}
		}

		return \array_values( $ids );
	}
}
