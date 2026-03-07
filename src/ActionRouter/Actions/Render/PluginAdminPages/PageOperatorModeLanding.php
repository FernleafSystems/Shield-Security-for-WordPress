<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueue;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueuePayload;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\DashboardLiveMonitorPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\BuildZonePosture;

class PageOperatorModeLanding extends BaseRender {

	public const SLUG = 'plugin_admin_page_operator_mode_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/operator_mode_landing.twig';
	private const VALID_SEVERITIES = [
		'good',
		'warning',
		'critical',
	];

	protected function getRenderData() :array {
		$queuePayload = $this->getQueuePayload();
		$queueSummary = $this->getQueueSummary( $queuePayload );
		$queueZoneGroups = $this->getQueueZoneGroups( $queuePayload );
		$shieldStatus = $this->normalizeSeverity( $queueSummary[ 'severity' ] ?? 'good' );

		$configPercentage = $this->getZonePosture()[ 'percentage' ];
		$configPercentage = max( 0, min( 100, $configPercentage ) );
		$configTraffic = BuildZonePosture::trafficFromPercentage( $configPercentage );

		$sessionCount = $this->getInvestigateActiveSessionsCount();
		$reportsCount = $this->getGeneratedReportsCount();
		$actionsLane = $this->buildActionsLane( $queueSummary, $queueZoneGroups );
		$secondaryLanes = [
			$this->buildInvestigateLane( $sessionCount ),
			$this->buildConfigureLane( $configPercentage, $configTraffic ),
			$this->buildReportsLane( $reportsCount ),
		];

		return [
			'strings' => [
				'title'             => __( 'Actions Queue', 'wp-simple-firewall' ),
				'subtitle' => $this->buildShieldSubtitle( $queueSummary ),
				'actions_queue_cta' => __( 'View Actions Queue', 'wp-simple-firewall' ),
			],
			'vars' => [
				'shield_status'     => $shieldStatus,
				'shield_icon_class' => $this->buildShieldIconClass( $shieldStatus ),
				'lanes'             => [
					$actionsLane,
					...$secondaryLanes,
				],
				'actions_lane'      => $actionsLane,
				'secondary_lanes'   => $secondaryLanes,
				'actions_queue_rows' => $this->buildActionsQueueRows( $queueZoneGroups ),
				'live_monitor'      => $this->buildLiveMonitorVars(),
			],
		];
	}

	/**
	 * @return array{
	 *   components:list<array<string,mixed>>,
	 *   signals:list<array<string,mixed>>,
	 *   totals:array{score:int,max_weight:int,percentage:int,letter_score:string},
	 *   percentage:int,
	 *   severity:string,
	 *   status:string
	 * }
	 */
	protected function getZonePosture() :array {
		return ( new BuildZonePosture() )->build();
	}

	private function buildLiveMonitorVars() :array {
		try {
			$isCollapsed = ( new DashboardLiveMonitorPreference() )->isCollapsed();
		}
		catch ( \Throwable $e ) {
			$isCollapsed = false;
		}

		return [
			'is_collapsed' => $isCollapsed,
			'title'        => __( 'Live Monitor', 'wp-simple-firewall' ),
			'activity'     => __( 'WP Activity', 'wp-simple-firewall' ),
			'traffic'      => __( 'Live Traffic', 'wp-simple-firewall' ),
			'loading'      => __( 'Waiting for live updates...', 'wp-simple-firewall' ),
		];
	}

	private function getQueuePayload() :array {
		try {
			$payload = self::con()->action_router->action( NeedsAttentionQueue::class )->payload();
		}
		catch ( \Throwable $e ) {
			$payload = [];
		}
		return $payload;
	}

	/**
	 * @return array{has_items:bool,total_items:int,severity:string,icon_class:string,subtext:string}
	 */
	private function getQueueSummary( array $payload ) :array {
		return NeedsAttentionQueuePayload::summary( $payload, [
			'has_items'   => false,
			'total_items' => 0,
			'severity'    => 'good',
			'icon_class'  => self::con()->svgs->iconClass( 'shield-check' ),
			'subtext'     => '',
		] );
	}

