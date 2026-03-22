<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type GroupManagementLink from ActionsQueueGroupsBuilder
 * @phpstan-import-type GroupSeed from ActionsQueueGroupContractBuilder
 * @phpstan-import-type CompactSummaryRow from ActionsQueueCompactSummaryRowBuilder
 * @phpstan-import-type MaintenanceExpansionRow from MaintenanceQueueItemDisplayNormalizer
 * @phpstan-import-type MaintenanceQueueItem from MaintenanceQueueItemDisplayNormalizer
 */
class ActionsQueueMaintenanceGroupSeedBuilder {

	private ActionsQueueCompactSummaryRowBuilder $summaryRowBuilder;

	public function __construct( ?ActionsQueueCompactSummaryRowBuilder $summaryRowBuilder = null ) {
		$this->summaryRowBuilder = $summaryRowBuilder ?? new ActionsQueueCompactSummaryRowBuilder();
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 * @phpstan-return GroupSeed
	 */
	public function build( array $maintenanceItem, bool $isHealthy = false ) :array {
		$maintenanceRows = $this->projectRows( $maintenanceItem );

		return [
			'key'                 => $maintenanceItem[ 'key' ],
			'is_healthy'          => $isHealthy,
			'definition_key'      => 'maintenance',
			'heading_label'       => '',
			'label'               => $maintenanceItem[ 'label' ],
			'item_count'          => $isHealthy
				? $this->visibleCount( $maintenanceItem )
				: (int)$maintenanceItem[ 'count' ],
			'status'              => StatusPriority::normalize( $maintenanceItem[ 'severity' ], 'warning' ),
			'narrative'           => $maintenanceItem[ 'description' ],
			'detail_shell'        => 'maintenance',
			'icon_class_override' => $maintenanceItem[ 'icon_class' ],
			'links'               => [],
			'management_link'     => $this->buildManagementLink( $maintenanceItem ),
			'detail_table'        => [],
			'attention_items'     => [],
			'maintenance_rows'    => $maintenanceRows,
			'summary_row'         => empty( $maintenanceRows )
				? $this->buildSummaryRow( $maintenanceItem )
				: [],
		];
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 * @return array{}|GroupManagementLink
	 */
	private function buildManagementLink( array $maintenanceItem ) :array {
		if ( empty( $maintenanceItem[ 'cta' ] ) ) {
			return [];
		}

		$cta = $maintenanceItem[ 'cta' ];
		$label = $cta[ 'label' ];
		$href = $cta[ 'href' ];
		$target = \trim( (string)( $cta[ 'target' ] ?? '' ) );
		if ( $label === '' || $href === '' ) {
			return [];
		}

		return [
			'label'      => $label,
			'href'       => $href,
			'target'     => $target,
			'rel'        => $target === '_blank' ? 'noopener noreferrer' : '',
			'icon_class' => $target === '_blank' ? 'bi-box-arrow-up-right' : 'bi-arrow-right',
		];
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 * @return list<MaintenanceExpansionRow>
	 */
	private function extractRows( array $maintenanceItem ) :array {
		return empty( $maintenanceItem[ 'expansion' ] )
			? []
			: $maintenanceItem[ 'expansion' ][ 'table' ][ 'rows' ];
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 * @return list<CompactSummaryRow>
	 */
	private function projectRows( array $maintenanceItem ) :array {
		return \array_values( \array_map(
			fn( array $row ) :array => $this->summaryRowBuilder->build(
				$row[ 'icon_class' ],
				$row[ 'title' ],
				'',
				$row[ 'ignored_label' ],
				$row[ 'is_ignored' ],
				$row[ 'secondary_actions' ],
				$row[ 'inline_meta' ]
			),
			$this->extractRows( $maintenanceItem )
		) );
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 * @return array{}|CompactSummaryRow
	 */
	private function buildSummaryRow( array $maintenanceItem ) :array {
		$toggleAction = $maintenanceItem[ 'toggle_action' ];
		if ( $maintenanceItem[ 'description' ] === '' && empty( $toggleAction ) ) {
			return [];
		}

		$isIgnored = !empty( $toggleAction ) && $toggleAction[ 'kind' ] === 'unignore';
		return $this->summaryRowBuilder->build(
			$maintenanceItem[ 'icon_class' ],
			'',
			$maintenanceItem[ 'description' ],
			$isIgnored ? __( 'Currently ignored', 'wp-simple-firewall' ) : '',
			$isIgnored,
			empty( $toggleAction ) ? [] : [ $toggleAction ]
		);
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 */
	private function visibleCount( array $maintenanceItem ) :int {
		$activeCount = (int)( $maintenanceItem[ 'count' ] ?? 0 );
		if ( $activeCount > 0 ) {
			return $activeCount;
		}

		$rowCount = \count( $this->projectRows( $maintenanceItem ) );
		if ( $rowCount > 0 ) {
			return $rowCount;
		}

		return empty( $this->buildSummaryRow( $maintenanceItem ) ) ? 0 : 1;
	}
}
