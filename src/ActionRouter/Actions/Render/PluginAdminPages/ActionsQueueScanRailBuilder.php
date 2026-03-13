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
		$tabDefinitions = $this->buildOrderedQueueTabDefinitions( $summaryRows, $maintenanceDefinition );
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
	private function buildOrderedQueueTabDefinitions( array $summaryRows, array $maintenanceDefinition ) :array {
		$definitions = [];
		foreach ( $this->buildOrderedQueueRailTabKeys( $summaryRows ) as $tabKey ) {
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
	 * @param list<SummaryRow> $summaryRows
	 * @return list<string>
	 */
	private function buildOrderedQueueRailTabKeys( array $summaryRows ) :array {
		$orderedKeys = [];
		foreach ( $summaryRows as $row ) {
			$tabKey = $this->findRailTabKeyForSummaryKey( (string)( $row[ 'key' ] ?? '' ) );
			if ( $tabKey !== '' && !\in_array( $tabKey, $orderedKeys, true ) ) {
				$orderedKeys[] = $tabKey;
			}
		}

		foreach ( \array_merge( $this->getOrderedRailTabKeys( false ), [ 'maintenance' ] ) as $tabKey ) {
			if ( !\in_array( $tabKey, $orderedKeys, true ) ) {
				$orderedKeys[] = $tabKey;
			}
		}

		return $orderedKeys;
	}

	private function findRailTabKeyForSummaryKey( string $summaryKey ) :string {
		if ( $summaryKey === '' ) {
			return '';
		}

		foreach ( \array_merge( $this->getOrderedRailTabKeys( false ), [ 'maintenance' ] ) as $tabKey ) {
			if ( \in_array( $summaryKey, $this->getRailTabMeta( $tabKey )[ 'summary_keys' ], true ) ) {
				return $tabKey;
			}
		}

		return '';
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
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Malware::class ) ] );

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
