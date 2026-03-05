<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueue;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueuePayload;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

class PageActionsQueueLanding extends PageModeLandingBase {

	public const SLUG = 'plugin_admin_page_actions_queue_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/actions_queue_landing.twig';
	private ?array $needsAttentionPayload = null;
	private ?array $zoneTilesCache = null;
	private ?array $zonesIndexedCache = null;
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
		$queueStrings = $this->getNeedsAttentionStrings();
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
			'all_clear_title'          => $queueStrings[ 'all_clear_title' ],
			'all_clear_subtitle'       => $queueStrings[ 'all_clear_subtitle' ],
			'all_clear_icon_class'     => $queueStrings[ 'all_clear_icon_class' ],
			'zone_scans'               => $zones[ 'scans' ][ 'label' ],
			'zone_maintenance'         => $zones[ 'maintenance' ][ 'label' ],
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
		$scansZone = $this->getZonesIndexed()[ 'scans' ];
		return [
			'severity_strip' => $this->buildSeverityStripContract(),
			'zone_tiles'     => $this->getZoneTiles(),
			'all_clear'      => $this->buildAllClearContract(),
			'scans_results'  => $scansZone[ 'total_issues' ] > 0
				? $this->getScansResultsRenderData()
				: [],
		];
	}

	/**
	 * @return array{
	 *   all_clear_title:string,
	 *   all_clear_subtitle:string,
	 *   all_clear_icon_class:string
	 * }
	 */
	private function getNeedsAttentionStrings() :array {
		return NeedsAttentionQueuePayload::strings(
			$this->getNeedsAttentionPayload(),
			[
				'all_clear_title'      => __( 'All security zones are clear', 'wp-simple-firewall' ),
				'all_clear_subtitle'   => __( 'Shield is actively protecting your site. Nothing requires your action.', 'wp-simple-firewall' ),
				'all_clear_icon_class' => $this->buildLandingIconClass( 'shield-check' ),
			]
		);
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
		return NeedsAttentionQueuePayload::summary(
			$this->getNeedsAttentionPayload(),
			[
				'has_items'   => false,
				'total_items' => 0,
				'severity'    => 'good',
				'icon_class'  => $this->buildLandingIconClass( 'shield-check' ),
				'subtext'     => '',
			]
		);
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
		if ( $this->zonesIndexedCache === null ) {
			$zones = [
				'scans'       => [
					'slug'         => 'scans',
					'label'        => __( 'Scans', 'wp-simple-firewall' ),
					'icon_class'   => $this->buildLandingIconClass( 'shield-exclamation' ),
					'severity'     => 'good',
					'total_issues' => 0,
					'items'        => [],
				],
				'maintenance' => [
					'slug'         => 'maintenance',
					'label'        => __( 'Maintenance', 'wp-simple-firewall' ),
					'icon_class'   => $this->buildLandingIconClass( 'wrench' ),
					'severity'     => 'good',
					'total_issues' => 0,
					'items'        => [],
				],
			];

			foreach ( NeedsAttentionQueuePayload::zoneGroups( $this->getNeedsAttentionPayload() ) as $zoneGroup ) {
				$slug = sanitize_key( (string)( $zoneGroup[ 'slug' ] ?? '' ) );
				if ( !isset( $zones[ $slug ] ) ) {
					continue;
				}

				$label = (string)( $zoneGroup[ 'label' ] ?? '' );
				$iconClass = (string)( $zoneGroup[ 'icon_class' ] ?? '' );
				$zones[ $slug ] = [
					'slug'         => $slug,
					'label'        => empty( $label ) ? $zones[ $slug ][ 'label' ] : $label,
					'icon_class'   => empty( $iconClass ) ? $zones[ $slug ][ 'icon_class' ] : $iconClass,
					'severity'     => (string)( $zoneGroup[ 'severity' ] ?? 'good' ),
					'total_issues' => (int)( $zoneGroup[ 'total_issues' ] ?? 0 ),
					'items'        => \is_array( $zoneGroup[ 'items' ] ?? null ) ? \array_values( $zoneGroup[ 'items' ] ) : [],
				];
			}

			$this->zonesIndexedCache = $zones;
		}

		return $this->zonesIndexedCache;
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
		if ( $this->zoneTilesCache === null ) {
			$this->zoneTilesCache = \array_map(
				function ( array $zone ) :array {
					$countBySeverity = NeedsAttentionQueuePayload::countsFromItems( $zone[ 'items' ] );
					$totalIssues = $zone[ 'total_issues' ];
					$isEnabled = $totalIssues > 0;

					return [
						'key'            => $zone[ 'slug' ],
						'panel_target'   => $zone[ 'slug' ],
						'is_enabled'     => $isEnabled,
						'is_disabled'    => !$isEnabled,
						'label'          => $zone[ 'label' ],
						'icon_class'     => $zone[ 'icon_class' ],
						'status'         => $zone[ 'severity' ],
						'status_label'   => $this->statusLabel( $zone[ 'severity' ] ),
						'total_issues'   => $totalIssues,
						'critical_count' => $countBySeverity[ 'critical' ],
						'warning_count'  => $countBySeverity[ 'warning' ],
						'summary_text'   => $this->buildZoneSummaryText( $totalIssues, $countBySeverity ),
						'items'          => $zone[ 'items' ],
					];
				},
				\array_values( $this->getZonesIndexed() )
			);
		}
		return $this->zoneTilesCache;
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

	private function statusLabel( string $status ) :string {
		switch ( $status ) {
			case 'critical':
				return __( 'Critical', 'wp-simple-firewall' );
			case 'warning':
				return __( 'Warning', 'wp-simple-firewall' );
			default:
				return __( 'Good', 'wp-simple-firewall' );
		}
	}

	/**
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
	private function buildSeverityStripContract() :array {
		$summary = $this->getQueueSummary();
		$counts = NeedsAttentionQueuePayload::countsFromZoneGroups( \array_values( $this->getZonesIndexed() ) );
		$criticalCount = $counts[ 'critical' ];
		$warningCount = $counts[ 'warning' ];

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
	 * @return array{
	 *   title:string,
	 *   subtitle:string,
	 *   icon_class:string,
	 *   zone_chips:list<array{slug:string,label:string,icon_class:string,severity:string}>
	 * }
	 */
	private function buildAllClearContract() :array {
		$checkIconClass = $this->buildLandingIconClass( 'check-circle-fill' );
		$queueStrings = $this->getNeedsAttentionStrings();

		return [
			'title'      => $queueStrings[ 'all_clear_title' ],
			'subtitle'   => $queueStrings[ 'all_clear_subtitle' ],
			'icon_class' => $queueStrings[ 'all_clear_icon_class' ],
			'zone_chips' => \array_map(
				static fn( array $zone ) :array => [
					'slug'       => $zone[ 'slug' ],
					'label'      => $zone[ 'label' ],
					'icon_class' => $checkIconClass,
					'severity'   => 'good',
				],
				\array_values( $this->getZonesIndexed() )
			),
		];
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
