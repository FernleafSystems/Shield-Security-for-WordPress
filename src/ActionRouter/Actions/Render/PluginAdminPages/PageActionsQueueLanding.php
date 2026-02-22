<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageActionsQueueLanding extends PageModeLandingBase {

	public const SLUG = 'plugin_admin_page_actions_queue_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/actions_queue_landing.twig';

	protected function getLandingTitle() :string {
		return __( 'Actions Queue', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Review active issues and run the next action quickly.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'shield-shaded';
	}

	protected function getLandingContent() :array {
		$con = self::con();
		return [
			'needs_attention_queue' => $con->action_router->render( NeedsAttentionQueue::class ),
		];
	}

	protected function getLandingHrefs() :array {
		$con = self::con();
		return [
			'scan_results' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
			'scan_run'     => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RUN ),
		];
	}

	protected function getLandingStrings() :array {
		return [
			'cta_title'        => __( 'Quick Actions', 'wp-simple-firewall' ),
			'cta_scan_results' => __( 'Open Scan Results', 'wp-simple-firewall' ),
			'cta_scan_run'     => __( 'Run Manual Scan', 'wp-simple-firewall' ),
		];
	}
}
