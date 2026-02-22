<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse\Container as IpAnalyseContainer;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

class PageInvestigateLanding extends PageModeLandingBase {

	public const SLUG = 'plugin_admin_page_investigate_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_landing.twig';

	protected function getLandingTitle() :string {
		return __( 'Investigate', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Investigate user activity, request logs, and IP behavior.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'search';
	}

	protected function getLandingContent() :array {
		$con = self::con();
		$lookup = $this->getByIpLookup();

		$analysisContent = '';
		if ( $lookup[ 'is_valid' ] ) {
			$analysisContent = $con->action_router->render( IpAnalyseContainer::class, [ 'ip' => $lookup[ 'ip' ] ] );
		}

		return [
			'by_ip_analysis' => $analysisContent,
		];
	}

	protected function getLandingFlags() :array {
		$lookup = $this->getByIpLookup();
		$isValidIp = $lookup[ 'is_valid' ];
		return [
			'has_by_ip_lookup' => $lookup[ 'has_lookup' ],
			'by_ip_is_valid'   => $isValidIp,
		];
	}

	protected function getLandingHrefs() :array {
		$con = self::con();
		return [
			'activity_log' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ),
			'traffic_log'  => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ),
			'live_traffic' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE ),
			'ip_rules'     => $con->plugin_urls->adminTopNav( PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES ),
			'by_user'      => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_USER ),
			'by_ip'        => $con->plugin_urls->investigateByIp(),
		];
	}

	protected function getLandingStrings() :array {
		return [
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
		];
	}

	protected function getLandingVars() :array {
		return [
			'by_ip_value' => $this->getByIpLookup()[ 'ip' ],
		];
	}

	private function getByIpLookup() :array {
		$ip = \trim( sanitize_text_field( (string)Services::Request()->query( 'analyse_ip', '' ) ) );
		$hasLookup = !empty( $ip );
		return [
			'ip'         => $ip,
			'has_lookup' => $hasLookup,
			'is_valid'   => $hasLookup && Services::IP()->isValidIp( $ip ),
		];
	}
}
