<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Placeholders\PlaceholderMeter;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\ChartsSummary;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\{
	OverviewActivity,
	OverviewIpBlocks,
	OverviewIpOffenses,
	OverviewReports,
	OverviewTraffic
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter\MeterSummary;

class PageDashboardOverview extends BasePluginAdminPage {

	public const SLUG = 'plugin_admin_page_dashboard_overview';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/dashboard_overview.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$scansCon = $con->comps->scans;
		$counter = $scansCon->getScanResultsCount();
		$filesCount = $counter->countThemeFiles() + $counter->countPluginFiles() + $counter->countWPFiles();
		$malwareEnabled = $scansCon->AFS()->isEnabledMalwareScanPHP();
		$malwareCount = $malwareEnabled ? $counter->countMalware() : 0;
		$vulnEnabled = $scansCon->WPV()->isEnabled();
		$vulnCount = $vulnEnabled ? $counter->countVulnerableAssets() : 0;
		$abandonedEnabled = $scansCon->APC()->isEnabled();
		$abandonedCount = $abandonedEnabled ? $counter->countAbandoned() : 0;

		return [
			'content' => [
				'summary_charts' => $con->action_router->render( ChartsSummary::class, [
					'reports_limit' => 5,
				] ),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->raw( 'speedometer' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Security Overview', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Your entire WordPress site security at a glance.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'widget_grade'   => [
					[
						'title'     => false,
						'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_GRADES ),
						'href_text' => __( 'View All Security Grades', 'wp-simple-firewall' ),
						'content'   => $con->action_router->render( PlaceholderMeter::class, [
							'meter_slug' => MeterSummary::SLUG,
							'is_hero'    => true,
						] ),
						'width'   => 12,
						'no_card' => true,
					],
				],
				'scans'          => [
					[
						'title'         => __( 'WP Files', 'wp-simple-firewall' ),
						'count_display' => (string)$filesCount,
						'status_class'  => $filesCount > 0 ? 'critical' : 'good',
						'badge_text'    => $filesCount > 0
							? sprintf( _n( '%s Issue', '%s Issues', $filesCount, 'wp-simple-firewall' ), $filesCount )
							: __( 'All Clear', 'wp-simple-firewall' ),
					],
					[
						'title'         => __( 'Malware', 'wp-simple-firewall' ),
						'count_display' => $malwareEnabled ? (string)$malwareCount : "\xe2\x80\x94",
						'status_class'  => !$malwareEnabled ? 'disabled' : ( $malwareCount > 0 ? 'critical' : 'good' ),
						'badge_text'    => !$malwareEnabled
							? __( 'Disabled', 'wp-simple-firewall' )
							: ( $malwareCount > 0
								? sprintf( _n( '%s Issue', '%s Issues', $malwareCount, 'wp-simple-firewall' ), $malwareCount )
								: __( 'All Clear', 'wp-simple-firewall' ) ),
					],
					[
						'title'         => __( 'Vulnerable', 'wp-simple-firewall' ),
						'count_display' => $vulnEnabled ? (string)$vulnCount : "\xe2\x80\x94",
						'status_class'  => !$vulnEnabled ? 'disabled' : ( $vulnCount > 0 ? 'warning' : 'good' ),
						'badge_text'    => !$vulnEnabled
							? __( 'Disabled', 'wp-simple-firewall' )
							: ( $vulnCount > 0
								? sprintf( _n( '%s Issue', '%s Issues', $vulnCount, 'wp-simple-firewall' ), $vulnCount )
								: __( 'All Clear', 'wp-simple-firewall' ) ),
					],
					[
						'title'         => __( 'Abandoned', 'wp-simple-firewall' ),
						'count_display' => $abandonedEnabled ? (string)$abandonedCount : "\xe2\x80\x94",
						'status_class'  => !$abandonedEnabled ? 'disabled' : ( $abandonedCount > 0 ? 'warning' : 'good' ),
						'badge_text'    => !$abandonedEnabled
							? __( 'Disabled', 'wp-simple-firewall' )
							: ( $abandonedCount > 0
								? sprintf( _n( '%s Issue', '%s Issues', $abandonedCount, 'wp-simple-firewall' ), $abandonedCount )
								: __( 'All Clear', 'wp-simple-firewall' ) ),
					],
				],
				'scans_link'     => [
					'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
					'href_text' => __( 'View Scan Results', 'wp-simple-firewall' ),
				],
				'widget_general' => [
					[
						'title'        => __( 'IP Offenses', 'wp-simple-firewall' ),
						'href'         => $con->plugin_urls->adminTopNav( PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES ),
						'href_text'    => __( 'IP Rules', 'wp-simple-firewall' ),
						'content'      => $con->action_router->render( OverviewIpOffenses::class, [
							'limit' => 10,
						] ),
						'width'        => 2,
						'accent_class' => 'warning',
					],
					[
						'title'        => __( 'IP Blocks', 'wp-simple-firewall' ),
						'href'         => $con->plugin_urls->adminTopNav( PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES ),
						'href_text'    => __( 'IP Rules', 'wp-simple-firewall' ),
						'content'      => $con->action_router->render( OverviewIpBlocks::class, [
							'limit' => 10,
						] ),
						'width'        => 2,
						'accent_class' => 'critical',
					],
					[
						'title'        => __( 'Recent Activity', 'wp-simple-firewall' ),
						'href'         => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ),
						'href_text'    => __( 'View Activity Logs', 'wp-simple-firewall' ),
						'content'      => $con->action_router->render( OverviewActivity::class, [
							'limit' => 10,
						] ),
						'width'        => 4,
						'accent_class' => 'info',
					],
					[
						'title'        => __( 'Recent Traffic', 'wp-simple-firewall' ),
						'href'         => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ),
						'href_text'    => __( 'View Site Traffic', 'wp-simple-firewall' ),
						'content'      => $con->action_router->render( OverviewTraffic::class, [
							'limit' => 10,
						] ),
						'width'        => 2,
						'accent_class' => 'info',
					],
					[
						'title'        => __( 'Recent Reports', 'wp-simple-firewall' ),
						'href'         => $con->plugin_urls->adminTopNav( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_LIST ),
						'href_text'    => __( 'View Reports', 'wp-simple-firewall' ),
						'content'      => $con->action_router->render( OverviewReports::class, [
							'limit' => 10,
						] ),
						'width'        => 2,
						'accent_class' => 'info',
					],
				],
			],
		];
	}
}
