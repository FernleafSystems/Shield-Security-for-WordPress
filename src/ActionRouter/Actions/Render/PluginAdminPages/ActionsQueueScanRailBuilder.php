<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	ActionsQueueScanRailMetrics,
	AjaxBatchRequests
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker,
	Maintenance,
	Malware,
	Plugins,
	Themes,
	Vulnerabilities,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-type MaintenanceItemCta array{
 *   href:string,
 *   label:string,
 *   target?:string
 * }
 * @phpstan-type MaintenanceItemAction array{
 *   href:string,
 *   label:string,
 *   icon:string,
 *   tooltip:string,
 *   target?:string,
 *   ajax_action?:array<string,mixed>
 * }
 * @phpstan-type MaintenanceExpansion array{
 *   id:string,
 *   type:string,
 *   status:string,
 *   table:array<string,mixed>
 * }
 * @phpstan-type MaintenanceQueueItem array{
 *   key:string,
 *   zone:string,
 *   label:string,
 *   count:int,
 *   severity:string,
 *   description:string,
 *   href:string,
 *   action:string,
 *   target:string,
 *   cta:array{}|MaintenanceItemCta,
 *   toggle_action:array{}|MaintenanceItemAction,
 *   expansion:array{}|MaintenanceExpansion
 * }
 * @phpstan-type AssessmentRow array{
 *   key:string,
 *   label:string,
 *   description:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string
 * }
 * @phpstan-type SummaryRow array{
 *   key:string,
 *   label:string,
 *   text:string,
 *   severity:string,
 *   count:int,
 *   action:string,
 *   href:string
 * }
 * @phpstan-type RailMetrics array{
 *   tabs:array<string,array{count:int,status:string}>,
 *   rail_accent_status:string
 * }
 */

class ActionsQueueScanRailBuilder extends ScansResultsViewBuilder {

