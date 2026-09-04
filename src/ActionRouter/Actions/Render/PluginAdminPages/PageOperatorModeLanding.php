<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\ActionsQueueCardDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\DashboardLiveMonitorPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts\{
	BuildChartData,
	ChartOptions,
	ChartRequestVO
};

/**
 * @phpstan-import-type ActionsQueueCardData from ActionsQueueCardDataBuilder
 * @phpstan-type OperatorModeDestinationCard array{
 *   mode:string,
 *   sidebar_label:string,
 *   href:string,
 *   icon_class:string,
 *   accent:string,
 *   title:string,
 *   description:string,
 *   cta:string,
 *   accessible_label:string
 * }
 * @phpstan-type DashboardActivityChart array{
 *   key:string,
 *   label:string,
 *   value:int,
 *   href:string,
 *   accessible_label:string
 * }
 * @phpstan-type DashboardActivityChartData array{
 *   period_key:string,
 *   period_label:string,
 *   labels:list<string>,
 *   series:list<array{key:string,label:string,data:list<int>}>
 * }
 * @phpstan-type DashboardTaskGuideChoice array{
 *   key:string,
 *   label:string,
 *   icon_class:string,
 *   target:array{type:'node',node_key:string}|array{type:'href',href:string}
 * }
 * @phpstan-type DashboardTaskGuideNode array{
 *   key:string,
 *   title:string,
 *   choices:list<DashboardTaskGuideChoice>
 * }
 * @phpstan-type DashboardTaskGuide array{
 *   launcher:array{label:string,description:string,cta:string,tooltip:string,accessible_label:string},
 *   graph:array{
 *     initial_node_key:string,
 *     strings:array{back_label:string,close_label:string},
 *     nodes:list<DashboardTaskGuideNode>
 *   }
 * }
 */
class PageOperatorModeLanding extends BaseRender {

	public const SLUG = 'plugin_admin_page_operator_mode_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/operator_mode_landing.twig';
	private const DASHBOARD_ACTIVITY_EVENT_KEYS = [
		'login_block',
		'ip_offense',
		'ip_blocked',
		'conn_kill',
		'block_register',
		'block_xml',
	];

	private ?array $attentionQueryCache = null;

	protected function getRenderData() :array {
		$queueCard = $this->buildActionsQueueCardData();
		$dashboardActivityChartData = $this->buildDashboardActivityChartData();
		$dashboardTaskGuide = $this->buildDashboardTaskGuide();

		return [
			'vars' => [
				'dashboard_activity_chart_data_json' => \json_encode( $dashboardActivityChartData, \JSON_THROW_ON_ERROR ),
				'dashboard_activity_charts'         => $this->buildDashboardActivityCharts( $dashboardActivityChartData ),
				'dashboard_activity_charts_heading' => __( 'Stats (Previous 7 Days)', 'wp-simple-firewall' ),
				'dashboard_launchpad_heading'       => __( 'Launchpad', 'wp-simple-firewall' ),
				'dashboard_strip'                   => $queueCard[ 'dashboard_strip' ],
				'destination_cards'                 => $this->buildDestinationCards(),
				'dashboard_task_guide'              => [
					'launcher'   => $dashboardTaskGuide[ 'launcher' ],
					'graph_json' => \json_encode( $dashboardTaskGuide[ 'graph' ], \JSON_THROW_ON_ERROR ),
				],
				'live_monitor'                      => $this->buildLiveMonitorVars(),
			],
		];
	}

	/**
	 * Reuse the reporting chart service so dashboard totals and trends cover the
	 * same seven-day windows as the reports chart.
	 *
	 * @return DashboardActivityChartData
	 */
	protected function buildDashboardActivityChartData() :array {
		return ( new BuildChartData() )->build(
			( new ChartRequestVO() )->applyFromArray( [
				'period_key' => ChartOptions::PERIOD_7_DAYS,
				'event_keys' => self::DASHBOARD_ACTIVITY_EVENT_KEYS,
			] )
		);
	}

	/**
	 * @param DashboardActivityChartData $chartData
	 * @return list<DashboardActivityChart>
	 */
	private function buildDashboardActivityCharts( array $chartData ) :array {
		$href = self::con()->plugin_urls->adminTopNav(
			PluginNavs::NAV_REPORTS,
			PluginNavs::SUBNAV_REPORTS_CHARTS
		);
		$seriesByKey = \array_column( $chartData[ 'series' ], null, 'key' );

		return \array_map(
			static function ( string $eventKey ) use ( $href, $seriesByKey ) :array {
				$series = $seriesByKey[ $eventKey ];
				$value = \array_sum( $series[ 'data' ] );
				/* translators: %1$s: activity total, %2$s: activity label */
				$accessibleLabel = \sprintf(
					__( '%1$s %2$s in the last 7 days. View trend.', 'wp-simple-firewall' ),
					$value,
					$series[ 'label' ]
				);
				return [
					'key'              => $eventKey,
					'label'            => $series[ 'label' ],
					'value'            => $value,
					'href'             => $href,
					'accessible_label' => $accessibleLabel,
				];
			},
			self::DASHBOARD_ACTIVITY_EVENT_KEYS
		);
	}

