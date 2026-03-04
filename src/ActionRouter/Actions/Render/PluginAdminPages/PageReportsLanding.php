<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\ReportsTable;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageReportsLanding extends PageModeLandingBase {

	public const SLUG = 'plugin_admin_page_reports_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/reports_landing.twig';

	protected function getLandingTitle() :string {
		return __( 'Reports', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Review security reports and manage reporting settings.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'clipboard-data-fill';
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_REPORTS;
	}

	protected function isLandingInteractive() :bool {
		return true;
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool
	 * }>
	 */
	protected function getLandingTiles() :array {
		$tiles = [];
		foreach ( \array_keys( PluginNavs::reportsLandingWorkspaceDefinitions() ) as $subNav ) {
			$tiles[] = [
				'key'          => $subNav,
				'panel_target' => $subNav,
				'is_enabled'   => true,
				'is_disabled'  => false,
			];
		}
		return $tiles;
	}

	protected function getLandingPanel() :array {
		return [
			'active_target' => PluginNavs::SUBNAV_REPORTS_LIST,
		];
	}

	protected function getLandingHrefs() :array {
		$hrefs = [];
		foreach ( \array_keys( PluginNavs::reportsWorkspaceDefinitions() ) as $subNav ) {
			$hrefs[ 'reports_'.$subNav ] = self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_REPORTS, $subNav );
		}
		return $hrefs;
	}

	protected function getLandingVars() :array {
		return [
			'report_tiles' => $this->getReportsLandingTiles(),
		];
	}

	protected function getLandingStrings() :array {
		return [
			'landing_hint' => __( 'Select a reports area above to view details.', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   status:string,
	 *   icon_class:string,
	 *   title:string,
	 *   stat_line:string,
	 *   panel_title:string,
	 *   panel_status:string,
	 *   panel_variant:string,
	 *   panel_content:string,
	 *   panel_description:string,
	 *   panel_cta_href:string,
	 *   panel_cta_label:string
	 * }>
	 */
	private function getReportsLandingTiles() :array {
		$hrefs = $this->getLandingHrefs();
		$workspace = PluginNavs::reportsLandingWorkspaceDefinitions();
		$reportsTable = self::con()->action_router->render( ReportsTable::class );

		return [
			[
				'key'              => PluginNavs::SUBNAV_REPORTS_LIST,
				'panel_target'     => PluginNavs::SUBNAV_REPORTS_LIST,
				'is_enabled'       => true,
				'is_disabled'      => false,
				'status'           => 'warning',
				'icon_class'       => 'bi bi-file-text',
				'title'            => $workspace[ PluginNavs::SUBNAV_REPORTS_LIST ][ 'menu_title' ],
				'stat_line'        => __( 'Open security reports table', 'wp-simple-firewall' ),
				'panel_title'      => $workspace[ PluginNavs::SUBNAV_REPORTS_LIST ][ 'menu_title' ],
				'panel_status'     => 'warning',
				'panel_variant'    => 'reports_table',
				'panel_content'    => $reportsTable,
				'panel_description' => '',
				'panel_cta_href'   => $hrefs[ 'reports_'.PluginNavs::SUBNAV_REPORTS_LIST ] ?? '',
				'panel_cta_label'  => $workspace[ PluginNavs::SUBNAV_REPORTS_LIST ][ 'landing_cta' ],
			],
			[
				'key'              => PluginNavs::SUBNAV_REPORTS_ALERTS,
				'panel_target'     => PluginNavs::SUBNAV_REPORTS_ALERTS,
				'is_enabled'       => true,
				'is_disabled'      => false,
				'status'           => 'warning',
				'icon_class'       => 'bi bi-bell',
				'title'            => $workspace[ PluginNavs::SUBNAV_REPORTS_ALERTS ][ 'menu_title' ],
				'stat_line'        => __( 'Configuration page', 'wp-simple-firewall' ),
				'panel_title'      => $workspace[ PluginNavs::SUBNAV_REPORTS_ALERTS ][ 'menu_title' ],
				'panel_status'     => 'warning',
				'panel_variant'    => 'config_cta',
				'panel_content'    => '',
				'panel_description' => $workspace[ PluginNavs::SUBNAV_REPORTS_ALERTS ][ 'page_subtitle' ],
				'panel_cta_href'   => $hrefs[ 'reports_'.PluginNavs::SUBNAV_REPORTS_ALERTS ] ?? '',
				'panel_cta_label'  => $workspace[ PluginNavs::SUBNAV_REPORTS_ALERTS ][ 'landing_cta' ],
			],
			[
				'key'              => PluginNavs::SUBNAV_REPORTS_REPORTING,
				'panel_target'     => PluginNavs::SUBNAV_REPORTS_REPORTING,
				'is_enabled'       => true,
				'is_disabled'      => false,
				'status'           => 'warning',
				'icon_class'       => 'bi bi-sliders',
				'title'            => $workspace[ PluginNavs::SUBNAV_REPORTS_REPORTING ][ 'menu_title' ],
				'stat_line'        => __( 'Configuration page', 'wp-simple-firewall' ),
				'panel_title'      => $workspace[ PluginNavs::SUBNAV_REPORTS_REPORTING ][ 'menu_title' ],
				'panel_status'     => 'warning',
				'panel_variant'    => 'config_cta',
				'panel_content'    => '',
				'panel_description' => $workspace[ PluginNavs::SUBNAV_REPORTS_REPORTING ][ 'page_subtitle' ],
				'panel_cta_href'   => $hrefs[ 'reports_'.PluginNavs::SUBNAV_REPORTS_REPORTING ] ?? '',
				'panel_cta_label'  => $workspace[ PluginNavs::SUBNAV_REPORTS_REPORTING ][ 'landing_cta' ],
			],
		];
	}
}
