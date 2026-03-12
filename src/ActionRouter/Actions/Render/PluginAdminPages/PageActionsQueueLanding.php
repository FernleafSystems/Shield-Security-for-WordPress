<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueuePayload;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueueDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

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
			'queue_is_empty' => !$this->getQueueSummary()[ 'has_items' ],
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
		$zoneTiles = $this->getZoneTilesForDisplay();
		return [
			'severity_strip' => $viewData[ 'severity_strip' ],
			'zone_tiles'     => $zoneTiles,
			'all_clear'      => $viewData[ 'all_clear' ],
			'scans_results'  => $this->buildScansResultsContract(
				$this->getQueueSummary()[ 'has_items' ] ? $this->getScansResultsRenderData() : []
			),
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
	 * @return array{
	 *   has_items:bool,
	 *   total_items:int,
	 *   severity:string,
	 *   icon_class:string,
	 *   subtext:string
	 * }
	 */
	private function getQueueSummary() :array {
		return $this->getLandingViewData()[ 'summary' ];
	}

	/**
	 * @return array<string,array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array{
	 *     key:string,
	 *     zone:string,
	 *     label:string,
	 *     count:int,
	 *     severity:string,
	 *     description:string,
	 *     href:string,
	 *     action:string
	 *   }>
	 * }>
	 */
	private function getZonesIndexed() :array {
		return $this->getLandingViewData()[ 'zones_indexed' ];
	}

	/**
	 * @return list<array{
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
	 *   assessment_rows:list<array{
	 *     key:string,
	 *     label:string,
	 *     description:string,
	 *     status:string,
	 *     status_label:string,
	 *     status_icon_class:string
	 *   }>,
	 *   maintenance_detail_groups?:list<array{status:string,rows:list<array<string,mixed>>}>
	 * }>
	 */
	private function getZoneTiles() :array {
		return $this->getLandingViewData()[ 'zone_tiles' ];
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function getZoneTilesForDisplay() :array {
		return \array_map(
			function ( array $zoneTile ) :array {
				$zoneTile[ 'items' ] = \array_map(
					fn( array $item ) :array => $this->normalizeZoneItemForDisplay( $item ),
					$zoneTile[ 'items' ] ?? []
				);
				if ( ( $zoneTile[ 'key' ] ?? '' ) === 'maintenance' ) {
					$zoneTile[ 'maintenance_detail_groups' ] = ( new StatusDetailGroupsBuilder() )->buildForMaintenance(
						\is_array( $zoneTile[ 'items' ] ?? null ) ? \array_values( $zoneTile[ 'items' ] ) : [],
						\is_array( $zoneTile[ 'assessment_rows' ] ?? null ) ? \array_values( $zoneTile[ 'assessment_rows' ] ) : []
					);
				}
				return $zoneTile;
			},
			$this->getZoneTiles()
		);
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
	 * @param array<string,mixed> $renderData
	 * @return array{
	 *   strings:array<string,string>,
	 *   vars:array<string,mixed>,
	 *   content:array<string,mixed>
	 * }
	 */
	private function buildScansResultsContract( array $renderData ) :array {
		$strings = \is_array( $renderData[ 'strings' ] ?? null ) ? $renderData[ 'strings' ] : [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$content = \is_array( $renderData[ 'content' ] ?? null ) ? $renderData[ 'content' ] : [];
		$contentSection = \is_array( $content[ 'section' ] ?? null ) ? $content[ 'section' ] : [];

		return [
			'strings' => \array_merge( [
				'pane_loading' => __( 'Loading scan details...', 'wp-simple-firewall' ),
				'no_issues'    => __( 'No issues found in this section.', 'wp-simple-firewall' ),
			], $strings ),
			'vars'    => \array_merge( [
				'rail'            => [],
				'rail_tabs'       => [],
				'metrics_action'  => [],
				'preload_action'  => [],
				'summary_rows'    => [],
				'assessment_rows' => [],
			], $vars ),
			'content' => \array_merge( $content, [
				'section' => \array_merge( [
					'wordpress'       => '',
					'plugins'         => '',
					'themes'          => '',
					'vulnerabilities' => '',
					'malware'         => '',
					'filelocker'      => '',
				], $contentSection ),
			] ),
		];
	}

	protected function buildScansResultsRenderData() :array {
		return ( new ActionsQueueScanRailBuilder() )->buildFromLandingData(
			$this->getNeedsAttentionPayload(),
			$this->getAssessmentRowsByZone()[ 'scans' ] ?? []
		);
	}

	/**
	 * @param array<string,mixed> $item
	 * @return array<string,mixed>
	 */
	private function normalizeZoneItemForDisplay( array $item ) :array {
		if ( ( $item[ 'zone' ] ?? '' ) !== 'maintenance' ) {
			return $item;
		}

		$href = (string)( $item[ 'href' ] ?? '' );
		$target = (string)( $item[ 'target' ] ?? '' );

		switch ( $item[ 'key' ] ?? '' ) {
			case 'wp_plugins_inactive':
				$item[ 'cta' ] = [
					'href'  => $href,
					'label' => __( 'Go to plugins', 'wp-simple-firewall' ),
				];
				break;
			case 'wp_themes_inactive':
				$item[ 'cta' ] = [
					'href'  => $href,
					'label' => __( 'Go to themes', 'wp-simple-firewall' ),
				];
				break;
			default:
				$action = (string)( $item[ 'action' ] ?? '' );
				if ( $href !== '' && $action !== '' ) {
					$item[ 'cta' ] = [
						'href'   => $href,
						'label'  => $action,
						'target' => $target,
					];
				}
				break;
		}

		return $item;
	}

	/**
	 * @return array{
	 *   summary:array{
	 *     has_items:bool,
	 *     total_items:int,
	 *     severity:string,
	 *     icon_class:string,
	 *     subtext:string
	 *   },
	 *   zones_indexed:array<string,array{
	 *     slug:string,
	 *     label:string,
	 *     icon_class:string,
	 *     severity:string,
	 *     total_issues:int,
	 *     items:list<array{
	 *       key:string,
	 *       zone:string,
	 *       label:string,
	 *       count:int,
	 *       severity:string,
	 *       description:string,
	 *       href:string,
	 *       action:string
	 *     }>
	 *   }>,
	 *   zone_tiles:list<array{
	 *     key:string,
	 *     panel_target:string,
	 *     is_enabled:bool,
	 *     is_disabled:bool,
	 *     has_issues:bool,
	 *     has_assessments:bool,
	 *     has_panel_content:bool,
	 *     label:string,
	 *     icon_class:string,
	 *     status:string,
	 *     status_label:string,
	 *     total_issues:int,
	 *     critical_count:int,
	 *     warning_count:int,
	 *     summary_text:string,
	 *     items:list<array<string,mixed>>,
	 *     assessment_rows:list<array{
	 *       key:string,
	 *       label:string,
	 *       description:string,
	 *       status:string,
	 *       status_label:string,
	 *       status_icon_class:string
	 *     }>
	 *   }>,
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
	 * @return array<string,list<array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 * }>>
	 */
	private function getAssessmentRowsByZone() :array {
		if ( $this->assessmentRowsByZoneCache === null ) {
			$this->assessmentRowsByZoneCache = $this->buildAssessmentRowsByZone();
		}
		return $this->assessmentRowsByZoneCache;
	}

	/**
	 * @return array<string,list<array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 * }>>
	 */
	protected function buildAssessmentRowsByZone() :array {
		return ( new ActionsQueueLandingAssessmentBuilder() )->build();
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
