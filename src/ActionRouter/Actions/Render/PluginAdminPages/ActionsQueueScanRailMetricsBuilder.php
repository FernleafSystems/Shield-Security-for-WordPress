<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\ActionsQueueScanStateBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueueDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueuePayload;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

class ActionsQueueScanRailMetricsBuilder {

	/**
	 * @return array{
	 *   tabs:array<string,array{count:int,status:string}>,
	 *   rail_accent_status:string
	 * }
	 */
	public function build( array $needsAttentionPayload = [] ) :array {
		$state = ( new ActionsQueueScanStateBuilder() )->build();
		$maintenanceMetrics = $this->buildMaintenanceMetrics( $needsAttentionPayload );
		$tabs = $state[ 'tabs' ];
		$tabs[ 'maintenance' ] = $maintenanceMetrics;
		$tabs[ 'summary' ] = [
			'count'  => (int)( $tabs[ 'summary' ][ 'count' ] ?? 0 ) + $maintenanceMetrics[ 'count' ],
			'status' => StatusPriority::highest( [
				(string)( $tabs[ 'summary' ][ 'status' ] ?? 'good' ),
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
	private function buildMaintenanceMetrics( array $needsAttentionPayload ) :array {
		$maintenanceZoneGroup = NeedsAttentionQueuePayload::zoneGroup(
			$this->resolveQueuePayload( $needsAttentionPayload ),
			'maintenance'
		);
		$count = (int)( $maintenanceZoneGroup[ 'total_issues' ] ?? 0 );

		return [
			'count'  => $count,
			'status' => $count > 0
				? StatusPriority::normalize( (string)( $maintenanceZoneGroup[ 'severity' ] ?? 'warning' ), 'warning' )
				: 'good',
		];
	}

	private function resolveQueuePayload( array $needsAttentionPayload ) :array {
		return !empty( $needsAttentionPayload )
			? $needsAttentionPayload
			: [
				'render_data' => ( new NeedsAttentionQueueDataBuilder() )->build( [
					'compact_all_clear' => true,
				] ),
			];
	}
}
