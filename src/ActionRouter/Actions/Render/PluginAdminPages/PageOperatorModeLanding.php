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

		return [
			'vars' => [
				'dashboard_activity_chart_data_json' => \json_encode( $dashboardActivityChartData, \JSON_THROW_ON_ERROR ),
				'dashboard_activity_charts'         => $this->buildDashboardActivityCharts( $dashboardActivityChartData ),
				'dashboard_activity_charts_heading' => __( 'Stats (Previous 7 Days)', 'wp-simple-firewall' ),
				'dashboard_launchpad_heading'       => __( 'Launchpad', 'wp-simple-firewall' ),
				'dashboard_strip'                   => $queueCard[ 'dashboard_strip' ],
				'destination_cards'                 => $this->buildDestinationCards(),
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
