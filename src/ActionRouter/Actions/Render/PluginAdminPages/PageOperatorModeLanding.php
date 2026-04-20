<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\ActionsQueueCardDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\ActionsQueueScanStateBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\BuildConfigurationCoverage;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\DashboardLiveMonitorPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants as ReportingConstants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\LoadSessions;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type ActionsQueueScanRow from ActionsQueueScanStateBuilder
 * @phpstan-import-type ActionsQueueScanState from ActionsQueueScanStateBuilder
 * @phpstan-import-type ActionsQueueCardData from ActionsQueueCardDataBuilder
 */
class PageOperatorModeLanding extends BaseRender {

	public const SLUG = 'plugin_admin_page_operator_mode_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/operator_mode_landing.twig';

	private ?array $attentionQueryCache = null;
	/** @var ActionsQueueScanState|null */
	private ?array $scanStateCache = null;

	protected function getRenderData() :array {
		$queueCard = $this->buildActionsQueueCardData();

		$coverage = $this->getConfigurationCoverage();
		$configPercentage = $coverage[ 'percentage' ];
		$configPercentage = max( 0, min( 100, $configPercentage ) );
		$configTraffic = $coverage[ 'severity' ];

		$sessionSummary = $this->getInvestigateSessionSummary();
		$reportsSummary = $this->getReportsSummary();
		$secondaryLanes = [
			$this->buildInvestigateLane( $sessionSummary ),
			$this->buildConfigureLane( $configPercentage, $configTraffic ),
			$this->buildReportsLane( $reportsSummary ),
		];

		return [
			'strings' => [
				'title'             => __( 'Actions Queue', 'wp-simple-firewall' ),
				'subtitle'          => $queueCard[ 'subtitle' ],
				'actions_queue_cta' => __( 'View Actions Queue', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'shield_status'      => $queueCard[ 'shield_status' ],
				'shield_icon_class'  => $queueCard[ 'shield_icon_class' ],
				'actions_all_clear'  => $queueCard[ 'summary' ][ 'has_items' ] ? null : $queueCard[ 'all_clear' ],
				'lanes'              => [
					$queueCard[ 'actions_lane' ],
					...$secondaryLanes,
				],
				'actions_lane'       => $queueCard[ 'actions_lane' ],
				'secondary_lanes'    => $secondaryLanes,
				'actions_queue_rows' => $queueCard[ 'actions_queue_rows' ],
				'live_monitor'       => $this->buildLiveMonitorVars(),
			],
		];
	}

	/**
	 * @return ActionsQueueCardData
	 */
	protected function buildActionsQueueCardData() :array {
		return ( new ActionsQueueCardDataBuilder() )->build(
			$this->getAttentionQuery(),
			$this->getQueueScanRows()
		);
	}

	/**
	 * @return array{
	 *   severity:'good'|'warning'|'critical',
	 *   percentage:int,
	 *   controls:array{total:int,good:int,warning:int,critical:int},
	 *   zones:array{total:int,good:int,warning:int,critical:int}
	 * }
	 */
	protected function getConfigurationCoverage() :array {
		return ( new BuildConfigurationCoverage() )->build();
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
			'description'        => __( 'Fine tune your WordPress security coverage to exactly what you need.', 'wp-simple-firewall' ),
			'href'               => $this->modeHref( PluginNavs::MODE_CONFIGURE ),
			'icon_class'         => self::con()->svgs->iconClass( 'sliders' ),
			'edge_status'        => 'good',
			'extra_classes'      => '',
			'indicator_type'     => 'posture',
			'posture_percentage' => $percentage,
			'posture_status'     => $this->normalizeSeverity( $status ),
			'posture_text'       => sprintf( __( '%s%% configuration coverage', 'wp-simple-firewall' ), $percentage ),
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

	/**
	 * @return ActionsQueueScanState
	 */
	protected function buildScanState() :array {
		return ( new ActionsQueueScanStateBuilder() )->build();
	}

	/**
	 * @return list<ActionsQueueScanRow>
	 */
	private function getQueueScanRows() :array {
		if ( $this->scanStateCache === null ) {
			$this->scanStateCache = $this->buildScanState();
		}

		return $this->scanStateCache[ 'rows' ];
	}

	private function normalizeSeverity( string $severity ) :string {
		$severity = sanitize_key( $severity );
		return \in_array( $severity, [ 'good', 'warning', 'critical' ], true ) ? $severity : 'good';
	}
}
