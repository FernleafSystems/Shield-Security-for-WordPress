<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Placeholders\PlaceholderMeter;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\ChartsSummary;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\ReportsTable;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\{
	OverviewActivity,
	OverviewIpBlocks,
	OverviewIpOffenses,
	OverviewScans,
	OverviewTraffic
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter\MeterSummary;

class PageDashboardOverview extends BasePluginAdminPage {

	public const SLUG = 'plugin_admin_page_dashboard_overview';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/dashboard_overview.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$scansCon = self::con()->comps->scans;
		$counter = $scansCon->getScanResultsCount();
		$filesCount = $counter->countThemeFiles() + $counter->countPluginFiles() + $counter->countWPFiles();
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
						] ),
						'width'     => 12,
					],
				],
				'widget_scans'   => [
					[
						'title'     => __( 'WordPress Files', 'wp-simple-firewall' ),
						'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
						'href_text' => __( 'Scan Results', 'wp-simple-firewall' ),
						'content'   => $con->action_router->render( OverviewScans::class, [
							'count' => $filesCount,
						] ),
						'width'     => 6,
						'classes'   => [
							'card' => $filesCount > 0 ? 'text-bg-danger' : 'text-bg-success'
						]
					],
					[
						'title'     => __( 'Malware', 'wp-simple-firewall' ),
						'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
						'href_text' => __( 'Scan Results', 'wp-simple-firewall' ),
						'content'   => $con->action_router->render( OverviewScans::class, [
							'count' => $scansCon->AFS()->isEnabledMalwareScanPHP() ? $counter->countMalware() : '-',
						] ),
						'width'     => 6,
						'classes'   => [
							'card' => $scansCon->AFS()->isEnabledMalwareScanPHP() ?
								( $counter->countMalware() > 0 ? 'text-bg-danger' : 'text-bg-success' ) : 'text-bg-secondary'
						]
					],
					[
						'title'     => __( 'Vulnerable Assets', 'wp-simple-firewall' ),
						'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
						'href_text' => __( 'Scan Results', 'wp-simple-firewall' ),
						'content'   => $con->action_router->render( OverviewScans::class, [
							'count' => $scansCon->WPV()->isEnabled() ? $counter->countVulnerableAssets() : '-',
						] ),
						'width'     => 6,
						'classes'   => [
							'card' => $scansCon->WPV()->isEnabled() ?
								( $counter->countVulnerableAssets() > 0 ? 'text-bg-danger' : 'text-bg-success' ) : 'text-bg-secondary'
						]
					],
					[
						'title'     => __( 'Abandoned Plugins', 'wp-simple-firewall' ),
						'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
						'href_text' => __( 'Scan Results', 'wp-simple-firewall' ),
						'content'   => $con->action_router->render( OverviewScans::class, [
							'count' => $scansCon->APC()->isEnabled() ? $counter->countAbandoned() : '-',
						] ),
						'width'     => 6,
						'classes'   => [
							'card' => $scansCon->APC()->isEnabled() ?
								( $counter->countAbandoned() > 0 ? 'text-bg-danger' : 'text-bg-success' ) : 'text-bg-secondary'
						]
					],
				],
				'widget_general' => [
					[
						'title'     => __( 'IP Offenses', 'wp-simple-firewall' ),
						'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES ),
						'href_text' => __( 'IP Rules', 'wp-simple-firewall' ),
						'content'   => $con->action_router->render( OverviewIpOffenses::class, [
							'limit' => 5,
						] ),
						'width'     => 3,
					],
					[
						'title'     => __( 'IP Blocks', 'wp-simple-firewall' ),
						'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES ),
						'href_text' => __( 'IP Rules', 'wp-simple-firewall' ),
						'content'   => $con->action_router->render( OverviewIpBlocks::class, [
							'limit' => 5,
						] ),
						'width'     => 3,
					],
					[
						'title'     => __( 'Recent Reports', 'wp-simple-firewall' ),
						'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_LIST ),
						'href_text' => __( 'View/Create Reports', 'wp-simple-firewall' ),
						'content'   => $con->action_router->render( ReportsTable::class, [
							'reports_limit' => 5,
						] ),
					],
					[
						'title'     => __( 'Recent Activity', 'wp-simple-firewall' ),
						'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ),
						'href_text' => __( 'View Activity Logs', 'wp-simple-firewall' ),
						'content'   => $con->action_router->render( OverviewActivity::class, [
							'limit' => 5,
						] ),
					],
					[
						'title'     => __( 'Recent Traffic', 'wp-simple-firewall' ),
						'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ),
						'href_text' => __( 'View Site Traffic', 'wp-simple-firewall' ),
						'content'   => $con->action_router->render( OverviewTraffic::class, [
							'limit' => 5,
						] ),
					],
				],
			],
		];
	}
}