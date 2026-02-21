<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse\Container as IpAnalyseContainer;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

class PageInvestigateLanding extends BasePluginAdminPage {

	public const SLUG = 'plugin_admin_page_investigate_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_landing.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$request = Services::Request();
		$ip = \trim( sanitize_text_field( (string)$request->query( 'analyse_ip', '' ) ) );
		$hasIpLookup = !empty( $ip );
		$isValidIp = $hasIpLookup && Services::IP()->isValidIp( $ip );

		$analysisContent = '';
		if ( $isValidIp ) {
			$analysisContent = $con->action_router->render( IpAnalyseContainer::class, [ 'ip' => $ip ] );
		}

		return [
			'content' => [
				'by_ip_analysis' => $analysisContent,
			],
			'flags'   => [
				'has_by_ip_lookup' => $hasIpLookup,
				'by_ip_is_valid'   => $isValidIp,
			],
			'hrefs'   => [
				'activity_log' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ),
				'traffic_log'  => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ),
				'live_traffic' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE ),
				'ip_rules'     => $con->plugin_urls->adminTopNav( PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES ),
				'by_user'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_USER ),
				'by_ip'        => $con->plugin_urls->investigateByIp(),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( 'search' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Investigate', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Investigate user activity, request logs, and IP behavior.', 'wp-simple-firewall' ),
				'tools_title'         => __( 'Investigate Tools', 'wp-simple-firewall' ),
				'selectors_title'     => __( 'Subject Selectors', 'wp-simple-firewall' ),
				'tool_activity'       => __( 'WP Activity Log', 'wp-simple-firewall' ),
				'tool_traffic'        => __( 'HTTP Request Log', 'wp-simple-firewall' ),
				'tool_live'           => __( 'Live HTTP Log', 'wp-simple-firewall' ),
				'tool_ip_rules'       => __( 'IP Rules', 'wp-simple-firewall' ),
				'by_user_title'       => __( 'By User', 'wp-simple-firewall' ),
				'by_ip_title'         => __( 'By IP Address', 'wp-simple-firewall' ),
				'lookup_user'         => __( 'User ID, username, or email', 'wp-simple-firewall' ),
				'lookup_ip'           => __( 'IP address', 'wp-simple-firewall' ),
				'go_user'             => __( 'Investigate User', 'wp-simple-firewall' ),
				'go_ip'               => __( 'Analyze IP', 'wp-simple-firewall' ),
				'by_ip_results_title' => __( 'IP Analysis', 'wp-simple-firewall' ),
				'by_ip_invalid_title' => __( 'Invalid IP Address', 'wp-simple-firewall' ),
				'by_ip_invalid_text'  => __( 'Enter a valid IPv4 or IPv6 address to run IP analysis.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'by_ip_value' => $ip,
			],
		];
	}
}
