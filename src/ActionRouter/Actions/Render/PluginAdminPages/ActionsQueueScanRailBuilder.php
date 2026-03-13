<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	ActionsQueueScanRailMetrics,
	AjaxBatchRequests
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker,
	Malware,
	Plugins,
	Themes,
	Vulnerabilities,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueuePayload;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type QueueItem from NeedsAttentionQueuePayload
 * @phpstan-import-type ZoneGroup from NeedsAttentionQueuePayload
 * @phpstan-type MaintenanceItemCta array{
 *   href:string,
 *   label:string,
 *   target?:string
 * }
 * @phpstan-type MaintenanceExpansion array{
 *   id:string,
 *   type:'simple_table',
 *   status:string,
 *   table:array<string,mixed>
 * }
 * @phpstan-type MaintenanceQueueItem QueueItem&array{
 *   cta:array{}|MaintenanceItemCta,
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
	 *   scans:list<AssessmentRow>,
	 *   maintenance:list<AssessmentRow>
	 * } $assessmentRowsByZone
	 * @return array<string,mixed>
	 */
	public function buildFromLandingData( array $needsAttentionPayload, array $assessmentRowsByZone = [
		'scans' => [],
		'maintenance' => [],
	] ) :array {
		$scansZoneGroup = NeedsAttentionQueuePayload::zoneGroup( $needsAttentionPayload, 'scans' );
		$maintenanceZoneGroup = NeedsAttentionQueuePayload::zoneGroup( $needsAttentionPayload, 'maintenance' );
		$scansAssessmentRows = $assessmentRowsByZone[ 'scans' ];
		$maintenanceAssessmentRows = $assessmentRowsByZone[ 'maintenance' ];
		$maintenanceItems = ( new MaintenanceQueueItemDisplayNormalizer() )->normalizeAll( $maintenanceZoneGroup[ 'items' ] );
		$metrics = $this->buildInitialRailMetrics( $needsAttentionPayload );
		$maintenanceMetrics = $metrics[ 'tabs' ][ 'maintenance' ];
		$summaryRows = $this->buildSummaryRowsFromZoneGroup( $scansZoneGroup );
		if ( $maintenanceMetrics[ 'count' ] > 0 ) {
			$summaryRows[] = $this->buildMaintenanceSummaryRow( $maintenanceMetrics );
		}
		$summaryMetrics = $metrics[ 'tabs' ][ 'summary' ];
		$lazyDefinitions = $this->buildLazyTabDefinitions();
		$maintenanceDefinition = $this->buildMaintenanceTabDefinition(
			$maintenanceItems,
			$maintenanceAssessmentRows,
			$maintenanceMetrics
		);
		$summaryTargets = $this->buildSummaryRailTargets( \array_merge( [
			$maintenanceDefinition,
		], $lazyDefinitions ) );
		$summaryMeta = $this->getRailTabMeta( 'summary' );
		$railTabs = $this->buildTabs( \array_merge( [
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
			$maintenanceDefinition,
		], $lazyDefinitions ) );

		$rail = $this->buildRailContract( $railTabs );
		$rail[ 'accent_status' ] = $metrics[ 'rail_accent_status' ];

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
	 * @return RailMetrics
	 */
	protected function buildInitialRailMetrics( array $needsAttentionPayload = [] ) :array {
		return ( new ActionsQueueScanRailMetricsBuilder() )->build( $needsAttentionPayload );
	}

	/**
	 * @param list<QueueItem> $maintenanceItems
	 * @param list<AssessmentRow> $maintenanceAssessmentRows
	 * @param array{count:int,status:string} $maintenanceMetrics
	 * @return array<string,mixed>
	 */
	private function buildMaintenanceTabDefinition(
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
		];
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function buildLazyTabDefinitions() :array {
		$definitions = [];
		foreach ( $this->getOrderedRailTabKeys( false ) as $tabKey ) {
			$definition = $this->buildLazyTabDefinition( $tabKey );
			if ( $definition !== null ) {
				$definitions[] = $definition;
			}
		}
		return $definitions;
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
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Wordpress::class ) ] );

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
	 * @param ZoneGroup $scansZoneGroup
	 * @return list<SummaryRow>
	 */
	private function buildSummaryRowsFromZoneGroup( array $scansZoneGroup ) :array {
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
		}, $scansZoneGroup[ 'items' ] ) );
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
			$row = $this->buildDetailRow(
				$item[ 'label' ],
				$item[ 'description' ],
				$status,
				$item[ 'count' ],
				$status === 'neutral' ? 'info' : $status,
				$this->buildMaintenanceRailActions( $item[ 'cta' ] ),
				null,
				null,
				__( 'Needs attention', 'wp-simple-firewall' )
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
	 * @param array<string,mixed> $action
	 * @return list<array<string,mixed>>
	 */
	private function buildMaintenanceRailActions( array $action ) :array {
		if ( $action === [] ) {
			return [];
		}

		$attributes = [];
		if ( isset( $action[ 'target' ] ) && $action[ 'target' ] !== '' ) {
			$attributes[ 'target' ] = $action[ 'target' ];
		}

		return [
			[
				'type'       => 'navigate',
				'label'      => $action[ 'label' ],
				'href'       => $action[ 'href' ],
				'icon'       => 'bi bi-arrow-right-circle-fill',
				'attributes' => $attributes,
			],
		];
	}

	/**
	 * @param array{count:int,status:string} $maintenanceMetrics
	 */
	private function maintenanceMetricsStatus( array $maintenanceMetrics ) :string {
		return StatusPriority::normalize( $maintenanceMetrics[ 'status' ], 'good' );
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
}
