<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-import-type AttentionItem from BuildAttentionItems
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
 * @phpstan-import-type AssessmentRow from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type AssessmentRowsByZone from ActionsQueueLandingAssessmentBuilder
 * @phpstan-type QueueSummary array{
 *   has_items:bool,
 *   total_items:int,
 *   severity:string,
 *   icon_class:string,
 *   subtext:string
 * }
 * @phpstan-type ZoneGroup array{
 *   slug:string,
 *   label:string,
 *   icon_class:string,
 *   severity:string,
 *   total_issues:int,
 *   items:list<AttentionItem>
 * }
 * @phpstan-type ZoneTile array{
 *   key:string,
 *   panel_target:string,
 *   is_enabled:bool,
 *   is_disabled:bool,
 *   has_issues:bool,
 *   has_assessments:bool,
 *   has_panel_content:bool,
 *   label:string,
 *   icon_class:string,
 *   status:string,
 *   status_label:string,
 *   total_issues:int,
 *   critical_count:int,
 *   warning_count:int,
 *   summary_text:string,
 *   items:list<array<string,mixed>>,
 *   assessment_rows:list<AssessmentRow>,
 *   maintenance_detail_groups?:list<array{status:string,rows:list<array<string,mixed>>}>
 * }
 * @phpstan-type LandingViewData array{
 *   summary:QueueSummary,
 *   zones_indexed:array<string,ZoneGroup>,
 *   zone_tiles:list<ZoneTile>,
 *   status_overview:array{
 *     severity:string,
 *     label:string,
 *     icon_class:string,
 *     summary_text:string,
 *     subtext:string,
 *     total_items:int,
 *     critical_count:int,
 *     warning_count:int
 *   }
 * }
 */
class ActionsQueueLandingViewBuilder {

	use PluginControllerConsumer;
	use StandardStatusMapping;

	/**
	 * @param AttentionQuery $attentionQuery
	 * @param AssessmentRowsByZone $assessmentRowsByZone
	 * @return LandingViewData
	 */
	public function build( array $attentionQuery, array $assessmentRowsByZone = [
		'scans' => [],
		'maintenance' => [],
	], string $summarySubtext = '' ) :array {
		$summary = $this->extractQueueSummary( $attentionQuery, $summarySubtext );
		$zonesIndexed = $this->buildZonesIndexed( $attentionQuery );
		$zoneTiles = $this->buildZoneTiles( $zonesIndexed, $assessmentRowsByZone );

		return [
			'summary'        => $summary,
			'zones_indexed'  => $zonesIndexed,
			'zone_tiles'     => $zoneTiles,
			'status_overview' => $this->buildStatusOverviewContract( $summary, $zoneTiles ),
		];
	}

	/**
	 * @param AttentionQuery $attentionQuery
	 * @return QueueSummary
	 */
	private function extractQueueSummary( array $attentionQuery, string $summarySubtext ) :array {
		$hasItems = !$attentionQuery[ 'summary' ][ 'is_all_clear' ];

		return [
			'has_items'   => $hasItems,
			'total_items' => $attentionQuery[ 'summary' ][ 'total' ],
			'severity'    => $attentionQuery[ 'summary' ][ 'severity' ],
			'icon_class'  => self::con()->svgs->iconClass( $hasItems ? 'exclamation-triangle-fill' : 'shield-check' ),
			'subtext'     => $summarySubtext,
		];
	}

	/**
	 * @param AttentionQuery $attentionQuery
	 * @return array<string,ZoneGroup>
	 */
	private function buildZonesIndexed( array $attentionQuery ) :array {
		$zones = [];
		foreach ( $this->getZoneDefinitions() as $zoneDefinition ) {
			$slug = $zoneDefinition[ 'slug' ];
			$zones[ $slug ] = [
				'slug'         => $slug,
				'label'        => $zoneDefinition[ 'label' ],
				'icon_class'   => self::con()->svgs->iconClass( $zoneDefinition[ 'icon' ] ),
				'severity'     => $attentionQuery[ 'groups' ][ $slug ][ 'severity' ],
				'total_issues' => $attentionQuery[ 'groups' ][ $slug ][ 'total' ],
				'items'        => $attentionQuery[ 'groups' ][ $slug ][ 'items' ],
			];
		}

		return $zones;
	}

	/**
	 * @param array<string,ZoneGroup> $zonesIndexed
	 * @param AssessmentRowsByZone $assessmentRowsByZone
	 * @return list<ZoneTile>
	 */
	private function buildZoneTiles( array $zonesIndexed, array $assessmentRowsByZone ) :array {
		return \array_map(
			function ( array $zone ) use ( $assessmentRowsByZone ) :array {
				$items = $zone[ 'slug' ] === 'maintenance'
					? ( new MaintenanceQueueItemDisplayNormalizer() )->normalizeAll( $zone[ 'items' ] )
					: $zone[ 'items' ];
				$countBySeverity = $this->countsFromItems( $items );
				$totalIssues = $zone[ 'total_issues' ];
				$assessmentRows = $zone[ 'slug' ] === 'maintenance'
					? $this->filterMaintenanceAssessmentRows( $assessmentRowsByZone[ $zone[ 'slug' ] ], $items )
					: $assessmentRowsByZone[ $zone[ 'slug' ] ];
				$hasIssues = $totalIssues > 0;
				$hasAssessments = !empty( $assessmentRows );
				$hasPanelContent = $hasIssues || $hasAssessments || !empty( $items );

				$tile = [
					'key'               => $zone[ 'slug' ],
					'panel_target'      => $zone[ 'slug' ],
					'is_enabled'        => $hasPanelContent,
					'is_disabled'       => !$hasPanelContent,
					'has_issues'        => $hasIssues,
					'has_assessments'   => $hasAssessments,
					'has_panel_content' => $hasPanelContent,
					'label'             => $zone[ 'label' ],
					'icon_class'        => $zone[ 'icon_class' ],
					'status'            => $zone[ 'severity' ],
					'status_label'      => $this->standardStatusLabel( $zone[ 'severity' ] ),
					'total_issues'      => $totalIssues,
					'critical_count'    => $countBySeverity[ 'critical' ],
					'warning_count'     => $countBySeverity[ 'warning' ],
					'summary_text'      => $this->buildZoneSummaryText( $totalIssues, $countBySeverity ),
					'items'             => $items,
					'assessment_rows'   => $assessmentRows,
				];

				if ( $zone[ 'slug' ] === 'maintenance' ) {
					$tile[ 'maintenance_detail_groups' ] = ( new StatusDetailGroupsBuilder() )
						->buildForMaintenance( $items, $assessmentRows );
				}

				return $tile;
			},
			\array_values( $zonesIndexed )
		);
	}