	/**
	 * @param array{
	 *   zone_tiles:list<array<string,mixed>>
	 * } $landingViewData
	 * @param RailMetrics $initialMetrics
	 * @return array<string,mixed>
	 */
	public function buildFromLandingViewData( array $landingViewData, array $initialMetrics ) :array {
		$zoneTiles = $this->indexZoneTilesByKey( $landingViewData[ 'zone_tiles' ] );
		$scansTile = $zoneTiles[ 'scans' ];
		$maintenanceTile = $zoneTiles[ 'maintenance' ];
		$scansAssessmentRows = $scansTile[ 'assessment_rows' ];
		$maintenanceAssessmentRows = $maintenanceTile[ 'assessment_rows' ];
		$maintenanceItems = $maintenanceTile[ 'items' ];
		$maintenanceMetrics = $initialMetrics[ 'tabs' ][ 'maintenance' ];
		$summaryRows = $this->buildSummaryRowsFromZoneTile( $scansTile );
		if ( $maintenanceMetrics[ 'count' ] > 0 ) {
			$summaryRows[] = $this->buildMaintenanceSummaryRow( $maintenanceMetrics );
		}
		$summaryMetrics = $initialMetrics[ 'tabs' ][ 'summary' ];
		$maintenanceDefinition = $this->buildMaintenanceTabDefinition(
			$maintenanceItems,
			$maintenanceAssessmentRows,
			$maintenanceMetrics
		);
		$tabDefinitions = $this->buildOrderedQueueTabDefinitions( $maintenanceDefinition );
		$summaryTargets = $this->buildSummaryRailTargets( $tabDefinitions );
		$summaryMeta = $this->getRailTabMeta( 'summary' );
		$railTabs = $this->buildTabs( \array_merge(
			[
				[
					'key'        => 'summary',
					'label'      => $summaryMeta[ 'label' ],
					'count'      => $summaryMetrics[ 'count' ],
					'is_shown'   => true,
					'status'     => $summaryMetrics[ 'status' ],
					'icon_class' => $summaryMeta[ 'icon_class' ],
					'items'      => $this->buildSummaryRailItems(
						$summaryRows,
						$scansAssessmentRows,
						$summaryTargets
					),
					'is_loaded'  => true,
				],
			],
			$tabDefinitions
		) );

		$rail = $this->buildRailContract( $railTabs );
		$rail[ 'accent_status' ] = $initialMetrics[ 'rail_accent_status' ];

		return [
			'strings' => [
				'pane_loading' => __( 'Loading scan details...', 'wp-simple-firewall' ),
				'no_issues'    => __( 'No issues found in this section.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'rail'            => $rail,
				'rail_tabs'       => $railTabs,
				'metrics_action'  => ActionData::Build( ActionsQueueScanRailMetrics::class ),
				'preload_action'  => ActionData::Build( AjaxBatchRequests::class ),
				'summary_rows'    => $summaryRows,
				'assessment_rows' => $scansAssessmentRows,
			],
			'content' => [
				'section' => [
					'wordpress'       => '',
					'plugins'         => '',
					'themes'          => '',
					'vulnerabilities' => '',
					'malware'         => '',
					'filelocker'      => '',
				],
			],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildVulnerabilitiesPane() :array {
		return $this->buildRailPaneData( 'vulnerabilities' );
	}

	/**
	 * @param list<MaintenanceQueueItem> $maintenanceItems
	 * @param list<AssessmentRow> $maintenanceAssessmentRows
	 * @param array{count:int,status:string} $maintenanceMetrics
	 * @return array<string,mixed>
	 */
	public function buildMaintenanceTabDefinition(
		array $maintenanceItems,
		array $maintenanceAssessmentRows,
		array $maintenanceMetrics
	) :array {
		$meta = $this->getRailTabMeta( 'maintenance' );

		return [
			'key'        => 'maintenance',
			'label'      => $meta[ 'label' ],
			'count'      => $maintenanceMetrics[ 'count' ],
			'is_shown'   => true,
			'status'     => $this->maintenanceMetricsStatus( $maintenanceMetrics ),
			'icon_class' => $meta[ 'icon_class' ],
			'items'      => $this->buildMaintenanceRailItems( $maintenanceItems, $maintenanceAssessmentRows ),
			'is_loaded'  => true,
			'render_action' => $this->buildAjaxRenderActionData( Maintenance::class ),
		];
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function buildOrderedQueueTabDefinitions( array $maintenanceDefinition ) :array {
		$definitions = [];
		foreach ( $this->buildOrderedQueueRailTabKeys() as $tabKey ) {
			if ( $tabKey === 'maintenance' ) {
				$definitions[] = $maintenanceDefinition;
				continue;
			}
			$definition = $this->buildLazyTabDefinition( $tabKey );
			if ( $definition !== null ) {
				$definitions[] = $definition;
			}
		}
		return $definitions;
	}

	/**
	 * @return list<string>
	 */
	private function buildOrderedQueueRailTabKeys() :array {
		return [
			'vulnerabilities',
			'wordpress',
			'plugins',
			'themes',
			'malware',
			'file_locker',
			'maintenance',
		];
	}

	private function findRailTabKeyForSummaryKey( string $summaryKey ) :string {
		if ( $summaryKey === '' ) {
			return '';
		}

		return $this->getRailTabKeyForSummaryKey( $summaryKey );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function buildLazyTabDefinition( string $tabKey ) :?array {
		$availability = $this->getRailTabAvailability( $tabKey );
		if ( !$availability[ 'show_in_actions_queue' ] ) {
			return null;
		}

		$meta = $this->getRailTabMeta( $tabKey );
		$definition = [
			'key'        => $tabKey,
			'label'      => $meta[ 'label' ],
			'count'      => null,
			'is_shown'   => true,
			'status'     => 'neutral',
			'icon_class' => $meta[ 'icon_class' ],
			'items'      => [],
			'is_loaded'  => false,
			'is_disabled' => !$availability[ 'is_available' ],
			'disabled_message' => $availability[ 'disabled_message' ],
			'disabled_status' => $availability[ 'disabled_status' ],
			'render_action' => [],
			'show_count_placeholder' => true,
		];

		switch ( $tabKey ) {
			case 'wordpress':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Wordpress::class, [
					'display_context' => 'actions_queue',
				] ) ] );

			case 'plugins':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Plugins::class, [
					'display_context' => 'actions_queue',
				] ) ] );

			case 'themes':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Themes::class, [
					'display_context' => 'actions_queue',
				] ) ] );

			case 'vulnerabilities':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Vulnerabilities::class ) ] );

			case 'malware':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Malware::class, [
					'display_context' => 'actions_queue',
				] ) ] );

			case 'file_locker':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( FileLocker::class, [
					'display_context' => 'actions_queue',
				] ) ] );

			default:
				return null;
		}
	}

	/**
	 * @param array{items:list<array<string,mixed>>} $scansTile
	 * @return list<SummaryRow>
	 */
	private function buildSummaryRowsFromZoneTile( array $scansTile ) :array {
		return \array_values( \array_map( static function ( array $item ) :array {
			return [
				'key'      => $item[ 'key' ],
				'label'    => $item[ 'label' ],
				'text'     => $item[ 'description' ],
				'severity' => $item[ 'severity' ],
				'count'    => $item[ 'count' ],
				'action'   => $item[ 'action' ],
				'href'     => $item[ 'href' ],
			];
		}, $scansTile[ 'items' ] ) );
	}

	/**
	 * @param array{count:int,status:string} $maintenanceMetrics
	 * @return SummaryRow
	 */
	private function buildMaintenanceSummaryRow( array $maintenanceMetrics ) :array {
		$count = $maintenanceMetrics[ 'count' ];

		return [
			'key'      => 'maintenance',
			'label'    => __( 'Maintenance', 'wp-simple-firewall' ),
			'text'     => \sprintf(
				_n(
					'%s maintenance item needs review.',
					'%s maintenance items need review.',
					$count,
					'wp-simple-firewall'
				),
				$count
			),
			'severity' => $this->maintenanceMetricsStatus( $maintenanceMetrics ),
			'count'    => $count,
			'action'   => __( 'Open', 'wp-simple-firewall' ),
			'href'     => '',
		];
	}

	/**
	 * @param list<MaintenanceQueueItem> $maintenanceItems
	 * @param list<AssessmentRow> $maintenanceAssessmentRows
	 * @return list<array<string,mixed>>
	 */
	private function buildMaintenanceRailItems( array $maintenanceItems, array $maintenanceAssessmentRows ) :array {
		$items = [];

		foreach ( $maintenanceItems as $item ) {
			$status = StatusPriority::normalize( $item[ 'severity' ], 'warning' );
			$cta = $item[ 'cta' ] ?? [];
			$toggleAction = $item[ 'toggle_action' ] ?? [];
			$actions = $this->buildActionsForHref(
				$cta[ 'label' ] ?? '',
				$cta[ 'href' ] ?? '',
				'navigate',
				$cta[ 'target' ] ?? ''
			);
			if ( $toggleAction !== [] ) {
				$actions[] = $this->buildMaintenanceToggleRailAction( $toggleAction );
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
	 * @param list<SummaryRow> $summaryRows
	 * @param list<AssessmentRow> $assessmentRows
	 * @param array<string,string> $summaryRailTargets
	 * @return list<array<string,mixed>>
	 */
	protected function buildSummaryRailItems( array $summaryRows, array $assessmentRows, array $summaryRailTargets = [] ) :array {
		$items = [];

		foreach ( $this->buildOrderedSummaryIssueRows( $summaryRows, $summaryRailTargets ) as $row ) {
			$items[] = $row;
		}

		foreach ( $this->buildOrderedSummaryAssessmentRows( $assessmentRows ) as $row ) {
			$items[] = $row;
		}

		return $items;
	}

	/**
	 * @param list<SummaryRow> $summaryRows
	 * @param array<string,string> $summaryRailTargets
	 * @return list<array<string,mixed>>
	 */
	private function buildOrderedSummaryIssueRows( array $summaryRows, array $summaryRailTargets ) :array {
		$items = [];

		foreach ( [
			'critical' => __( 'Critical', 'wp-simple-firewall' ),
			'warning'  => __( 'Warnings', 'wp-simple-firewall' ),
		] as $severity => $sectionLabel ) {
			foreach ( $this->filterSummaryRowsBySeverityAndTabOrder( $summaryRows, $severity ) as $item ) {
				$railTab = $summaryRailTargets[ $item[ 'key' ] ] ?? $this->findRailTabKeyForSummaryKey( $item[ 'key' ] );
				$row = $this->buildDetailRow(
					$item[ 'label' ],
					$item[ 'text' ],
					$severity,
					$item[ 'count' ],
					$severity
				);
				if ( $railTab !== '' ) {
					$row[ 'attributes' ] = $this->buildQueueRailSwitchRowAttributes( $railTab );
				}
				else {
					$row[ 'actions' ] = $this->buildActionsForHref(
						$item[ 'action' ],
						$item[ 'href' ]
					);
				}
				$row[ 'section_label' ] = $sectionLabel;
				$items[] = $row;
			}
		}

		return $items;
	}

	/**
	 * @param list<AssessmentRow> $assessmentRows
	 * @return list<array<string,mixed>>
	 */
	private function buildOrderedSummaryAssessmentRows( array $assessmentRows ) :array {
		$items = [];

		foreach ( $this->filterAssessmentRowsByStatusAndTabOrder( $assessmentRows, 'good' ) as $item ) {
			$row = $this->buildDetailRow(
				$item[ 'label' ],
				$item[ 'description' ],
				'good',
				null,
				null,
				[],
				$item[ 'status_icon_class' ],
				$item[ 'status_label' ]
			);
			$row[ 'section_label' ] = __( 'All clear', 'wp-simple-firewall' );
			$items[] = $row;
		}

		return $items;
	}

	/**
	 * @param list<SummaryRow> $summaryRows
	 * @return list<SummaryRow>
	 */
	private function filterSummaryRowsBySeverityAndTabOrder( array $summaryRows, string $severity ) :array {
		return $this->sortByQueueTabOrder( \array_values( \array_filter(
			$summaryRows,
			fn( array $item ) :bool => StatusPriority::normalize( $item[ 'severity' ], 'warning' ) === $severity
		) ), fn( array $item ) :string => $this->findRailTabKeyForSummaryKey( $item[ 'key' ] ) );
	}

	/**
	 * @param list<AssessmentRow> $assessmentRows
	 * @return list<AssessmentRow>
	 */
	private function filterAssessmentRowsByStatusAndTabOrder( array $assessmentRows, string $status ) :array {
		return $this->sortByQueueTabOrder( \array_values( \array_filter(
			$assessmentRows,
			static fn( array $item ) :bool => StatusPriority::normalize( $item[ 'status' ], 'good' ) === $status
		) ), fn( array $item ) :string => $this->findRailTabKeyForSummaryKey( $item[ 'key' ] ) );
	}

	/**
	 * @template T of array<string,mixed>
	 * @param list<T> $items
	 * @param callable(T):string $tabKeyFromItem
	 * @return list<T>
	 */
	private function sortByQueueTabOrder( array $items, callable $tabKeyFromItem ) :array {
		$sorted = [];

		foreach ( $this->buildOrderedQueueRailTabKeys() as $tabKey ) {
			foreach ( $items as $item ) {
				if ( $tabKeyFromItem( $item ) === $tabKey ) {
					$sorted[] = $item;
				}
			}
		}

		foreach ( $items as $item ) {
			if ( !\in_array( $item, $sorted, true ) ) {
				$sorted[] = $item;
			}
		}

		return $sorted;
	}

	/**
	 * @return array<string,string>
	 */
	private function buildQueueRailSwitchRowAttributes( string $target ) :array {
		return [
			'data-shield-rail-switch' => $target,
			'role'                    => 'button',
			'tabindex'                => '0',
		];
	}

	/**
	 * @param array{count:int,status:string} $maintenanceMetrics
	 */
	private function maintenanceMetricsStatus( array $maintenanceMetrics ) :string {
		return StatusPriority::normalize( $maintenanceMetrics[ 'status' ], 'good' );
	}

	/**
	 * @param MaintenanceItemAction $action
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
				'data-actions-queue-maintenance-action' => empty( $action[ 'ajax_action' ] )
					? ''
					: (string)\json_encode( $action[ 'ajax_action' ] ),
				'target' => $action[ 'target' ] ?? '',
			], static fn( string $value ) :bool => $value !== '' ),
		];
	}

	/**
	 * @return array{label:string,icon_class:string,summary_keys:list<string>}
	 */
	protected function getRailTabMeta( string $key ) :array {
		if ( $key === 'maintenance' ) {
			return [
				'label'        => __( 'Maintenance', 'wp-simple-firewall' ),
				'icon_class'   => 'bi bi-wrench',
				'summary_keys' => [ 'maintenance' ],
			];
		}

		return parent::getRailTabMeta( $key );
	}

	/**
	 * @param list<array<string,mixed>> $zoneTiles
	 * @return array<string,array<string,mixed>>
	 */
	private function indexZoneTilesByKey( array $zoneTiles ) :array {
		$indexed = [];
		foreach ( $zoneTiles as $tile ) {
			$indexed[ $tile[ 'key' ] ] = $tile;
		}
		return $indexed;
	}
}
