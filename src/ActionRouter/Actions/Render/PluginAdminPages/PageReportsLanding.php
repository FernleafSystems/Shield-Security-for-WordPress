<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\{
	ChartsSummary,
	ReportsTable
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageReportsLanding extends PageModeLandingBase {

	public const SLUG = 'plugin_admin_page_reports_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/reports_landing.twig';

	protected function getLandingTitle() :string {
		return __( 'Reports', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Review report trends and open the full reporting workspace.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'clipboard-data-fill';
	}

	protected function getLandingContent() :array {
		$con = self::con();
		return [
			'summary_charts' => $con->action_router->render( ChartsSummary::class ),
			'recent_reports' => $con->action_router->render( ReportsTable::class, [
				'reports_limit' => 5,
			] ),
		];
	}

	protected function getLandingHrefs() :array {
		return [
			'reports_list'     => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_LIST ),
			'reports_charts'   => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_CHARTS ),
			'reports_settings' => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_SETTINGS ),
		];
	}

	protected function getLandingStrings() :array {
		return [
			'cta_reports_list'     => __( 'Open Reports List', 'wp-simple-firewall' ),
			'cta_reports_charts'   => __( 'Open Charts & Trends', 'wp-simple-firewall' ),
			'cta_reports_settings' => __( 'Open Alert Settings', 'wp-simple-firewall' ),
		];
	}
}