	/**
	 * @param list<AssessmentRow> $assessmentRows
	 * @param list<array<string,mixed>> $items
	 * @return list<AssessmentRow>
	 */
	private function filterMaintenanceAssessmentRows( array $assessmentRows, array $items ) :array {
		$itemKeys = \array_fill_keys( \array_column( $items, 'key' ), true );

		return \array_values( \array_filter(
			$assessmentRows,
			static fn( array $row ) :bool => $row[ 'status' ] !== 'good' || !isset( $itemKeys[ $row[ 'key' ] ] )
		) );
	}

	/**
	 * @param QueueSummary $summary
	 * @param list<ZoneTile> $zoneTiles
	 * @return array{
	 *   severity:string,
	 *   label:string,
	 *   icon_class:string,
	 *   summary_text:string,
	 *   subtext:string,
	 *   total_items:int,
	 *   critical_count:int,
	 *   warning_count:int
	 * }
	 */
	private function buildStatusOverviewContract( array $summary, array $zoneTiles ) :array {
		$severityTotals = $this->countsFromZoneGroups( \array_map(
			static fn( array $tile ) :array => [
				'slug'         => $tile[ 'key' ],
				'total_issues' => $tile[ 'total_issues' ],
				'items'        => $tile[ 'items' ],
			],
			$zoneTiles
		) );
		$criticalCount = $severityTotals[ 'critical' ];
		$warningCount = $severityTotals[ 'warning' ];

		return [
			'severity'       => $summary[ 'severity' ],
			'label'          => $summary[ 'has_items' ]
				? __( 'Action Required', 'wp-simple-firewall' )
				: __( 'All Clear', 'wp-simple-firewall' ),
			'icon_class'     => $summary[ 'icon_class' ],
			'summary_text'   => $summary[ 'has_items' ]
				? sprintf(
					__( '%1$s critical - %2$s warnings - %3$s items total', 'wp-simple-firewall' ),
					$criticalCount,
					$warningCount,
					$summary[ 'total_items' ]
				)
				: __( 'No actions currently require your attention.', 'wp-simple-firewall' ),
			'subtext'        => $summary[ 'subtext' ],
			'total_items'    => $summary[ 'total_items' ],
			'critical_count' => $criticalCount,
			'warning_count'  => $warningCount,
		];
	}

	/**
	 * @param list<AttentionItem>|list<array<string,mixed>> $items
	 * @return array{critical:int,warning:int}
	 */
	private function countsFromItems( array $items ) :array {
		$counts = [
			'critical' => 0,
			'warning'  => 0,
		];
		foreach ( $items as $item ) {
			if ( isset( $counts[ $item[ 'severity' ] ] ) ) {
				$counts[ $item[ 'severity' ] ] += $item[ 'count' ];
			}
		}
		return $counts;
	}

	/**
	 * @param list<array{slug:string,total_issues:int,items:list<array<string,mixed>>}> $zoneGroups
	 * @return array{critical:int,warning:int}
	 */
	private function countsFromZoneGroups( array $zoneGroups ) :array {
		$totals = [
			'critical' => 0,
			'warning'  => 0,
		];
		foreach ( $zoneGroups as $group ) {
			$counts = $this->countsFromItems( $group[ 'items' ] );
			$totals[ 'critical' ] += $counts[ 'critical' ];
			$totals[ 'warning' ] += $counts[ 'warning' ];
		}
		return $totals;
	}

	/**
	 * @param array{critical:int,warning:int} $severityCounts
	 */
	private function buildZoneSummaryText( int $totalIssues, array $severityCounts ) :string {
		if ( $totalIssues < 1 ) {
			return __( 'All clear', 'wp-simple-firewall' );
		}
		if ( $severityCounts[ 'critical' ] > 0 ) {
			return sprintf(
				_n( '%1$s issue - %2$s critical', '%1$s issues - %2$s critical', $totalIssues, 'wp-simple-firewall' ),
				$totalIssues,
				$severityCounts[ 'critical' ]
			);
		}
		return sprintf(
			_n( '%1$s issue', '%1$s issues', $totalIssues, 'wp-simple-firewall' ),
			$totalIssues
		);
	}

	/**
	 * @return array<string,array{
	 *   slug:string,
	 *   label:string,
	 *   icon:string
	 * }>
	 */
	private function getZoneDefinitions() :array {
		return PluginNavs::actionsLandingZoneDefinitions();
	}
}
