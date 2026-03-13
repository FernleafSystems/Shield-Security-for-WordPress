<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\ActionsQueueScanStateBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
 */
class ActionsQueueScanRailMetricsBuilder {

	/**
	 * @param AttentionQuery $attentionQuery
	 * @return array{
	 *   tabs:array<string,array{count:int,status:string}>,
	 *   rail_accent_status:string
	 * }
	 */
	public function build( array $attentionQuery ) :array {
		$state = ( new ActionsQueueScanStateBuilder() )->build();
		$maintenanceMetrics = $this->buildMaintenanceMetrics( $attentionQuery );
		$tabs = $state[ 'tabs' ];
		$tabs[ 'maintenance' ] = $maintenanceMetrics;
		$tabs[ 'summary' ] = [
			'count'  => $tabs[ 'summary' ][ 'count' ] + $maintenanceMetrics[ 'count' ],
			'status' => StatusPriority::highest( [
				$tabs[ 'summary' ][ 'status' ],
				$maintenanceMetrics[ 'status' ],
			], 'good' ),
		];

		return [
			'tabs'               => $tabs,
			'rail_accent_status' => StatusPriority::highest(
				\array_column(
					\array_values( \array_filter(
						$tabs,
						static fn( string $key ) :bool => $key !== 'summary',
						\ARRAY_FILTER_USE_KEY
					) ),
					'status'
				),
				'good'
			),
		];
	}

	/**
	 * @return array{count:int,status:string}
	 */
	private function buildMaintenanceMetrics( array $attentionQuery ) :array {
		$maintenanceGroup = $attentionQuery[ 'groups' ][ 'maintenance' ];
		$count = $maintenanceGroup[ 'total' ];

		return [
			'count'  => $count,
			'status' => $count > 0
				? StatusPriority::normalize( $maintenanceGroup[ 'severity' ], 'warning' )
				: 'good',
		];
	}
}