	/**
	 * @return ActionsQueueCardData
	 */
	protected function buildActionsQueueCardData() :array {
		return ( new ActionsQueueCardDataBuilder() )->build( $this->getAttentionQuery() );
	}

	/**
	 * @return list<OperatorModeDestinationCard>
	 */
	private function buildDestinationCards() :array {
		return [
			$this->buildDestinationCard(
				PluginNavs::MODE_INVESTIGATE,
				'search',
				__( 'Investigate Site', 'wp-simple-firewall' ),
				__( 'Users, activity, assets and IPs.', 'wp-simple-firewall' ),
				__( 'Open Investigation', 'wp-simple-firewall' )
			),
			$this->buildDestinationCard(
				PluginNavs::MODE_CONFIGURE,
				'sliders',
				__( 'Configure', 'wp-simple-firewall' ),
				__( 'Set coverage and protection.', 'wp-simple-firewall' ),
				__( 'Open Configure', 'wp-simple-firewall' )
			),
			$this->buildDestinationCard(
				PluginNavs::MODE_REPORTS,
				'bar-chart-line',
				__( 'Reports', 'wp-simple-firewall' ),
				__( 'Review security reports and trends.', 'wp-simple-firewall' ),
				__( 'Open Reports', 'wp-simple-firewall' )
			),
		];
	}

	/**
	 * @return OperatorModeDestinationCard
	 */
	private function buildDestinationCard(
		string $mode,
		string $icon,
		string $title,
		string $description,
		string $cta
	) :array {
		return [
			'mode'             => $mode,
			'sidebar_label'    => PluginNavs::modeLabel( $mode ),
			'href'             => $this->modeHref( $mode ),
			'icon_class'       => self::con()->svgs->iconClass( $icon ),
			'accent'           => $mode,
			'title'            => $title,
			'description'      => $description,
			'cta'              => $cta,
			'accessible_label' => $title.'. '.$description.' '.$cta,
		];
	}

