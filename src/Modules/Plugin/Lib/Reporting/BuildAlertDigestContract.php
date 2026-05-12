<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\{
	Afs,
	Apc,
	Wpv
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class BuildAlertDigestContract {

	use PluginControllerConsumer;

	public function build( ReportVO $report ) :array {
		$rows = \array_values( \array_filter( \array_map(
			fn( array $scanRow ) :?array => $this->buildRowContract( $scanRow ),
			$report->areas_data[ Constants::REPORT_AREA_SCANS ][ 'scan_results' ] ?? []
		) ) );

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

	protected function buildRowContract( array $scanRow ) :?array {
		$slug = (string)( $scanRow[ 'slug' ] ?? '' );
		if ( !$this->isCriticalScanSlug( $slug ) ) {
			return null;
		}

		$count = (int)( $scanRow[ 'count' ] ?? 0 );
		if ( $count < 1 ) {
			return null;
		}

		$newCount = (int)( $scanRow[ 'new_count' ] ?? 0 );
		$outstandingCount = \max( 0, $count - $newCount );
		$visibleItems = \is_array( $scanRow[ 'items' ] ?? null ) ? $scanRow[ 'items' ] : [];
		$notificationTargetIds = \array_values( \array_unique( \array_map(
			'intval',
			\array_filter(
				\is_array( $scanRow[ 'notification_target_ids' ] ?? null ) ? $scanRow[ 'notification_target_ids' ] : [],
				static fn( $id ) :bool => (int)$id > 0
			)
		) ) );
		$newItems = \array_values( \array_map(
			fn( array $item ) :array => [ 'label' => (string)( $item[ 'label' ] ?? '' ) ],
			\array_filter( $visibleItems, static fn( array $item ) :bool => !empty( $item[ 'is_new' ] ) )
		) );
		$outstandingItems = \array_values( \array_map(
			fn( array $item ) :array => [ 'label' => (string)( $item[ 'label' ] ?? '' ) ],
			\array_filter( $visibleItems, static fn( array $item ) :bool => empty( $item[ 'is_new' ] ) )
		) );

		return [
			'title'                   => (string)( $scanRow[ 'name' ] ?? __( 'Scan Issue', 'wp-simple-firewall' ) ),
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
			'notification_target_ids' => $notificationTargetIds,
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
	 * @param array<array<string,mixed>> $rows
	 * @return list<int>
	 */
	private function collectNotificationTargetIds( array $rows ) :array {
		$ids = [];
		foreach ( $rows as $row ) {
			foreach ( (array)( $row[ 'notification_target_ids' ] ?? [] ) as $id ) {
				$id = (int)$id;
				if ( $id > 0 ) {
					$ids[ $id ] = $id;
				}
			}
		}

		return \array_values( $ids );
	}
}
