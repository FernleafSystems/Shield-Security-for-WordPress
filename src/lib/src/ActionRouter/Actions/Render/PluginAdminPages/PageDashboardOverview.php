<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\MeterCardPrimary;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\ChartsSummary;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\ReportsTable;
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
						'href'    => $con->plugin_urls->adminTopNav( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_GRADES ),
						'title'   => __( 'View All Security Grades', 'wp-simple-firewall' ),
						'content' => $con->action_router->render( MeterCardPrimary::class, [
							'meter_slug' => MeterSummary::SLUG,
							'meter_data' => ( new Handler() )->getMeter( MeterSummary::class ),
						] ),
					],
					[
						'href'    => $con->plugin_urls->adminTopNav( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_LIST ),
						'title'   => __( 'View/Create Reports', 'wp-simple-firewall' ),
						'content' => $con->action_router->render( ReportsTable::class, [
							'reports_limit' => 5,
						] ),
					],
				],
			],
		];
	}
}