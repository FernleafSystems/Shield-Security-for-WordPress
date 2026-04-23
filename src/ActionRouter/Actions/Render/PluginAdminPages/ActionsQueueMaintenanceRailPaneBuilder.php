<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type AssessmentRow from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type MaintenanceQueueItem from MaintenanceQueueItemDisplayNormalizer
 * @phpstan-import-type MaintenanceUiAction from MaintenanceQueueItemDisplayNormalizer
 */
class ActionsQueueMaintenanceRailPaneBuilder extends ScansResultsViewBuilder {

	/**
	 * @param list<MaintenanceQueueItem> $maintenanceItems
	 * @param list<AssessmentRow>       $maintenanceAssessmentRows
	 * @return array<string,mixed>
	 */
	public function buildMaintenancePane( array $maintenanceItems, array $maintenanceAssessmentRows ) :array {
		$items = $this->buildMaintenanceRailItems( $maintenanceItems, $maintenanceAssessmentRows );
		$count = $this->countNonGoodMaintenanceItems( $items );

		return [
			'key'        => 'maintenance',
			'label'      => __( 'Maintenance', 'wp-simple-firewall' ),
			'count'      => $count,
			'count_items' => $count,
			'is_shown'   => true,
			'status'     => StatusPriority::highest( \array_column( $items, 'status' ), 'good' ),
			'icon_class' => 'bi bi-wrench',
			'items'      => $items,
			'is_loaded'  => true,
			'is_disabled' => false,
			'disabled_message' => '',
			'disabled_status' => 'good',
			'disabled_actions' => [],
			'render_action' => [],
		];
	}

	/**
	 * @param list<MaintenanceQueueItem> $maintenanceItems
	 * @param list<AssessmentRow>       $maintenanceAssessmentRows
	 * @return list<array<string,mixed>>
	 */
	private function buildMaintenanceRailItems( array $maintenanceItems, array $maintenanceAssessmentRows ) :array {
		$items = [];

		foreach ( $maintenanceItems as $item ) {
			$status = StatusPriority::normalize( $item[ 'severity' ], 'warning' );
			$cta = $item[ 'cta' ] ?? [];
			$actions = $this->buildActionsForHref(
				$cta[ 'label' ] ?? '',
				$cta[ 'href' ] ?? '',
				'navigate',
				$cta[ 'target' ] ?? ''
			);
			if ( !empty( $item[ 'toggle_action' ] ) ) {
				$actions[] = $this->buildMaintenanceToggleRailAction( $item[ 'toggle_action' ] );
			}
			$row = $this->buildDetailRow(
				$item[ 'label' ],
				$item[ 'description' ],
				$status,
				$item[ 'count' ],
				$status === 'neutral' ? 'info' : $status,
				$actions,
				null,
				null,
				$status === 'good' ? __( 'All clear', 'wp-simple-firewall' ) : __( 'Needs attention', 'wp-simple-firewall' )
			);
			if ( $item[ 'expansion' ] !== [] ) {
				$row = $this->attachExpansionToDetailRow( $row, $item[ 'expansion' ] );
			}
			$items[] = $row;
		}

		foreach ( $maintenanceAssessmentRows as $row ) {
			if ( $row[ 'status' ] !== 'good' ) {
				continue;
			}

			$items[] = $this->buildDetailRow(
				$row[ 'label' ],
				$row[ 'description' ],
				'good',
				null,
				null,
				[],
				$row[ 'status_icon_class' ],
				$row[ 'status_label' ],
				__( 'All clear', 'wp-simple-firewall' )
			);
		}

		return $items;
	}

	/**
	 * @param list<array<string,mixed>> $items
	 */
	private function countNonGoodMaintenanceItems( array $items ) :int {
		return \count( \array_filter(
			$items,
			static fn( array $item ) :bool => StatusPriority::normalize( $item[ 'status' ], 'good' ) !== 'good'
		) );
	}

	/**
	 * @param MaintenanceUiAction $action
	 * @return array<string,mixed>
	 */
	private function buildMaintenanceToggleRailAction( array $action ) :array {
		return [
			'type'       => 'navigate',
			'label'      => $action[ 'label' ],
			'href'       => $action[ 'href' ],
			'icon'       => $action[ 'icon' ],
			'tooltip'    => $action[ 'tooltip' ],
			'attributes' => \array_filter( [
				'data-actions-queue-maintenance-action' => $action[ 'ajax_action_json' ],
				'target' => $action[ 'target' ],
			], static fn( string $value ) :bool => $value !== '' ),
		];
	}
}
