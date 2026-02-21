<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageActionsQueueLanding extends BasePluginAdminPage {

	public const SLUG = 'plugin_admin_page_actions_queue_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/actions_queue_landing.twig';

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'content' => [
				'needs_attention_queue' => $con->action_router->render( NeedsAttentionQueue::class ),
			],
			'hrefs'   => [
				'scan_results' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
				'scan_run'     => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RUN ),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( 'shield-shaded' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Actions Queue', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Review active issues and run the next action quickly.', 'wp-simple-firewall' ),
				'cta_title'           => __( 'Quick Actions', 'wp-simple-firewall' ),
				'cta_scan_results'    => __( 'Open Scan Results', 'wp-simple-firewall' ),
				'cta_scan_run'        => __( 'Run Manual Scan', 'wp-simple-firewall' ),
			],
		];
	}
}
