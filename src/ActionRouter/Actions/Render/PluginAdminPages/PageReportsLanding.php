<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\{
	ChartsSummary,
	PageReportsView
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageReportsLanding extends BasePluginAdminPage {

	public const SLUG = 'plugin_admin_page_reports_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/reports_landing.twig';

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'content' => [
				'summary_charts' => $con->action_router->render( ChartsSummary::class ),
				'reports_view'   => $con->action_router->render( PageReportsView::class ),
			],
			'hrefs'   => [
				'reports_list' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_LIST ),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( 'clipboard-data-fill' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Reports', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Review report trends and open the full reporting workspace.', 'wp-simple-firewall' ),
				'cta_reports_list'    => __( 'Open Reports List', 'wp-simple-firewall' ),
			],
		];
	}
}
