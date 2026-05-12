<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueGroupDefinitions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type AttentionItem from BuildAttentionItems
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
 */
class DashboardAttentionQueryFilter {

	/**
	 * @param AttentionQuery $attention
	 * @return AttentionQuery
	 */
	public function filter( array $attention ) :array {
		$filteredItems = \array_values( \array_filter(
			$attention[ 'items' ],
			[ $this, 'isDashboardAttentionItem' ]
		) );

		foreach ( $attention[ 'groups' ] as $groupKey => $group ) {
			$groupItems = \array_values( \array_filter(
				$group[ 'items' ],
				[ $this, 'isDashboardAttentionItem' ]
			) );
			$attention[ 'groups' ][ $groupKey ][ 'items' ] = $groupItems;
			$attention[ 'groups' ][ $groupKey ][ 'total' ] = (int)\array_sum( \array_column( $groupItems, 'count' ) );
			$attention[ 'groups' ][ $groupKey ][ 'severity' ] = $this->highestSeverity( $groupItems );
		}

		$totalItems = (int)\array_sum( \array_column( $filteredItems, 'count' ) );
		$attention[ 'items' ] = $filteredItems;
		$attention[ 'summary' ] = [
			'total'        => $totalItems,
			'severity'     => $this->highestSeverity( $filteredItems ),
			'is_all_clear' => $totalItems === 0,
		];

		return $attention;
	}

	/**
	 * @param AttentionItem $item
	 */
	private function isDashboardAttentionItem( array $item ) :bool {
		return !ActionsQueueGroupDefinitions::isIgnoredOnlySummaryKey( $item[ 'key' ] );
	}

	/**
	 * @param list<AttentionItem> $items
	 */
	private function highestSeverity( array $items ) :string {
		return StatusPriority::highest( \array_column( $items, 'severity' ), 'good' );
	}
}
