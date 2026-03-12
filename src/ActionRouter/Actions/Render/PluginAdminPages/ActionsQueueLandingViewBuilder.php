<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueuePayload;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-import-type QueueSummary from NeedsAttentionQueuePayload
 * @phpstan-import-type QueueItem from NeedsAttentionQueuePayload
 * @phpstan-import-type ZoneGroup from NeedsAttentionQueuePayload
 * @phpstan-type AssessmentRow array{
 *   key:string,
 *   label:string,
 *   description:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string
 * }
 * @phpstan-type AssessmentRowsByZone array{
 *   scans:list<AssessmentRow>,
 *   maintenance:list<AssessmentRow>
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
 *   items:list<QueueItem>,
 *   assessment_rows:list<AssessmentRow>
 * }
 */
class ActionsQueueLandingViewBuilder {

	use PluginControllerConsumer;
	use StandardStatusMapping;

	/**
	 * @return array{
	 *   summary:QueueSummary,
	 *   zones_indexed:array<string,ZoneGroup>,
	 *   zone_tiles:list<ZoneTile>,
	 *   severity_strip:array{
	 *     severity:string,
	 *     label:string,
	 *     icon_class:string,
	 *     summary_text:string,
	 *     subtext:string,
	 *     total_items:int,
	 *     critical_count:int,
	 *     warning_count:int
	 *   },
	 *   all_clear:array{
	 *     title:string,
	 *     subtitle:string,
	 *     icon_class:string,
	 *     zone_chips:list<array{
	 *       slug:string,
	 *       label:string,
	 *       icon_class:string,
	 *       severity:string
	 *     }>
	 *   }
	 * }
	 */
	/**
	 * @param AssessmentRowsByZone $assessmentRowsByZone
	 */
	public function build( array $needsAttentionPayload, array $assessmentRowsByZone = [
		'scans' => [],
		'maintenance' => [],
	] ) :array {
		$summary = $this->extractQueueSummary( $needsAttentionPayload );
		$zonesIndexed = $this->buildZonesIndexed( $needsAttentionPayload );
		$zoneTiles = $this->buildZoneTiles( $zonesIndexed, $assessmentRowsByZone );

		return [
			'summary'        => $summary,
			'zones_indexed'  => $zonesIndexed,
			'zone_tiles'     => $zoneTiles,
			'severity_strip' => $this->buildSeverityStripContract( $summary, $zoneTiles ),
			'all_clear'      => $this->buildAllClearContract( $needsAttentionPayload, $zonesIndexed ),
		];
	}

	/**
	 * @return QueueSummary
	 */
	private function extractQueueSummary( array $needsAttentionPayload ) :array {
		return NeedsAttentionQueuePayload::summary(
			$needsAttentionPayload,
			[
				'has_items'   => false,
				'total_items' => 0,
				'severity'    => 'good',
				'icon_class'  => self::con()->svgs->iconClass( 'shield-check' ),
				'subtext'     => '',
			]
		);
	}

