<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\ActionsQueueCardDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\DashboardRecentEventsDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops as EventsDB;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\DashboardEventIcons;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\DashboardLiveMonitorPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\TaskGuideDataBuilder;
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
 *   accessible_label:string,
 *   icon_class:string,
 *   change:int,
 *   change_label:string,
 *   spark_points:string
 * }
 * @phpstan-type DashboardActivityChartData array{
 *   period_key:string,
 *   period_label:string,
 *   labels:list<string>,
 *   series:list<array{key:string,label:string,data:list<int>}>
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
		$dashboardTaskGuideLauncher = ( new TaskGuideDataBuilder() )->buildLauncher();
		$dashboardRecentEvents = ( new DashboardRecentEventsDataBuilder() )->build();

		return [
			'vars' => [
				'dashboard_activity_chart_data_json' => \json_encode( $dashboardActivityChartData, \JSON_THROW_ON_ERROR ),
				'dashboard_activity_charts'         => $this->buildDashboardActivityCharts( $dashboardActivityChartData ),
				'dashboard_activity_charts_heading' => __( 'Stats (Previous 7 Days)', 'wp-simple-firewall' ),
				'dashboard_recent_events'           => $dashboardRecentEvents,
				'dashboard_recent_events_heading'   => __( 'Recent Events', 'wp-simple-firewall' ),
				'dashboard_recent_events_unrecorded_label' => __( 'Not seen yet:', 'wp-simple-firewall' ),
				'dashboard_launchpad_heading'       => __( 'Launchpad', 'wp-simple-firewall' ),
				'dashboard_strip'                   => $queueCard[ 'dashboard_strip' ],
				'launchpad'                         => $this->buildLaunchpadTopItems(),
				'destination_cards'                 => $this->buildDestinationCards(),
				'dashboard_task_guide'              => [ 'launcher' => $dashboardTaskGuideLauncher ],
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
		$href = self::con()->plugin_urls->reportsHome( PluginNavs::SUBNAV_REPORTS_CHARTS );
		$seriesByKey = \array_column( $chartData[ 'series' ], null, 'key' );
		$previous = $this->buildPreviousWeekTotals();

		return \array_map(
			function ( string $eventKey ) use ( $href, $seriesByKey, $previous ) :array {
				$series = $seriesByKey[ $eventKey ];
				$value = \array_sum( $series[ 'data' ] );
				$change = $value - ( $previous[ $eventKey ] ?? 0 );
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
					'icon_class'       => ( new DashboardEventIcons() )->iconClassForKey( $eventKey ),
					'accessible_label' => $accessibleLabel,
					'change'           => $change,
					'change_label'     => $this->changeLabel( $change ),
					'spark_points'     => $this->sparkPoints( \array_map( 'intval', $series[ 'data' ] ) ),
				];
			},
			self::DASHBOARD_ACTIVITY_EVENT_KEYS
		);
	}

	/**
	 * Totals for the seven calendar days before the seven shown, so each stat can say how it changed.
	 * @return array<string,int>
	 */
	protected function buildPreviousWeekTotals() :array {
		$today = Services::Request()->carbon( true )->startOfDay();
		$startAt = ( clone $today )->subDays( 13 )->getTimestamp();
		$endAt = ( clone $today )->subDays( 6 )->getTimestamp() - 1;

		$totals = [];
		foreach ( self::DASHBOARD_ACTIVITY_EVENT_KEYS as $eventKey ) {
			/** @var EventsDB\Select $selector */
			$selector = self::con()->db_con->events->getQuerySelector();
			$totals[ $eventKey ] = (int)$selector->filterByBoundary( $startAt, $endAt )->sumEvent( $eventKey );
		}
		return $totals;
	}

	private function changeLabel( int $change ) :string {
		if ( $change === 0 ) {
			return __( 'Same as the 7 days before', 'wp-simple-firewall' );
		}
		/* translators: %s: signed change, such as +3 or -2 */
		return \sprintf( __( '%s on the 7 days before', 'wp-simple-firewall' ), ( $change > 0 ? '+' : '−' ).\abs( $change ) );
	}

	/**
	 * Polyline points for a small trend line in a 100 x 24 box; a flat line when there is no activity.
	 * @param list<int> $values
	 */
	private function sparkPoints( array $values ) :string {
		$count = \count( $values );
		if ( $count < 2 ) {
			return '';
		}
		$max = \max( 1, \max( $values ) );
		$points = [];
		foreach ( \array_values( $values ) as $i => $value ) {
			$points[] = \sprintf( '%.1f,%.1f', $i*100/( $count - 1 ), 22 - ( $value/$max )*20 );
		}
		return \implode( ' ', $points );
	}

	/**
	 * The most pressing queue groups, listed without links: the Launchpad has one way into the
	 * Actions Queue, which lists every group with its own action.
	 * @return array{items:list<array{label:string,count:int,severity:string}>,more:int,more_label:string,queue_href:string,queue_label:string,heading:string}
	 */
	private function buildLaunchpadTopItems() :array {
		$items = \array_values( \array_filter(
			$this->getAttentionQuery()[ 'items' ],
			static fn( array $item ) :bool => $item[ 'count' ] > 0
		) );
		$top = \array_map(
			static fn( array $item ) :array => [
				'label'    => $item[ 'label' ],
				'count'    => $item[ 'count' ],
				'severity' => $item[ 'severity' ] === 'critical' ? 'critical' : 'warning',
			],
			\array_slice( $items, 0, 3 )
		);

		return [
			'items'       => $top,
			'more'        => \max( 0, \count( $items ) - \count( $top ) ),
			/* translators: %s: number of further queue groups */
			'more_label'  => __( 'and %s more', 'wp-simple-firewall' ),
			'queue_href'  => self::con()->plugin_urls->actionsQueueScans(),
			'queue_label' => __( 'Open Actions Queue', 'wp-simple-firewall' ),
			'heading'     => __( 'Needs attention', 'wp-simple-firewall' ),
		];
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
