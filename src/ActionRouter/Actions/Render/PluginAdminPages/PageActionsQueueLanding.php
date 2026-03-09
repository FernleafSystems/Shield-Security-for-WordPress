<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueue;
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
		return [
			'status_action_required'   => __( 'Action Required', 'wp-simple-firewall' ),
			'status_all_clear'         => __( 'All Clear', 'wp-simple-firewall' ),
			'severity_strip_label'     => __( 'Queue Status', 'wp-simple-firewall' ),
			'all_clear_title'          => $this->getNeedsAttentionString( 'all_clear_title' ),
			'all_clear_subtitle'       => $this->getNeedsAttentionString( 'all_clear_subtitle' ),
			'all_clear_icon_class'     => $this->getNeedsAttentionString( 'all_clear_icon_class' ),
			'zone_scans'               => (string)( $zones[ 'scans' ][ 'label' ] ?? __( 'Scans', 'wp-simple-firewall' ) ),
			'zone_maintenance'         => (string)( $zones[ 'maintenance' ][ 'label' ] ?? __( 'Maintenance', 'wp-simple-firewall' ) ),
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
		$scansZone = $this->getZonesIndexed()[ 'scans' ] ?? [ 'total_issues' => 0 ];
		$hasScansIssues = $scansZone[ 'total_issues' ] > 0;
		return [
			'severity_strip' => $viewData[ 'severity_strip' ],
			'zone_tiles'     => $zoneTiles,
			'all_clear'      => $viewData[ 'all_clear' ],
			'scans_results'  => $hasScansIssues
				? $this->getScansResultsRenderData()
				: [],
		];
	}

	/**
	 * @return array{
	 *   flags:array{has_items:bool},
	 *   strings:array{
	 *     all_clear_title:string,
	 *     all_clear_subtitle:string,
	 *     status_strip_subtext:string,
	 *     all_clear_icon_class:string
	 *   }
	 * }
	 */
	private function getNeedsAttentionRenderData() :array {
		return $this->getNeedsAttentionPayload()[ 'render_data' ];
	}

	private function getNeedsAttentionString( string $key ) :string {
		$strings = $this->getNeedsAttentionRenderData()[ 'strings' ] ?? [];
		$defaults = [
			'all_clear_title'      => __( 'All security zones are clear', 'wp-simple-firewall' ),
			'all_clear_subtitle'   => __( 'Shield is actively protecting your site. Nothing requires your action.', 'wp-simple-firewall' ),
			'all_clear_icon_class' => $this->buildLandingIconClass( 'shield-check' ),
		];
		return (string)( $strings[ $key ] ?? ( $defaults[ $key ] ?? '' ) );
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

	protected function buildScansResultsRenderData() :array {
		return ( new ScansResultsViewBuilder() )->build();
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
	 *   render_output:string,
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
			$this->needsAttentionPayload = self::con()
											->action_router
											->action( NeedsAttentionQueue::class, [
												'compact_all_clear' => true,
											] )
											->payload();
		}
		return $this->needsAttentionPayload;
	}
}
