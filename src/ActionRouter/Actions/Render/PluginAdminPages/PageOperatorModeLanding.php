<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\ActionsQueueCardDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\BuildConfigurationCoverage;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\DashboardLiveMonitorPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants as ReportingConstants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\LoadSessions;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type ActionsQueueCardData from ActionsQueueCardDataBuilder
 * @phpstan-type OperatorModeBadge array{text:string,severity:string,title:string}
 * @phpstan-type OperatorModeStatusLane array{
 *   mode:string,
 *   label:string,
 *   description:string,
 *   href:string,
 *   icon_class:string,
 *   edge_status:string,
 *   extra_classes:string,
 *   indicator_type:'status',
 *   indicator_severity:string,
 *   indicator_text:string,
 *   indicator_badges:list<OperatorModeBadge>,
 *   indicator_subtext:string
 * }
 * @phpstan-type OperatorModePostureLane array{
 *   mode:string,
 *   label:string,
 *   description:string,
 *   href:string,
 *   icon_class:string,
 *   edge_status:string,
 *   extra_classes:string,
 *   indicator_type:'posture',
 *   indicator_badges:list<OperatorModeBadge>,
 *   posture_percentage:int,
 *   posture_status:string,
 *   posture_text:string
 * }
 * @phpstan-type OperatorModeSecondaryLane OperatorModeStatusLane|OperatorModePostureLane
 */
class PageOperatorModeLanding extends BaseRender {

	public const SLUG = 'plugin_admin_page_operator_mode_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/operator_mode_landing.twig';

	private ?array $attentionQueryCache = null;

	protected function getRenderData() :array {
		$queueCard = $this->buildActionsQueueCardData();

		$coverage = $this->getConfigurationCoverage();
		$configPercentage = $coverage[ 'percentage' ];
		$configPercentage = max( 0, min( 100, $configPercentage ) );
		$configTraffic = $coverage[ 'severity' ];

		$sessionSummary = $this->getInvestigateSessionSummary();
		$reportsSummary = $this->getReportsSummary();
		/** @var list<OperatorModeSecondaryLane> $secondaryLanes */
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
		return ( new ActionsQueueCardDataBuilder() )->build( $this->getAttentionQuery() );
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
			'ready'        => __( 'Live monitor updated.', 'wp-simple-firewall' ),
			'error'        => __( 'Live monitor update failed.', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @param array{active_count:int,recent_active_count:int} $sessionSummary
	 * @return OperatorModeStatusLane
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

	/**
	 * @return OperatorModePostureLane
	 */
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
			'indicator_badges'   => [],
			'posture_percentage' => $percentage,
			'posture_status'     => $this->normalizeSeverity( $status ),
			'posture_text'       => sprintf( __( '%s%% configuration coverage', 'wp-simple-firewall' ), $percentage ),
		];
	}

	/**
	 * @param array{count:int,latest_report_at:int,latest_alert_at:int} $reportsSummary
	 * @return OperatorModeStatusLane
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
			$row = Services::WpDb()->selectRow( sprintf(
				"SELECT
					COUNT(*) AS `count`,
					MAX(`created_at`) AS `latest_report_at`,
					MAX(CASE WHEN `type`='%s' THEN `created_at` ELSE 0 END) AS `latest_alert_at`
				FROM `%s`
				WHERE `unique_id`!=''",
				ReportingConstants::REPORT_TYPE_ALERT,
				self::con()->db_con->reports->getTable()
			) );
			$row = \is_object( $row ) ? \get_object_vars( $row ) : ( \is_array( $row ) ? $row : [] );
			$summary[ 'count' ] = (int)( $row[ 'count' ] ?? 0 );
			$summary[ 'latest_report_at' ] = (int)( $row[ 'latest_report_at' ] ?? 0 );
			$summary[ 'latest_alert_at' ] = (int)( $row[ 'latest_alert_at' ] ?? 0 );
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

	private function normalizeSeverity( string $severity ) :string {
		$severity = sanitize_key( $severity );
		return \in_array( $severity, [ 'good', 'warning', 'critical' ], true ) ? $severity : 'good';
	}
}