	/**
	 * @return list<array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array<string,mixed>>
	 * }>
	 */
	private function getQueueZoneGroups( array $payload ) :array {
		return NeedsAttentionQueuePayload::zoneGroups( $payload );
	}

	private function normalizeSeverity( string $severity ) :string {
		$severity = sanitize_key( $severity );
		return \in_array( $severity, self::VALID_SEVERITIES, true ) ? $severity : 'good';
	}

	/**
	 * @param array{has_items:bool,total_items:int,severity:string,icon_class:string,subtext:string} $queueSummary
	 */
	private function buildShieldSubtitle( array $queueSummary ) :string {
		return $queueSummary[ 'has_items' ]
			? sprintf(
				_n( '%s issue needs your attention.', '%s issues need your attention.', $queueSummary[ 'total_items' ], 'wp-simple-firewall' ),
				$queueSummary[ 'total_items' ]
			)
			: __( 'Your site is protected. All systems operational.', 'wp-simple-firewall' );
	}

	private function buildShieldIconClass( string $shieldStatus ) :string {
		$iconMap = [
			'good'     => 'shield-shaded',
			'warning'  => 'shield-exclamation',
			'critical' => 'shield-x',
		];
		return self::con()->svgs->iconClass( $iconMap[ $shieldStatus ] ?? $iconMap[ 'good' ] );
	}

	/**
	 * @param array{has_items:bool,total_items:int,severity:string,icon_class:string,subtext:string} $queueSummary
	 * @param list<array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array<string,mixed>>
	 * }> $zoneGroups
	 */
	private function buildActionsLane( array $queueSummary, array $zoneGroups ) :array {
		$severity = $this->normalizeSeverity( (string)( $queueSummary[ 'severity' ] ?? 'good' ) );
		$iconMap = [
			'good'     => 'shield-check',
			'warning'  => 'shield-exclamation',
			'critical' => 'shield-x',
		];
		$indicatorText = $queueSummary[ 'has_items' ]
			? sprintf(
				_n( '%s issue needs attention', '%s issues need attention', $queueSummary[ 'total_items' ], 'wp-simple-firewall' ),
				$queueSummary[ 'total_items' ]
			)
			: __( 'All Clear', 'wp-simple-firewall' );

		$extraClasses = '';
		if ( $severity === 'critical' ) {
			$extraClasses = ' has-critical';
		}
		elseif ( $severity === 'warning' ) {
			$extraClasses = ' has-issues';
		}

		return [
			'mode'               => PluginNavs::MODE_ACTIONS,
			'label'              => PluginNavs::modeLabel( PluginNavs::MODE_ACTIONS ),
			'description'        => __( 'Take action on critical issues such as scan results, vulnerabilities and malware.', 'wp-simple-firewall' ),
			'href'               => $this->modeHref( PluginNavs::MODE_ACTIONS ),
			'icon_class'         => self::con()->svgs->iconClass( $iconMap[ $severity ] ?? $iconMap[ 'good' ] ),
			'edge_status'        => 'shield',
			'extra_classes'      => $extraClasses,
			'indicator_type'     => 'status',
			'indicator_severity' => $severity,
			'indicator_text'     => $indicatorText,
			'indicator_subtext'  => $queueSummary[ 'has_items' ] ? $this->buildQueueBreakdownText( $zoneGroups ) : '',
		];
	}

