<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\DashboardLiveMonitorPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants as ReportingConstants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\LoadSessions;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\BuildZonePosture;
use FernleafSystems\Wordpress\Services\Services;

class PageOperatorModeLanding extends BaseRender {

	public const SLUG = 'plugin_admin_page_operator_mode_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/operator_mode_landing.twig';

	private const VALID_SEVERITIES = [
		'good',
		'warning',
		'critical',
	];

	private ?array $attentionQueryCache = null;

	protected function getRenderData() :array {
		$attentionQuery = $this->getAttentionQuery();
		$queueSummary = $this->getQueueSummary( $attentionQuery );
		$queueZoneGroups = $this->getQueueZoneGroups( $attentionQuery );
		$shieldStatus = $this->normalizeSeverity( $queueSummary[ 'severity' ] );

		$configPercentage = $this->getZonePosture()[ 'percentage' ];
		$configPercentage = max( 0, min( 100, $configPercentage ) );
		$configTraffic = BuildZonePosture::trafficFromPercentage( $configPercentage );

		$sessionSummary = $this->getInvestigateSessionSummary();
		$reportsSummary = $this->getReportsSummary();
		$actionsLane = $this->buildActionsLane( $queueSummary, $queueZoneGroups );
		$secondaryLanes = [
			$this->buildInvestigateLane( $sessionSummary ),
			$this->buildConfigureLane( $configPercentage, $configTraffic ),
			$this->buildReportsLane( $reportsSummary ),
		];

		return [
			'strings' => [
				'title'             => __( 'Actions Queue', 'wp-simple-firewall' ),
				'subtitle'          => $this->buildShieldSubtitle( $queueSummary ),
				'actions_queue_cta' => __( 'View Actions Queue', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'shield_status'      => $shieldStatus,
				'shield_icon_class'  => $this->buildShieldIconClass( $shieldStatus ),
				'lanes'              => [
					$actionsLane,
					...$secondaryLanes,
				],
				'actions_lane'       => $actionsLane,
				'secondary_lanes'    => $secondaryLanes,
				'actions_queue_rows' => $this->buildActionsQueueRows( $queueZoneGroups ),
				'live_monitor'       => $this->buildLiveMonitorVars(),
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

	/**
	 * @return array{has_items:bool,total_items:int,severity:string,icon_class:string,subtext:string}
	 */
	private function getQueueSummary( array $attentionQuery ) :array {
		$hasItems = !$attentionQuery[ 'summary' ][ 'is_all_clear' ];

		return [
			'has_items'   => $hasItems,
			'total_items' => $attentionQuery[ 'summary' ][ 'total' ],
			'severity'    => $attentionQuery[ 'summary' ][ 'severity' ],
			'icon_class'  => self::con()->svgs->iconClass( $hasItems ? 'shield-exclamation' : 'shield-check' ),
			'subtext'     => '',
		];
	}

	/**
	 * @return list<array{
	 *   zone:'scans'|'maintenance',
	 *   severity:string,
	 *   total:int,
	 *   items:list<array<string,mixed>>
	 * }>
	 */
	private function getQueueZoneGroups( array $attentionQuery ) :array {
		return \array_values( $attentionQuery[ 'groups' ] );
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
	 *   zone:'scans'|'maintenance',
	 *   severity:string,
	 *   total:int,
	 *   items:list<array<string,mixed>>
	 * }> $zoneGroups
	 */
	private function buildActionsLane( array $queueSummary, array $zoneGroups ) :array {
		$severity = $this->normalizeSeverity( $queueSummary[ 'severity' ] );
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
	 *   zone:'scans'|'maintenance',
	 *   severity:string,
	 *   total:int,
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
		$scanGroup = $this->findQueueZoneGroupByZone( $zoneGroups, 'scans' );
		$rows = \array_values( \array_map(
			fn( array $item ) :array => $this->buildScanQueueRowFromQueueItem( $item ),
			$scanGroup[ 'items' ]
		) );
		$rows[] = $this->buildMaintenanceQueueRow( $zoneGroups );

		return $rows;
	}

	/**
	 * @param array<string,mixed> $item
	 * @return array{
	 *   key:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   count:int
	 * }
	 */
	private function buildScanQueueRowFromQueueItem( array $item ) :array {
		$key = (string)$item[ 'key' ];

		return [
			'key'        => $key,
			'label'      => $item[ 'label' ],
			'icon_class' => self::con()->svgs->iconClass( $this->getScanQueueRowIcon( $key ) ),
			'severity'   => $this->normalizeSeverity( $item[ 'severity' ] ),
			'count'      => $item[ 'count' ],
		];
	}

	private function getScanQueueRowIcon( string $key ) :string {
		return PluginNavs::actionsLandingScanRowIcon( $key );
	}

	/**
	 * @param list<array{
	 *   zone:'scans'|'maintenance',
	 *   severity:string,
	 *   total:int,
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
		$maintenanceGroup = $this->findQueueZoneGroupByZone( $zoneGroups, 'maintenance' );
		$count = $maintenanceGroup[ 'total' ];

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
	 *   zone:'scans'|'maintenance',
	 *   severity:string,
	 *   total:int,
	 *   items:list<array<string,mixed>>
	 * }> $zoneGroups
	 * @return array{
	 *   zone:'scans'|'maintenance',
	 *   severity:string,
	 *   total:int,
	 *   items:list<array<string,mixed>>
	 * }
	 */
	private function findQueueZoneGroupByZone( array $zoneGroups, string $zone ) :array {
		foreach ( $zoneGroups as $zoneGroup ) {
			if ( $zoneGroup[ 'zone' ] === $zone ) {
				return $zoneGroup;
			}
		}

		return [
			'zone'     => $zone,
			'severity' => 'good',
			'total'    => 0,
			'items'    => [],
		];
	}

	/**
	 * @param list<array{
	 *   zone:'scans'|'maintenance',
	 *   severity:string,
	 *   total:int,
	 *   items:list<array<string,mixed>>
	 * }> $zoneGroups
	 */
	private function buildQueueBreakdownText( array $zoneGroups ) :string {
		$counts = [
			'critical' => 0,
			'warning'  => 0,
		];
		foreach ( $zoneGroups as $zoneGroup ) {
			foreach ( $zoneGroup[ 'items' ] as $item ) {
				if ( isset( $counts[ $item[ 'severity' ] ] ) ) {
					$counts[ $item[ 'severity' ] ] += $item[ 'count' ];
				}
			}
		}

		$parts = [];
		if ( $counts[ 'critical' ] > 0 ) {
			$parts[] = sprintf( _n( '%s critical', '%s critical', $counts[ 'critical' ], 'wp-simple-firewall' ), $counts[ 'critical' ] );
		}
		if ( $counts[ 'warning' ] > 0 ) {
			$parts[] = sprintf( _n( '%s warning', '%s warnings', $counts[ 'warning' ], 'wp-simple-firewall' ), $counts[ 'warning' ] );
		}

		return empty( $parts ) ? '' : implode( ' - ', $parts );
	}

	/**
	 * @param array{active_count:int,recent_active_count:int} $sessionSummary
	 */
	private function buildInvestigateLane( array $sessionSummary ) :array {
		$badges = [
			$this->buildIndicatorBadge(
				sprintf(
					_n( '%s active session', '%s active sessions', $sessionSummary[ 'active_count' ], 'wp-simple-firewall' ),
					$sessionSummary[ 'active_count' ]
				),
				'info'
			),
			$this->buildIndicatorBadge(
				sprintf(
					_n( '%s session in last 24h', '%s sessions in last 24h', $sessionSummary[ 'recent_active_count' ], 'wp-simple-firewall' ),
					$sessionSummary[ 'recent_active_count' ]
				),
				'info'
			),
		];

		return [
			'mode'               => PluginNavs::MODE_INVESTIGATE,
			'label'              => PluginNavs::modeLabel( PluginNavs::MODE_INVESTIGATE ),
			'description'        => __( 'Deep dive to explore every aspect of your site including users, plugins, themes & IP addresses.', 'wp-simple-firewall' ),
			'href'               => $this->modeHref( PluginNavs::MODE_INVESTIGATE ),
			'icon_class'         => self::con()->svgs->iconClass( 'search' ),
			'edge_status'        => 'info',
			'extra_classes'      => '',
			'indicator_type'     => 'status',
			'indicator_severity' => 'info',
			'indicator_text'     => $badges[ 0 ][ 'text' ],
			'indicator_badges'   => $badges,
			'indicator_subtext'  => '',
		];
	}

	private function buildConfigureLane( int $percentage, string $status ) :array {
		return [
			'mode'               => PluginNavs::MODE_CONFIGURE,
			'label'              => PluginNavs::modeLabel( PluginNavs::MODE_CONFIGURE ),
			'description'        => __( 'Fine tune your WordPress security posture to exactly what you need.', 'wp-simple-firewall' ),
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

	/**
	 * @param array{count:int,latest_report_at:int,latest_alert_at:int} $reportsSummary
	 */
	private function buildReportsLane( array $reportsSummary ) :array {
		$badges = [
			$this->buildIndicatorBadge(
				sprintf( _n( '%s report', '%s reports', $reportsSummary[ 'count' ], 'wp-simple-firewall' ), $reportsSummary[ 'count' ] ),
				'info'
			),
		];
		if ( $reportsSummary[ 'latest_report_at' ] > 0 ) {
			$badges[] = $this->buildTimestampBadge( __( 'Last report', 'wp-simple-firewall' ), $reportsSummary[ 'latest_report_at' ] );
		}
		if ( $reportsSummary[ 'latest_alert_at' ] > 0 ) {
			$badges[] = $this->buildTimestampBadge( __( 'Last alert', 'wp-simple-firewall' ), $reportsSummary[ 'latest_alert_at' ], 'warning' );
		}

		return [
			'mode'               => PluginNavs::MODE_REPORTS,
			'label'              => PluginNavs::modeLabel( PluginNavs::MODE_REPORTS ),
			'description'        => __( 'Review security reports and trends.', 'wp-simple-firewall' ),
			'href'               => $this->modeHref( PluginNavs::MODE_REPORTS ),
			'icon_class'         => self::con()->svgs->iconClass( 'bar-chart-line' ),
			'edge_status'        => 'warning',
			'extra_classes'      => '',
			'indicator_type'     => 'status',
			'indicator_severity' => 'info',
			'indicator_text'     => $badges[ 0 ][ 'text' ],
			'indicator_badges'   => $badges,
			'indicator_subtext'  => '',
		];
	}

	/**
	 * @return array{active_count:int,recent_active_count:int}
	 */
	private function getInvestigateSessionSummary() :array {
		$summary = [
			'active_count'        => 0,
			'recent_active_count' => 0,
		];

		try {
			$cutoff = $this->getCurrentTimestamp() - 86400;
			$sessions = $this->getSessionsLoader()->flat();
			$summary[ 'active_count' ] = \count( $sessions );
			$summary[ 'recent_active_count' ] = \count( \array_filter(
				$sessions,
				static function ( array $session ) use ( $cutoff ) :bool {
					$lastActivityAt = $session[ 'shield' ][ 'last_activity_at' ] ?? $session[ 'login' ] ?? 0;
					return \is_int( $lastActivityAt ) && $lastActivityAt >= $cutoff;
				}
			) );
		}
		catch ( \Throwable $e ) {
		}

		return [
			'active_count'        => \max( 0, $summary[ 'active_count' ] ),
			'recent_active_count' => \max( 0, $summary[ 'recent_active_count' ] ),
		];
	}

	/**
	 * @return array{count:int,latest_report_at:int,latest_alert_at:int}
	 */
	private function getReportsSummary() :array {
		$summary = [
			'count'            => 0,
			'latest_report_at' => 0,
			'latest_alert_at'  => 0,
		];

		try {
			$summary[ 'count' ] = self::con()->db_con->reports->getQuerySelector()
				->addWhere( 'unique_id', '', '!=' )
				->count();
			$latestReport = $this->getLatestReportRecord();
			$latestAlert = $this->getLatestReportRecord( ReportingConstants::REPORT_TYPE_ALERT );
			$summary[ 'latest_report_at' ] = (int)( $latestReport->created_at ?? 0 );
			$summary[ 'latest_alert_at' ] = (int)( $latestAlert->created_at ?? 0 );
		}
		catch ( \Throwable $e ) {
		}

		return [
			'count'            => \max( 0, $summary[ 'count' ] ),
			'latest_report_at' => \max( 0, $summary[ 'latest_report_at' ] ),
			'latest_alert_at'  => \max( 0, $summary[ 'latest_alert_at' ] ),
		];
	}

	protected function getSessionsLoader() :LoadSessions {
		return new LoadSessions();
	}

	protected function getCurrentTimestamp() :int {
		return Services::Request()->ts();
	}

	/**
	 * @return object|null
	 */
	private function getLatestReportRecord( ?string $reportType = null ) {
		$selector = self::con()->db_con->reports->getQuerySelector()
			->addWhere( 'unique_id', '', '!=' );
		if ( !empty( $reportType ) ) {
			$selector->filterByType( $reportType );
		}

		return $selector
			->setOrderBy( 'created_at', 'DESC', true )
			->first();
	}

	/**
	 * @return array{text:string,severity:string,title:string}
	 */
	private function buildIndicatorBadge( string $text, string $severity = 'info', string $title = '' ) :array {
		return [
			'text'     => $text,
			'severity' => $severity,
			'title'    => $title,
		];
	}

	/**
	 * @return array{text:string,severity:string,title:string}
	 */
	private function buildTimestampBadge( string $label, int $timestamp, string $severity = 'info' ) :array {
		return $this->buildIndicatorBadge(
			sprintf(
				'%s: %s',
				$label,
				Services::Request()->carbon( true )->setTimestamp( $timestamp )->diffForHumans()
			),
			$severity,
			Services::WpGeneral()->getTimeStringForDisplay( $timestamp )
		);
	}

	private function modeHref( string $mode ) :string {
		$entry = PluginNavs::defaultEntryForMode( $mode );
		return self::con()->plugin_urls->adminTopNav( $entry[ 'nav' ], $entry[ 'subnav' ] );
	}

	private function getAttentionQuery() :array {
		if ( $this->attentionQueryCache === null ) {
			$this->attentionQueryCache = $this->buildAttentionQuery();
		}

		return $this->attentionQueryCache;
	}

	protected function buildAttentionQuery() :array {
		return self::con()->comps->site_query->attention();
	}
}
