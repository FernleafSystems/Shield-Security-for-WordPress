<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\MeterCard;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Component\Base as MeterComponent,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Secadmin;

class PageConfigureLanding extends BasePluginAdminPage {

	public const SLUG = 'plugin_admin_page_configure_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/configure_landing.twig';

	protected function getRenderData() :array {
		$con = self::con();

		$heroMeter = $con->action_router->render( MeterCard::class, [
			'meter_slug'    => MeterSummary::SLUG,
			'meter_channel' => MeterComponent::CHANNEL_CONFIG,
			'is_hero'       => true,
		] );

		return [
			'content' => [
				'hero_meter' => $heroMeter,
			],
			'hrefs'   => [
				'grades'       => $con->plugin_urls->adminTopNav( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_GRADES ),
				'zones_home'   => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ZONES, Secadmin::Slug() ),
				'rules_manage' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_RULES, PluginNavs::SUBNAV_RULES_MANAGE ),
				'tools_import' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_IMPORT ),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( 'gear' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Configure', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Check posture and jump to core security configuration areas.', 'wp-simple-firewall' ),
				'posture_title'       => __( 'Configuration Posture', 'wp-simple-firewall' ),
				'quick_links_title'   => __( 'Quick Links', 'wp-simple-firewall' ),
				'link_grades'         => __( 'Security Grades', 'wp-simple-firewall' ),
				'link_zones'          => __( 'Security Zones', 'wp-simple-firewall' ),
				'link_rules'          => __( 'Rules Manager', 'wp-simple-firewall' ),
				'link_tools'          => __( 'Import/Export Tool', 'wp-simple-firewall' ),
			],
		];
	}
}
