<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
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
			'scan_results'   => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
			'wp_updates'     => Services::WpGeneral()->getAdminUrl_Updates(),
			'manage_plugins' => Services::WpGeneral()->getAdminUrl_Plugins(),
		];
	}

	protected function getLandingStrings() :array {
		$zones = $this->getZonesIndexed();
		return [
			'status_action_required'   => __( 'Action Required', 'wp-simple-firewall' ),
			'status_all_clear'         => __( 'All Clear', 'wp-simple-firewall' ),
			'severity_strip_label'     => __( 'Queue Status', 'wp-simple-firewall' ),
			'panel_scan_summary_tab'   => __( 'Summary', 'wp-simple-firewall' ),
			'panel_scan_results_tab'   => __( 'Scan Results', 'wp-simple-firewall' ),
			'panel_scan_results_link'  => __( 'Open full scan results page', 'wp-simple-firewall' ),
			'panel_maintenance_actions' => __( 'Maintenance Actions', 'wp-simple-firewall' ),
			'panel_wp_updates'         => __( 'Open WordPress Updates', 'wp-simple-firewall' ),
			'panel_manage_plugins'     => __( 'Open Plugins', 'wp-simple-firewall' ),
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
		$scansZone = $this->getZonesIndexed()[ 'scans' ] ?? [ 'total_issues' => 0 ];
		return [
			'severity_strip' => $viewData[ 'severity_strip' ],
			'zone_tiles'     => $viewData[ 'zone_tiles' ],
			'all_clear'      => $viewData[ 'all_clear' ],
			'scans_results'  => $scansZone[ 'total_issues' ] > 0
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
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   total_issues:int,
	 *   critical_count:int,
	 *   warning_count:int,
	 *   summary_text:string,
	 *   items:list<array<string,mixed>>
	 * }>
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
			// TODO: Keep server-side embedding for now. Switch this to on-demand render only if this panel becomes materially heavy.
			$this->scansResultsRenderDataCache = self::con()
												 ->action_router
												 ->action( PageScansResults::class, [
													 Constants::NAV_ID     => PluginNavs::NAV_SCANS,
													 Constants::NAV_SUB_ID => PluginNavs::SUBNAV_SCANS_RESULTS,
												 ] )
												 ->payload()[ 'render_data' ] ?? [];
		}
		return $this->scansResultsRenderDataCache;
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
	 *     label:string,
	 *     icon_class:string,
	 *     status:string,
	 *     status_label:string,
	 *     total_issues:int,
	 *     critical_count:int,
	 *     warning_count:int,
	 *     summary_text:string,
	 *     items:list<array<string,mixed>>
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
				->build( $this->getNeedsAttentionPayload() );
		}
		return $this->landingViewDataCache;
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
