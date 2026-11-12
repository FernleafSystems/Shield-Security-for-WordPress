<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type GroupManagementLink from ActionsQueueGroupsBuilder
 * @phpstan-import-type GroupSeed from ActionsQueueGroupContractBuilder
 * @phpstan-import-type CompactSummaryAction from ActionsQueueCompactSummaryRowBuilder
 * @phpstan-import-type CompactSummaryRow from ActionsQueueCompactSummaryRowBuilder
 * @phpstan-import-type MaintenanceExpansionRow from MaintenanceQueueItemDisplayNormalizer
 * @phpstan-import-type MaintenanceItemCta from MaintenanceQueueItemDisplayNormalizer
 * @phpstan-import-type MaintenanceQueueItem from MaintenanceQueueItemDisplayNormalizer
 */
class ActionsQueueMaintenanceGroupSeedBuilder {

	private const HEALTHY_COMPANION_KEY_SUFFIX = ':healthy';

	private ActionsQueueGroupDefinitions $groupDefinitions;
	private ActionsQueueCompactSummaryRowBuilder $summaryRowBuilder;

	public function __construct(
		ActionsQueueGroupDefinitions $groupDefinitions,
		?ActionsQueueCompactSummaryRowBuilder $summaryRowBuilder = null
	) {
		$this->groupDefinitions = $groupDefinitions;
		$this->summaryRowBuilder = $summaryRowBuilder ?? new ActionsQueueCompactSummaryRowBuilder();
	}

	/**
	 * @param list<MaintenanceQueueItem> $maintenanceItems
	 * @phpstan-return GroupSeed
	 */
	public function build(
		string $groupKey,
		array $maintenanceItems,
		bool $isHealthy = false,
		bool $useHealthyCompanionKey = false
	) :array {
		$seed = $this->groupDefinitions->isReviewMaintenanceAggregateGroupKey( $groupKey )
			? $this->buildAggregateSeed( $groupKey, $maintenanceItems, $isHealthy, $useHealthyCompanionKey )
			: $this->buildSingleItemSeed( $maintenanceItems[ 0 ], $isHealthy );
		return $isHealthy ? $this->withIgnoredRowsApart( $seed ) : $seed;
	}

