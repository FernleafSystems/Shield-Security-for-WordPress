<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\Render\Components\Traffic\TrafficLiveLogs,
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageTrafficLogLive extends PageTrafficLogBase {

	public const SLUG = 'page_admin_plugin_traffic_log_live';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/traffic_logs_live.twig';

	protected function getPageContextualHrefs() :array {
		$hrefs = parent::getPageContextualHrefs();
		\array_unshift( $hrefs, [
			'title' => __( 'Switch To Normal Logs', 'wp-simple-firewall' ),
			'href'  => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ),
		] );
		return $hrefs;
	}

	protected function getRenderData() :array {
		$limit = $this->action_data[ 'limit' ] ?? 200;
		return [
			'ajax'    => [
				'load_live_logs' => ActionData::BuildJson( TrafficLiveLogs::class, true, [
					'limit' => \is_numeric( $limit ) ? $limit : 200,
				] ),
			],
			'flags'   => [
				'is_enabled' => self::con()->comps->opts_lookup->getTrafficLiveLogTimeRemaining() > 0,
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'stoplights' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Live Logs', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'View request logs as they reach your site.', 'wp-simple-firewall' ),
				'not_enabled'         => __( "The Live HTTP Request Logging option isn't switched-on, so you may not see many updates.", 'wp-simple-firewall' ),
				'toggle_live_log'     => __( "Toggle Live Traffic Logging", 'wp-simple-firewall' ),
			],
		];
	}
}