	/**
	 * @param list<array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array<string,mixed>>
	 * }> $zoneGroups
	 * @return list<array{
	 *   key:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   count:int
	 * }>
	 */
	private function buildActionsQueueRows( array $zoneGroups ) :array {
		$scansCon = self::con()->comps->scans;
		$counter = $scansCon->getScanResultsCount();
		$rows = \array_values( \array_filter( [
			$this->buildScanQueueRow(
				'malware',
				__( 'Malware', 'wp-simple-firewall' ),
				'bug',
				'critical',
				$scansCon->AFS()->isEnabledMalwareScanPHP(),
				$counter->countMalware()
			),
			$this->buildScanQueueRow(
				'vulnerable_assets',
				__( 'Vulnerabilities', 'wp-simple-firewall' ),
				'shield-exclamation',
				'critical',
				$scansCon->WPV()->isEnabled(),
				$counter->countVulnerableAssets()
			),
			$this->buildScanQueueRow(
				'wp_files',
				__( 'WP Files', 'wp-simple-firewall' ),
				'wordpress',
				'critical',
				$scansCon->AFS()->isScanEnabledWpCore(),
				$counter->countWPFiles()
			),
			$this->buildScanQueueRow(
				'plugin_files',
				__( 'Plugin Files', 'wp-simple-firewall' ),
				'plug',
				'warning',
				$scansCon->AFS()->isScanEnabledPlugins(),
				$counter->countPluginFiles()
			),
			$this->buildScanQueueRow(
				'theme_files',
				__( 'Theme Files', 'wp-simple-firewall' ),
				'brush',
				'warning',
				$scansCon->AFS()->isScanEnabledThemes(),
				$counter->countThemeFiles()
			),
			$this->buildScanQueueRow(
				'abandoned',
				__( 'Abandoned Assets', 'wp-simple-firewall' ),
				'archive',
				'warning',
				$scansCon->APC()->isEnabled(),
				$counter->countAbandoned()
			),
			$this->buildMaintenanceQueueRow( $zoneGroups ),
		] ) );

		return $rows;
	}

	private function buildScanQueueRow(
		string $key,
		string $label,
		string $icon,
		string $activeSeverity,
		bool $isEnabled,
		int $count
	) :?array {
		if ( !$isEnabled ) {
			return null;
		}

		return [
			'key'        => $key,
			'label'      => $label,
			'icon_class' => self::con()->svgs->iconClass( $icon ),
			'severity'   => $count > 0 ? $activeSeverity : 'good',
			'count'      => \max( 0, $count ),
		];
	}

	/**
	 * @param list<array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array<string,mixed>>
	 * }> $zoneGroups
	 * @return array{
	 *   key:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   count:int
	 * }
	 */
	private function buildMaintenanceQueueRow( array $zoneGroups ) :array {
		$maintenanceGroup = $this->findQueueZoneGroupBySlug( $zoneGroups, 'maintenance' );
		$count = \max( 0, (int)( $maintenanceGroup[ 'total_issues' ] ?? 0 ) );

		return [
			'key'        => 'maintenance',
			'label'      => __( 'Maintenance Items', 'wp-simple-firewall' ),
			'icon_class' => self::con()->svgs->iconClass( 'wrench' ),
			'severity'   => $count > 0 ? 'warning' : 'good',
			'count'      => $count,
		];
	}

	/**
	 * @param list<array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array<string,mixed>>
	 * }> $zoneGroups
	 * @return array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array<string,mixed>>
	 * }|array{}
	 */
	private function findQueueZoneGroupBySlug( array $zoneGroups, string $slug ) :array {
		$matches = \array_values( \array_filter(
			$zoneGroups,
			static fn( array $zoneGroup ) :bool => ( $zoneGroup[ 'slug' ] ?? '' ) === $slug
		) );

		return $matches[ 0 ] ?? [];
	}

	/**
	 * @param list<array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array<string,mixed>>
	 * }> $zoneGroups
	 */
	private function buildQueueBreakdownText( array $zoneGroups ) :string {
		$counts = NeedsAttentionQueuePayload::countsFromZoneGroups( $zoneGroups );
		$critical = $counts[ 'critical' ];
		$warning = $counts[ 'warning' ];

		$parts = [];
		if ( $critical > 0 ) {
			$parts[] = sprintf( _n( '%s critical', '%s critical', $critical, 'wp-simple-firewall' ), $critical );
		}
		if ( $warning > 0 ) {
			$parts[] = sprintf( _n( '%s warning', '%s warnings', $warning, 'wp-simple-firewall' ), $warning );
		}

		return empty( $parts ) ? '' : implode( ' - ', $parts );
	}

