<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\MeterCardPrimary;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\ChartsSummary;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\ReportsTable;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\OverviewActivity;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\OverviewIpBlocks;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\OverviewIpOffenses;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\OverviewTraffic;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ReportingChartSummary;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter\MeterSummary;

class PageDashboardOverview extends BasePluginAdminPage {

	public const SLUG = 'plugin_admin_page_dashboard_overview';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/dashboard_overview.twig';

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'ajax'    => [
				'render_summary_chart' => ActionData::BuildJson( ReportingChartSummary::class ),
			],
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
				'widgets' => [
					[
						'title'     => false,
						'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_GRADES ),
						'href_text' => __( 'View All Security Grades', 'wp-simple-firewall' ),
						'content'   => $con->action_router->render( MeterCardPrimary::class, [
							'meter_slug' => MeterSummary::SLUG,
							'meter_data' => ( new Handler() )->getMeter( MeterSummary::class ),
						] ),
					],
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
						'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_LOG ),
						'href_text' => __( 'View Activity Logs', 'wp-simple-firewall' ),
						'content'   => $con->action_router->render( OverviewActivity::class, [
							'limit' => 5,
						] ),
					],
					[
						'title'     => __( 'Recent Traffic', 'wp-simple-firewall' ),
						'href'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_TRAFFIC_LOG ),
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