	/**
	 * @return DashboardTaskGuide
	 */
	private function buildDashboardTaskGuide() :array {
		return [
			'launcher' => [
				'label'            => __( 'Help me navigate', 'wp-simple-firewall' ),
				'description'      => __( 'Find the right Shield screen for your task.', 'wp-simple-firewall' ),
				'cta'              => __( 'Find a task', 'wp-simple-firewall' ),
				'tooltip'          => __( 'Find the right Shield screen for your task.', 'wp-simple-firewall' ),
				'accessible_label' => __( 'Help me navigate. Find the right Shield screen for your task. Find a task.', 'wp-simple-firewall' ),
			],
			'graph'    => [
				'initial_node_key' => 'start',
				'strings'          => [
					'back_label'  => __( 'Back', 'wp-simple-firewall' ),
					'close_label' => __( 'Close', 'wp-simple-firewall' ),
				],
				'nodes'            => [
					$this->buildDashboardTaskGuideNode(
						'start',
						__( 'What do you want to do?', 'wp-simple-firewall' ),
						[
							$this->buildDashboardTaskGuideNodeChoice( 'manage_ip_access', __( 'Manage IP access', 'wp-simple-firewall' ), 'bi bi-shield-lock', 'ip_access' ),
							$this->buildDashboardTaskGuideNodeChoice( 'run_or_review_scans', __( 'Run or review scans', 'wp-simple-firewall' ), 'bi bi-clipboard2-pulse', 'scans' ),
							$this->buildDashboardTaskGuideNodeChoice( 'investigate', __( 'Investigate activity', 'wp-simple-firewall' ), 'bi bi-search', 'investigate' ),
							$this->buildDashboardTaskGuideNodeChoice( 'configure', __( 'Configure Shield', 'wp-simple-firewall' ), 'bi bi-sliders', 'configure' ),
							$this->buildDashboardTaskGuideNodeChoice( 'view_reports', __( 'View reports', 'wp-simple-firewall' ), 'bi bi-bar-chart-line', 'reports' ),
						]
					),
					$this->buildDashboardTaskGuideNode(
						'ip_access',
						__( 'Manage IP access', 'wp-simple-firewall' ),
						[
							$this->buildDashboardTaskGuideHrefChoice( 'block_or_allow_ip', __( 'Block or allow an IP address', 'wp-simple-firewall' ), 'bi bi-shield-lock', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES ) ),
							$this->buildDashboardTaskGuideHrefChoice( 'investigate_ip_activity', __( 'Investigate activity around an IP address', 'wp-simple-firewall' ), 'bi bi-globe', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_IP ) ),
						]
					),
					$this->buildDashboardTaskGuideNode(
						'scans',
						__( 'Run or review scans', 'wp-simple-firewall' ),
						[
							$this->buildDashboardTaskGuideHrefChoice( 'view_scan_results', __( 'View my scan results', 'wp-simple-firewall' ), 'bi bi-list-check', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_OVERVIEW, [ 'zone' => 'scans' ] ) ),
							$this->buildDashboardTaskGuideHrefChoice( 'run_scan', __( 'Run a scan', 'wp-simple-firewall' ), 'bi bi-play-circle', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RUN ) ),
						]
					),
					$this->buildDashboardTaskGuideNode(
						'investigate',
						__( 'Investigate activity', 'wp-simple-firewall' ),
						[
							$this->buildDashboardTaskGuideHrefChoice( 'investigate_user_activity', __( 'Investigate user activity', 'wp-simple-firewall' ), 'bi bi-person', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_USER ) ),
							$this->buildDashboardTaskGuideHrefChoice( 'investigate_ip_activity', __( 'Investigate activity around an IP address', 'wp-simple-firewall' ), 'bi bi-globe', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_IP ) ),
							$this->buildDashboardTaskGuideHrefChoice( 'investigate_plugin_activity', __( 'Investigate activity around a plugin', 'wp-simple-firewall' ), 'bi bi-plugin', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN ) ),
							$this->buildDashboardTaskGuideHrefChoice( 'investigate_theme_activity', __( 'Investigate activity around a theme', 'wp-simple-firewall' ), 'bi bi-palette', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_THEME ) ),
							$this->buildDashboardTaskGuideHrefChoice( 'investigate_core_activity', __( 'Investigate WordPress core activity', 'wp-simple-firewall' ), 'bi bi-wordpress', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_CORE ) ),
						]
					),
					$this->buildDashboardTaskGuideNode(
						'configure',
						__( 'Configure Shield', 'wp-simple-firewall' ),
						[
							$this->buildDashboardTaskGuideHrefChoice( 'configure_firewall', __( 'Configure the Firewall', 'wp-simple-firewall' ), 'bi bi-shield-check', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW, [ 'zone' => 'firewall' ] ) ),
							$this->buildDashboardTaskGuideHrefChoice( 'configure_ips', __( 'Configure Bots & IPs', 'wp-simple-firewall' ), 'bi bi-shield-lock', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW, [ 'zone' => 'ips' ] ) ),
							$this->buildDashboardTaskGuideHrefChoice( 'configure_scans', __( 'Configure scans', 'wp-simple-firewall' ), 'bi bi-clipboard2-pulse', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW, [ 'zone' => 'scans' ] ) ),
							$this->buildDashboardTaskGuideHrefChoice( 'configure_login', __( 'Configure login protection', 'wp-simple-firewall' ), 'bi bi-key', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW, [ 'zone' => 'login' ] ) ),
							$this->buildDashboardTaskGuideHrefChoice( 'configure_users', __( 'Configure user protection', 'wp-simple-firewall' ), 'bi bi-people', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW, [ 'zone' => 'users' ] ) ),
							$this->buildDashboardTaskGuideHrefChoice( 'configure_other', __( 'Find another setting', 'wp-simple-firewall' ), 'bi bi-sliders', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW ) ),
						]
					),
					$this->buildDashboardTaskGuideNode(
						'reports',
						__( 'View reports', 'wp-simple-firewall' ),
						[
							$this->buildDashboardTaskGuideHrefChoice( 'security_reports', __( 'View security reports', 'wp-simple-firewall' ), 'bi bi-file-earmark-text', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_LIST ) ),
							$this->buildDashboardTaskGuideHrefChoice( 'charts_and_trends', __( 'View charts and trends', 'wp-simple-firewall' ), 'bi bi-graph-up-arrow', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_CHARTS ) ),
							$this->buildDashboardTaskGuideHrefChoice( 'reporting_settings', __( 'Configure reports and alerts', 'wp-simple-firewall' ), 'bi bi-bell', $this->buildDashboardTaskGuideHref( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_SETTINGS ) ),
						]
					),
				],
			],
		];
	}

	/**
	 * @param list<DashboardTaskGuideChoice> $choices
	 * @return DashboardTaskGuideNode
	 */
	private function buildDashboardTaskGuideNode( string $key, string $title, array $choices ) :array {
		return [
			'key'     => $key,
			'title'   => $title,
			'choices' => $choices,
		];
	}

	/**
	 * @return DashboardTaskGuideChoice
	 */
	private function buildDashboardTaskGuideNodeChoice(
		string $key,
		string $label,
		string $iconClass,
		string $nodeKey
	) :array {
		return [
			'key'        => $key,
			'label'      => $label,
			'icon_class' => $iconClass,
			'target'      => [
				'type'     => 'node',
				'node_key' => $nodeKey,
			],
		];
	}

	/**
	 * @return DashboardTaskGuideChoice
	 */
	private function buildDashboardTaskGuideHrefChoice(
		string $key,
		string $label,
		string $iconClass,
		string $href
	) :array {
		return [
			'key'        => $key,
			'label'      => $label,
			'icon_class' => $iconClass,
			'target'      => [
				'type' => 'href',
				'href' => $href,
			],
		];
	}

	private function buildDashboardTaskGuideHref( string $nav, string $subNav, array $query = [] ) :string {
		$href = self::con()->plugin_urls->adminTopNav( $nav, $subNav );
		if ( !empty( $query ) ) {
			$href .= ( \strpos( $href, '?' ) === false ? '?' : '&' ).\http_build_query( $query, '', '&', \PHP_QUERY_RFC3986 );
		}
		return $href;
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
