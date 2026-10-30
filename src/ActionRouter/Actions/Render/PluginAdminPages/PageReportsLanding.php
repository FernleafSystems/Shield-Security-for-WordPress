<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\{
	ChartsTrends,
	ReportsTable
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

/**
 * Reports is one page with tabs, like Investigate: charts and trends, the security reports, and the reporting
 * and alerts settings. The settings are Configure's "Reports and alerts" goal, so they look and save exactly
 * as they do there.
 *
 * @phpstan-type ReportsTab array{key:string,label:string,icon_class:string,href:string,is_current:bool}
 * @phpstan-type ReportsTabPanel array{key:string,body:string}
 */
class PageReportsLanding extends PageModeLandingBase {

	use BuildsConfigureLandingData;

	public const SLUG = 'plugin_admin_page_reports_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/reports_landing.twig';
	public const SETTINGS_GOAL = 'alerts';

	protected function getPageContextualHrefs() :array {
		return self::con()->caps->canReportsLocal() ? [ [
			'title' => __( 'Create Custom Report', 'wp-simple-firewall' ),
			'is_action' => true,
			'classes' => [ 'offcanvas_report_create_form' ],
		] ] : [];
	}

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title' => __( 'Reports Help', 'wp-simple-firewall' ),
			'href' => 'https://help.getshieldsecurity.com/collection/77-reporting',
			'new_window' => true,
		];
	}

	protected function getLandingVars() :array {
		return \array_merge( parent::getLandingVars(), [
			'reports_current_tab' => $this->getCurrentTab(),
			'reports_tabs'        => $this->buildTabs(),
			'reports_panels'      => $this->buildTabPanels(),
		] );
	}

	/**
	 * The tabs in the order they're shown. Each keeps the link it has always had.
	 * @return array{label:string,tabs:list<ReportsTab>}
	 */
	protected function buildTabs() :array {
		$definitions = PluginNavs::reportsWorkspaceDefinitions();
		$icons = [
			PluginNavs::SUBNAV_REPORTS_CHARTS   => 'bi bi-graph-up-arrow',
			PluginNavs::SUBNAV_REPORTS_LIST     => 'bi bi-file-text',
			PluginNavs::SUBNAV_REPORTS_SETTINGS => 'bi bi-sliders',
		];
		$tabs = [];
		foreach ( $icons as $key => $icon ) {
			$tabs[] = [
				'key'        => $key,
				'label'      => $definitions[ $key ][ 'menu_title' ],
				'icon_class' => $icon,
				'href'       => self::con()->plugin_urls->reportsHome( $key ),
				'is_current' => $key === $this->getCurrentTab(),
			];
		}
		return [
			'label' => __( 'Reports', 'wp-simple-firewall' ),
			'tabs'  => $tabs,
		];
	}

	/**
	 * @return list<ReportsTabPanel>
	 */
	protected function buildTabPanels() :array {
		$router = self::con()->action_router;
		return [
			[
				'key'  => PluginNavs::SUBNAV_REPORTS_CHARTS,
				'body' => $router->render( ChartsTrends::class ),
			],
			[
				'key'  => PluginNavs::SUBNAV_REPORTS_LIST,
				'body' => $router->render( ReportsTable::class ),
			],
			[
				'key'  => PluginNavs::SUBNAV_REPORTS_SETTINGS,
				'body' => $this->renderSettings(),
			],
		];
	}

	/**
	 * Configure's own panels for reports and alerts, without its filter and layout tools.
	 */
	protected function renderSettings() :string {
		$data = $this->buildConfigureDiagnosisRenderData( self::SETTINGS_GOAL );
		unset( $data[ 'zone_tools' ] );
		$data[ 'is_embedded' ] = true;
		return self::con()->comps->render
			->setTemplate( '/wpadmin/components/configure/layer_diagnosis.twig' )
			->setData( $data )
			->render();
	}

	protected function getCurrentTab() :string {
		$key = sanitize_key( $this->getTextInputFromRequestOrActionData( 'workspace' ) );
		return isset( PluginNavs::reportsWorkspaceDefinitions()[ $key ] ) ? $key : PluginNavs::SUBNAV_REPORTS_CHARTS;
	}

	protected function getLandingTitle() :string {
		return __( 'Reports', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Review security reports, manage delivery settings, and chart security trends.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'clipboard-data-fill';
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_REPORTS;
	}

	protected function getOperatorRootStep() :array {
		return \array_replace(
			parent::getOperatorRootStep(),
			[
				'focus'     => __( 'Charts, security reports, and delivery settings each have their own tab.', 'wp-simple-firewall' ),
				'next_step' => __( 'Choose the tab you need.', 'wp-simple-firewall' ),
			]
		);
	}
}