	private function buildInvestigateLane( int $sessionCount ) :array {
		$text = $sessionCount > 0
			? sprintf( _n( '%s active session', '%s active sessions', $sessionCount, 'wp-simple-firewall' ), $sessionCount )
			: __( 'Activity & Events', 'wp-simple-firewall' );

		return [
			'mode'               => PluginNavs::MODE_INVESTIGATE,
			'label'              => PluginNavs::modeLabel( PluginNavs::MODE_INVESTIGATE ),
			'description'        => __( 'Deep dive to explore every aspect of your site including users, plugins, themes & IP addresses.', 'wp-simple-firewall' ),
			'href'               => $this->modeHref( PluginNavs::MODE_INVESTIGATE ),
			'icon_class'         => self::con()->svgs->iconClass( 'search' ),
			'edge_status'        => 'info',
			'extra_classes'      => '',
			'indicator_type'     => 'status',
			'indicator_severity' => 'neutral',
			'indicator_text'     => $text,
			'indicator_subtext'  => '',
		];
	}

	private function buildConfigureLane( int $percentage, string $status ) :array {
		return [
			'mode'               => PluginNavs::MODE_CONFIGURE,
			'label'              => PluginNavs::modeLabel( PluginNavs::MODE_CONFIGURE ),
			'description'        => __( 'Fine tune your WordPress security posture to exactly what you need.','wp-simple-firewall' ),
			'href'               => $this->modeHref( PluginNavs::MODE_CONFIGURE ),
			'icon_class'         => self::con()->svgs->iconClass( 'sliders' ),
			'edge_status'        => 'good',
			'extra_classes'      => '',
			'indicator_type'     => 'posture',
			'posture_percentage' => $percentage,
			'posture_status'     => $this->normalizeSeverity( $status ),
			'posture_text'       => sprintf( __( '%s%% configured', 'wp-simple-firewall' ), $percentage ),
		];
	}

	private function buildReportsLane( int $reportsCount ) :array {
		$text = $reportsCount > 0
			? sprintf( _n( '%s report', '%s reports', $reportsCount, 'wp-simple-firewall' ), $reportsCount )
			: __( 'Summaries & Alerts', 'wp-simple-firewall' );

		return [
			'mode'               => PluginNavs::MODE_REPORTS,
			'label'              => PluginNavs::modeLabel( PluginNavs::MODE_REPORTS ),
			'description'        => __( 'Review security reports and trends.', 'wp-simple-firewall' ),
			'href'               => $this->modeHref( PluginNavs::MODE_REPORTS ),
			'icon_class'         => self::con()->svgs->iconClass( 'bar-chart-line' ),
			'edge_status'        => 'warning',
			'extra_classes'      => '',
			'indicator_type'     => 'status',
			'indicator_severity' => 'neutral',
			'indicator_text'     => $text,
			'indicator_subtext'  => '',
		];
	}

	private function getInvestigateActiveSessionsCount() :int {
		try {
			$count = \count( ( new FindSessions() )->mostRecent( 5 ) );
		}
		catch ( \Exception $e ) {
			$count = 0;
		}
		return \max( 0, $count );
	}

	private function getGeneratedReportsCount() :int {
		try {
			$count = self::con()->db_con->reports->getQuerySelector()
							 ->addWhere( 'unique_id', '', '!=' )
							 ->count();
		}
		catch ( \Exception $e ) {
			$count = 0;
		}
		return \max( 0, $count );
	}

	private function modeHref( string $mode ) :string {
		$entry = PluginNavs::defaultEntryForMode( $mode );
		return self::con()->plugin_urls->adminTopNav( $entry[ 'nav' ], $entry[ 'subnav' ] );
	}
}