	/**
	 * @return array<string,ZoneGroup>
	 */
	private function buildZonesIndexed( array $needsAttentionPayload ) :array {
		$zones = [];
		foreach ( $this->getZoneDefinitions() as $zoneDefinition ) {
			$zones[ $zoneDefinition[ 'slug' ] ] = [
				'slug'         => $zoneDefinition[ 'slug' ],
				'label'        => $zoneDefinition[ 'label' ],
				'icon_class'   => self::con()->svgs->iconClass( $zoneDefinition[ 'icon' ] ),
				'severity'     => 'good',
				'total_issues' => 0,
				'items'        => [],
			];
		}

		foreach ( NeedsAttentionQueuePayload::zoneGroups( $needsAttentionPayload ) as $zoneGroup ) {
			$slug = sanitize_key( $zoneGroup[ 'slug' ] );
			if ( !isset( $zones[ $slug ] ) ) {
				continue;
			}

			$zones[ $slug ] = [
				'slug'         => $slug,
				'label'        => $zoneGroup[ 'label' ] !== '' ? $zoneGroup[ 'label' ] : $zones[ $slug ][ 'label' ],
				'icon_class'   => $zoneGroup[ 'icon_class' ] !== '' ? $zoneGroup[ 'icon_class' ] : $zones[ $slug ][ 'icon_class' ],
				'severity'     => $zoneGroup[ 'severity' ],
				'total_issues' => $zoneGroup[ 'total_issues' ],
				'items'        => $zoneGroup[ 'items' ],
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
				$countBySeverity = NeedsAttentionQueuePayload::countsFromItems( $zone[ 'items' ] );
				$totalIssues = $zone[ 'total_issues' ];
				$assessmentRows = $assessmentRowsByZone[ $zone[ 'slug' ] ];
				$hasIssues = $totalIssues > 0;
				$hasAssessments = !empty( $assessmentRows );
				$hasPanelContent = $hasIssues || $hasAssessments;

				return [
					'key'              => $zone[ 'slug' ],
					'panel_target'     => $zone[ 'slug' ],
					'is_enabled'       => $hasPanelContent,
					'is_disabled'      => !$hasPanelContent,
					'has_issues'       => $hasIssues,
					'has_assessments'  => $hasAssessments,
					'has_panel_content' => $hasPanelContent,
					'label'            => $zone[ 'label' ],
					'icon_class'       => $zone[ 'icon_class' ],
					'status'           => $zone[ 'severity' ],
					'status_label'     => $this->standardStatusLabel( $zone[ 'severity' ] ),
					'total_issues'     => $totalIssues,
					'critical_count'   => $countBySeverity[ 'critical' ],
					'warning_count'    => $countBySeverity[ 'warning' ],
					'summary_text'     => $this->buildZoneSummaryText( $totalIssues, $countBySeverity ),
					'items'            => $zone[ 'items' ],
					'assessment_rows'  => $assessmentRows,
				];
			},
			\array_values( $zonesIndexed )
		);
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
	private function buildSeverityStripContract( array $summary, array $zoneTiles ) :array {
		$severityTotals = NeedsAttentionQueuePayload::countsFromZoneGroups( \array_map(
				static fn( array $tile ) :array => [
					'slug'         => $tile[ 'key' ],
					'label'        => $tile[ 'label' ],
					'icon_class'   => $tile[ 'icon_class' ],
					'severity'     => $tile[ 'status' ],
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
			'subtext'        => (string)$summary[ 'subtext' ],
			'total_items'    => (int)$summary[ 'total_items' ],
			'critical_count' => $criticalCount,
			'warning_count'  => $warningCount,
		];
	}

	/**
	 * @param array<string,ZoneGroup> $zonesIndexed
	 * @return array{
	 *   title:string,
	 *   subtitle:string,
	 *   icon_class:string,
	 *   zone_chips:list<array{
	 *     slug:string,
	 *     label:string,
	 *     icon_class:string,
	 *     severity:string
	 *   }>
	 * }
	 */
	private function buildAllClearContract( array $needsAttentionPayload, array $zonesIndexed ) :array {
		$strings = NeedsAttentionQueuePayload::strings(
			$needsAttentionPayload,
			$this->buildAllClearStringDefaults()
		);
		$chipIconClass = self::con()->svgs->iconClass( 'check-circle-fill' );

		return [
			'title'      => $strings[ 'all_clear_title' ],
			'subtitle'   => $strings[ 'all_clear_subtitle' ],
			'icon_class' => $strings[ 'all_clear_icon_class' ],
			'zone_chips' => \array_map(
				static fn( array $zone ) :array => [
					'slug'       => $zone[ 'slug' ],
					'label'      => $zone[ 'label' ],
					'icon_class' => $chipIconClass,
					'severity'   => 'good',
				],
				\array_values( $zonesIndexed )
			),
		];
	}

	/**
	 * @return array<string,string>
	 */
	private function buildAllClearStringDefaults() :array {
		return [
			'all_clear_title'      => __( 'All security zones are clear', 'wp-simple-firewall' ),
			'all_clear_subtitle'   => __( 'Shield is actively protecting your site. Nothing requires your action.', 'wp-simple-firewall' ),
			'all_clear_icon_class' => self::con()->svgs->iconClass( 'shield-check' ),
		];
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
