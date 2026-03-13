<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueuePayload;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueueDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

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
 *   items:list<array<string,mixed>>,
 *   assessment_rows:list<AssessmentRow>,
 *   maintenance_detail_groups?:list<array{status:string,rows:list<array<string,mixed>>}>
 * }
 * @phpstan-type ScansResultsContract array{
 *   strings:array<string,string>,
 *   vars:array<string,mixed>,
 *   content:array<string,mixed>
 * }
 */
class PageActionsQueueLanding extends PageModeLandingBase {

	public const SLUG = 'plugin_admin_page_actions_queue_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/actions_queue_landing.twig';
	private ?array $needsAttentionPayload = null;
	private ?array $landingViewDataCache = null;
	private ?string $activeZoneCache = null;
	private ?array $scansResultsRenderDataCache = null;
	private ?array $assessmentRowsByZoneCache = null;

	protected function getLandingTitle() :string {
		return __( 'Actions Queue', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Review active issues and run the next action quickly.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'shield-shaded';
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_ACTIONS;
	}

	protected function isLandingInteractive() :bool {
		return true;
	}

	protected function getLandingFlags() :array {
		return [
			'queue_is_empty' => !$this->shouldRenderScansResultsShell(),
		];
	}

	protected function getLandingHrefs() :array {
		$con = self::con();
		return [
			'scan_results' => $con->plugin_urls->actionsQueueScans(),
			'wp_updates'   => Services::WpGeneral()->getAdminUrl_Updates(),
		];
	}

	protected function getLandingStrings() :array {
		$zones = $this->getZonesIndexed();
		$needsAttentionStrings = $this->getNeedsAttentionStrings();
		return [
			'status_action_required'   => __( 'Action Required', 'wp-simple-firewall' ),
			'status_all_clear'         => __( 'All Clear', 'wp-simple-firewall' ),
			'severity_strip_label'     => __( 'Queue Status', 'wp-simple-firewall' ),
			'all_clear_title'          => $needsAttentionStrings[ 'all_clear_title' ],
			'all_clear_subtitle'       => $needsAttentionStrings[ 'all_clear_subtitle' ],
			'all_clear_icon_class'     => $needsAttentionStrings[ 'all_clear_icon_class' ],
			'zone_scans'               => $zones[ 'scans' ][ 'label' ],
			'zone_maintenance'         => $zones[ 'maintenance' ][ 'label' ],
			'pane_loading'             => __( 'Loading scan details...', 'wp-simple-firewall' ),
			'pane_load_error'          => __( 'Unable to load these scan details. Please try again.', 'wp-simple-firewall' ),
		];
	}

	protected function getLandingTiles() :array {
		return \array_map(
			static fn( array $zoneTile ) :array => [
				'key'          => $zoneTile[ 'key' ],
				'panel_target' => $zoneTile[ 'panel_target' ],
				'is_enabled'   => $zoneTile[ 'is_enabled' ],
				'is_disabled'  => $zoneTile[ 'is_disabled' ],
			],
			$this->getZoneTiles()
		);
	}

	protected function getLandingPanel() :array {
		return [
			'active_target' => $this->getActiveZone(),
		];
	}

	protected function getLandingVars() :array {
		$viewData = $this->getLandingViewData();
		return [
			'severity_strip' => $viewData[ 'severity_strip' ],
			'zone_tiles'     => $viewData[ 'zone_tiles' ],
			'all_clear'      => $viewData[ 'all_clear' ],
			'scans_results'  => $this->shouldRenderScansResultsShell()
				? $this->getScansResultsRenderData()
				: $this->buildEmptyScansResultsContract(),
		];
	}

	/**
	 * @return array<string,string>
	 */
	private function getNeedsAttentionStrings() :array {
		return NeedsAttentionQueuePayload::strings( $this->getNeedsAttentionPayload(), [
			'all_clear_title'      => __( 'All security zones are clear', 'wp-simple-firewall' ),
			'all_clear_subtitle'   => __( 'Shield is actively protecting your site. Nothing requires your action.', 'wp-simple-firewall' ),
			'all_clear_icon_class' => $this->buildLandingIconClass( 'shield-check' ),
		] );
	}

	/**
	 * @return QueueSummary
	 */
	private function getQueueSummary() :array {
		return $this->getLandingViewData()[ 'summary' ];
	}

	private function shouldRenderScansResultsShell() :bool {
		if ( $this->getQueueSummary()[ 'has_items' ] ) {
			return true;
		}

		foreach ( $this->getZoneTiles() as $zoneTile ) {
			if ( $zoneTile[ 'key' ] === 'maintenance' && !empty( $zoneTile[ 'items' ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<string,ZoneGroup>
	 */
	private function getZonesIndexed() :array {
		return $this->getLandingViewData()[ 'zones_indexed' ];
	}

	/**
	 * @return list<ZoneTile>
	 */
	private function getZoneTiles() :array {
		return $this->getLandingViewData()[ 'zone_tiles' ];
	}

	private function getActiveZone() :string {
		if ( $this->activeZoneCache === null ) {
			$requestedZone = sanitize_key( $this->getTextInputFromRequestOrActionData( 'zone' ) );
			$enabledZones = \array_column(
				\array_filter(
					$this->getZoneTiles(),
					static fn( array $zoneTile ) :bool => $zoneTile[ 'is_enabled' ]
				),
				'key'
			);
			$this->activeZoneCache = \in_array( $requestedZone, $enabledZones, true )
				? $requestedZone
				: '';
		}
		return $this->activeZoneCache;
	}

	private function getScansResultsRenderData() :array {
		if ( $this->scansResultsRenderDataCache === null ) {
			$this->scansResultsRenderDataCache = $this->buildScansResultsRenderData();
		}
		return $this->scansResultsRenderDataCache;
	}

	/**
	 * @return ScansResultsContract
	 */
	private function buildEmptyScansResultsContract() :array {
		return [
			'strings' => [
				'pane_loading' => __( 'Loading scan details...', 'wp-simple-firewall' ),
				'no_issues'    => __( 'No issues found in this section.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'rail'            => [],
				'rail_tabs'       => [],
				'metrics_action'  => [],
				'preload_action'  => [],
				'summary_rows'    => [],
				'assessment_rows' => [],
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
	 * @return ScansResultsContract
	 */
	protected function buildScansResultsRenderData() :array {
		return ( new ActionsQueueScanRailBuilder() )->buildFromLandingViewData(
			$this->getLandingViewData(),
			( new ActionsQueueScanRailMetricsBuilder() )->build( $this->getNeedsAttentionPayload() )
		);
	}

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
	private function getLandingViewData() :array {
		if ( $this->landingViewDataCache === null ) {
			$this->landingViewDataCache = ( new ActionsQueueLandingViewBuilder() )
				->build( $this->getNeedsAttentionPayload(), $this->getAssessmentRowsByZone() );
		}
		return $this->landingViewDataCache;
	}

	/**
	 * @return AssessmentRowsByZone
	 */
	private function getAssessmentRowsByZone() :array {
		if ( $this->assessmentRowsByZoneCache === null ) {
			$this->assessmentRowsByZoneCache = $this->buildAssessmentRowsByZone();
		}
		return $this->assessmentRowsByZoneCache;
	}

	/**
	 * @return AssessmentRowsByZone
	 */
	protected function buildAssessmentRowsByZone() :array {
		$builder = new ActionsQueueLandingAssessmentBuilder();

		return [
			'scans'       => $builder->buildForZone( 'scans' ),
			'maintenance' => $builder->buildForZone( 'maintenance' ),
		];
	}

	/**
	 * @return array{
	 *   render_data:array{
	 *     flags:array{has_items:bool},
	 *     strings:array{
	 *       all_clear_title:string,
	 *       all_clear_subtitle:string,
	 *       status_strip_subtext:string,
	 *       all_clear_icon_class:string
	 *     }
	 *   }
	 * }
	 */
	private function getNeedsAttentionPayload() :array {
		if ( $this->needsAttentionPayload === null ) {
			$this->needsAttentionPayload = [
				'render_data' => $this->buildNeedsAttentionRenderData(),
			];
		}
		return $this->needsAttentionPayload;
	}

	protected function buildNeedsAttentionRenderData() :array {
		return ( new NeedsAttentionQueueDataBuilder() )->build( [
			'compact_all_clear' => true,
		] );
	}
}
