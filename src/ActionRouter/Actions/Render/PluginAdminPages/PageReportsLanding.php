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
		$hrefs = [];
		foreach ( \array_keys( PluginNavs::reportsWorkspaceDefinitions() ) as $subNav ) {
			$hrefs[ 'reports_'.$subNav ] = self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_REPORTS, $subNav );
		}
		return $hrefs;
	}

	protected function getLandingStrings() :array {
		$strings = [];
		foreach ( PluginNavs::reportsWorkspaceDefinitions() as $subNav => $definition ) {
			$strings[ 'cta_reports_'.$subNav ] = (string)( $definition[ 'landing_cta' ] ?? '' );
		}
		return $strings;
	}
}
