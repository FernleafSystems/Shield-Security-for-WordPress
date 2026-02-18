<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Placeholders\PlaceholderMeter;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter\MeterSummary;

class PageDashboardOverviewSimple extends BasePluginAdminPage {

	public const SLUG = 'plugin_admin_page_dashboard_overview_simple';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/dashboard_overview_simple.twig';

	protected function getRenderData() :array {
		$con = self::con();

		return [
			'content' => [
				'hero_meter' => $con->action_router->render( PlaceholderMeter::class, [
					'meter_slug' => MeterSummary::SLUG,
					'is_hero'    => true,
				] ),
				'needs_attention_queue' => $con->action_router->render( NeedsAttentionQueue::class ),
			],
			'hrefs'   => [
				'grades_page' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_GRADES ),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( 'speedometer' ),
			],
			'strings' => [
				'inner_page_title'     => __( 'Security Overview', 'wp-simple-firewall' ),
				'inner_page_subtitle'  => __( 'Your entire WordPress site security at a glance.', 'wp-simple-firewall' ),
				'view_all_grades_link' => __( 'View All Security Grades', 'wp-simple-firewall' ),
			],
		];
	}
}