	/**
	 * Items that are only passing because they're ignored keep their way back: they're listed apart, behind the
	 * group's "N ignored", rather than as rows that would read as needing action.
	 * @phpstan-param GroupSeed $seed
	 * @phpstan-return GroupSeed
	 */
	private function withIgnoredRowsApart( array $seed ) :array {
		$rows = empty( $seed[ 'maintenance_rows' ] )
			? ( empty( $seed[ 'summary_row' ] ) ? [] : [ $seed[ 'summary_row' ] ] )
			: $seed[ 'maintenance_rows' ];
		$ignored = \array_values( \array_filter( $rows, static fn( array $row ) :bool => $row[ 'is_ignored' ] ) );
		if ( !empty( $ignored ) ) {
			$seed[ 'ignored_rows' ] = $ignored;
			$seed[ 'maintenance_rows' ] = \array_values( \array_filter(
				$seed[ 'maintenance_rows' ],
				static fn( array $row ) :bool => !$row[ 'is_ignored' ]
			) );
			if ( !empty( $seed[ 'summary_row' ] ) && $seed[ 'summary_row' ][ 'is_ignored' ] ) {
				$seed[ 'summary_row' ] = [];
			}
		}
		return $seed;
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 * @phpstan-return GroupSeed
	 */
	private function buildSingleItemSeed( array $maintenanceItem, bool $isHealthy ) :array {
		$maintenanceRows = $this->projectRows( $maintenanceItem );

		return [
			'key'                 => $maintenanceItem[ 'key' ],
			'definition_key'      => 'maintenance',
			'label'               => $maintenanceItem[ 'label' ],
			'item_count'          => $isHealthy
				? $this->visibleCount( $maintenanceItem )
				: $maintenanceItem[ 'count' ],
			'status'              => StatusPriority::normalize( $maintenanceItem[ 'severity' ], 'warning' ),
			'narrative'           => $maintenanceItem[ 'description' ],
			'detail_shell'        => 'maintenance',
			'icon_class_override' => $maintenanceItem[ 'icon_class' ],
			'management_link'     => $this->buildManagementLink( $maintenanceItem ),
			'attention_items'     => [],
			'maintenance_rows'    => $maintenanceRows,
			'summary_row'         => empty( $maintenanceRows )
				? $this->buildSummaryRow( $maintenanceItem )
				: [],
		];
	}

	/**
	 * @param list<MaintenanceQueueItem> $maintenanceItems
	 * @phpstan-return GroupSeed
	 */
	private function buildAggregateSeed(
		string $groupKey,
		array $maintenanceItems,
		bool $isHealthy,
		bool $useHealthyCompanionKey
	) :array {
		$definition = $this->groupDefinitions->definitionForGroupKey( $groupKey );
		$itemCount = $this->aggregateItemCount( $maintenanceItems, $isHealthy );

		return [
			'key'              => $this->seedKey( $groupKey, $isHealthy, $useHealthyCompanionKey ),
			'definition_key'   => $groupKey,
			'label'            => $definition[ 'label' ],
			'item_count'       => $itemCount,
			'status'           => $this->aggregateStatus( $maintenanceItems ),
			'narrative'        => $this->buildAggregateNarrative( $groupKey, $itemCount, $isHealthy ),
			'detail_shell'     => 'maintenance',
			'management_link'  => [],
			'attention_items'  => [],
			'maintenance_rows' => $this->projectAggregateRows( $maintenanceItems ),
			'summary_row'      => [],
		];
	}

	private function seedKey( string $groupKey, bool $isHealthy, bool $useHealthyCompanionKey ) :string {
		return $isHealthy && $useHealthyCompanionKey
			? $groupKey.self::HEALTHY_COMPANION_KEY_SUFFIX
			: $groupKey;
	}

	/**
	 * @param list<MaintenanceQueueItem> $maintenanceItems
	 */
	private function aggregateItemCount( array $maintenanceItems, bool $isHealthy ) :int {
		return (int)\array_sum( \array_map(
			fn( array $maintenanceItem ) :int => $isHealthy
				? $this->visibleCount( $maintenanceItem )
				: $maintenanceItem[ 'count' ],
			$maintenanceItems
		) );
	}

	/**
	 * @param list<MaintenanceQueueItem> $maintenanceItems
	 */
	private function aggregateStatus( array $maintenanceItems ) :string {
		return StatusPriority::highest( \array_map(
			static fn( array $maintenanceItem ) :string => StatusPriority::normalize(
				$maintenanceItem[ 'severity' ],
				'good'
			),
			$maintenanceItems
		), 'good' );
	}

	private function buildAggregateNarrative( string $groupKey, int $itemCount, bool $isHealthy ) :string {
		$count = number_format_i18n( $itemCount );

		switch ( $groupKey ) {
			case 'maintenance_system':
				$pattern = $isHealthy
					? _n(
						'%s system item is currently looking good.',
						'%s system items are currently looking good.',
						$itemCount,
						'wp-simple-firewall'
					)
					: _n(
						'%s system item needs review.',
						'%s system items need review.',
						$itemCount,
						'wp-simple-firewall'
					);
				break;

			case 'maintenance_wordpress':
				$pattern = $isHealthy
					? _n(
						'%s WordPress item is currently looking good.',
						'%s WordPress items are currently looking good.',
						$itemCount,
						'wp-simple-firewall'
					)
					: _n(
						'%s WordPress item needs review.',
						'%s WordPress items need review.',
						$itemCount,
						'wp-simple-firewall'
					);
				break;

			default:
				$pattern = $isHealthy
					? _n(
						'%s maintenance item is currently looking good.',
						'%s maintenance items are currently looking good.',
						$itemCount,
						'wp-simple-firewall'
					)
					: _n(
						'%s maintenance item needs review.',
						'%s maintenance items need review.',
						$itemCount,
						'wp-simple-firewall'
					);
				break;
		}

		return \sprintf( $pattern, $count );
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 * @return array{}|GroupManagementLink
	 */
	private function buildManagementLink( array $maintenanceItem ) :array {
		$cta = $this->resolveCta( $maintenanceItem );
		if ( empty( $cta ) ) {
			return [];
		}

		return [
			'label'      => $cta[ 'label' ],
			'href'       => $cta[ 'href' ],
			'target'     => $cta[ 'target' ],
			'rel'        => $cta[ 'target' ] === '_blank' ? 'noopener noreferrer' : '',
			'icon_class' => $cta[ 'target' ] === '_blank' ? 'bi-box-arrow-up-right' : 'bi-arrow-right',
		];
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 * @return array{}|array{label:string,href:string,target:string}
	 */
	private function resolveCta( array $maintenanceItem ) :array {
		if ( empty( $maintenanceItem[ 'cta' ] ) ) {
			return [];
		}

		$cta = $maintenanceItem[ 'cta' ];
		$label = \trim( $cta[ 'label' ] );
		$href = \trim( $cta[ 'href' ] );
		if ( $label === '' || $href === '' ) {
			return [];
		}

		return [
			'label'  => $label,
			'href'   => $href,
			'target' => \trim( (string)( $cta[ 'target' ] ?? '' ) ),
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
	 * @param list<MaintenanceQueueItem> $maintenanceItems
	 * @return list<CompactSummaryRow>
	 */
	private function projectAggregateRows( array $maintenanceItems ) :array {
		$maintenanceItems = $this->sortAggregateItems( $maintenanceItems );

		return \array_values( \array_map(
			fn( array $maintenanceItem ) :array => $this->summaryRowBuilder->build(
				$maintenanceItem[ 'icon_class' ],
				$maintenanceItem[ 'label' ],
				$maintenanceItem[ 'description' ],
				$this->isIgnored( $maintenanceItem ) ? __( 'Currently ignored', 'wp-simple-firewall' ) : '',
				$this->isIgnored( $maintenanceItem ),
				$this->buildAggregateRowActions( $maintenanceItem )
			),
			$maintenanceItems
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

		return $this->summaryRowBuilder->build(
			$maintenanceItem[ 'icon_class' ],
			'',
			$maintenanceItem[ 'description' ],
			$this->isIgnored( $maintenanceItem ) ? __( 'Currently ignored', 'wp-simple-firewall' ) : '',
			$this->isIgnored( $maintenanceItem ),
			empty( $toggleAction ) ? [] : [ $toggleAction ]
		);
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 */
	private function visibleCount( array $maintenanceItem ) :int {
		$activeCount = $maintenanceItem[ 'count' ];
		if ( $activeCount > 0 ) {
			return $activeCount;
		}

		$rowCount = \count( $this->projectRows( $maintenanceItem ) );
		if ( $rowCount > 0 ) {
			return $rowCount;
		}

		return empty( $this->buildSummaryRow( $maintenanceItem ) ) ? 0 : 1;
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 * @return list<CompactSummaryAction>
	 */
	private function buildAggregateRowActions( array $maintenanceItem ) :array {
		$actions = [];
		$navigationAction = $this->buildNavigationAction( $maintenanceItem );
		if ( !empty( $navigationAction ) ) {
			$actions[] = $navigationAction;
		}

		$toggleAction = $maintenanceItem[ 'toggle_action' ];
		if ( !empty( $toggleAction ) ) {
			$actions[] = $toggleAction;
		}

		return $actions;
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 * @return array{}|CompactSummaryAction
	 */
	private function buildNavigationAction( array $maintenanceItem ) :array {
		$cta = $this->resolveCta( $maintenanceItem );
		if ( empty( $cta ) ) {
			return [];
		}

		return [
			'href'             => $cta[ 'href' ],
			'is_action'        => false,
			'label'            => $cta[ 'label' ],
			'icon'             => $cta[ 'target' ] === '_blank' ? 'bi bi-box-arrow-up-right' : 'bi bi-arrow-right',
			'tooltip'          => $cta[ 'label' ],
			'target'           => $cta[ 'target' ],
			'ajax_action_json' => '',
			'kind'             => '',
			'maintenance_key'  => '',
		];
	}

	/**
	 * @param list<MaintenanceQueueItem> $maintenanceItems
	 * @return list<MaintenanceQueueItem>
	 */
	private function sortAggregateItems( array $maintenanceItems ) :array {
		\uasort( $maintenanceItems, function ( array $left, array $right ) :int {
			$statusCmp = StatusPriority::rank(
				StatusPriority::normalize( $right[ 'severity' ], 'good' )
			) <=> StatusPriority::rank(
				StatusPriority::normalize( $left[ 'severity' ], 'good' )
			);
			if ( $statusCmp !== 0 ) {
				return $statusCmp;
			}

			$ignoredCmp = (int)$this->isIgnored( $left ) <=> (int)$this->isIgnored( $right );
			if ( $ignoredCmp !== 0 ) {
				return $ignoredCmp;
			}

			return \strnatcasecmp( $left[ 'label' ], $right[ 'label' ] );
		} );

		return \array_values( $maintenanceItems );
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 */
	private function isIgnored( array $maintenanceItem ) :bool {
		$toggleAction = $maintenanceItem[ 'toggle_action' ];
		return !empty( $toggleAction ) && $toggleAction[ 'kind' ] === 'unignore';
	}
}
