<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class TrafficLogLivePanelBody extends PageTrafficLogLive {

	public const SLUG = 'render_traffic_log_live_panel_body';
	public const TEMPLATE = '/wpadmin/components/investigate/live_traffic_body.twig';
}
