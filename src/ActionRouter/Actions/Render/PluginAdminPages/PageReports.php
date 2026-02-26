<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\GetOptionsForZoneComponents;

class PageReports extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_reports';

	protected function getPageContextualHrefs() :array {
		$con = self::con();
		$hrefs = [];
		$renderDefinition = $this->getCurrentSubNavRenderDefinition();
		if ( !empty( $renderDefinition )
			 && ( $renderDefinition[ 'show_create_action' ] ?? false )
			 && $con->caps->canReportsLocal() ) {
			\array_unshift( $hrefs, [
				'title'   => __( 'Create Custom Report', 'wp-simple-firewall' ),
				'href'    => '#',
				'classes' => [ 'offcanvas_report_create_form' ],
			] );
		}
		return $hrefs;
	}

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title'      => sprintf( '%s: %s', CommonDisplayStrings::get( 'help_label' ), __( 'Reports', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/collection/77-reporting',
			'new_window' => true,
		];
	}

	protected function getRenderData() :array {
		return [
			'content' => $this->buildContent(),
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->iconClass( 'clipboard-data-fill' ),
			],
			'strings' => [
				'inner_page_title'    => $this->getInnerPageTitle(),
				'inner_page_subtitle' => $this->getInnerPageSubTitle(),
			],
		];
	}

	protected function getInnerPageTitle() :string {
		$definition = $this->getCurrentWorkspaceDefinition();
		return !empty( $definition[ 'page_title' ] )
			? (string)$definition[ 'page_title' ]
			: CommonDisplayStrings::get( 'security_reports_label' );
	}

	protected function getInnerPageSubTitle() :string {
		$definition = $this->getCurrentWorkspaceDefinition();
		return !empty( $definition[ 'page_subtitle' ] )
			? (string)$definition[ 'page_subtitle' ]
			: __( 'Summary Security Reports.', 'wp-simple-firewall' );
	}

	private function buildContent() :array {
		$subNav = (string)( $this->action_data[ 'nav_sub' ] ?? '' );
		$definition = $this->getCurrentSubNavRenderDefinition();
		if ( empty( $definition[ 'content_key' ] ) || empty( $definition[ 'render_action' ] ) ) {
			return [];
		}

		return [
			(string)$definition[ 'content_key' ] => self::con()->action_router->render(
				(string)$definition[ 'render_action' ],
				$this->buildActionDataForSubNav( $subNav )
			),
		];
	}

	private function getSubNavRenderDefinitions() :array {
		return [
			PluginNavs::SUBNAV_REPORTS_LIST     => [
				'content_key'        => 'create_report',
				'render_action'      => Reports\PageReportsView::class,
				'show_create_action' => true,
			],
			PluginNavs::SUBNAV_REPORTS_CHARTS   => [
				'content_key'        => 'summary_charts',
				'render_action'      => Reports\ChartsSummary::class,
				'show_create_action' => false,
			],
			PluginNavs::SUBNAV_REPORTS_SETTINGS => [
				'content_key'        => 'alerts_settings',
				'render_action'      => OptionsFormFor::class,
				'show_create_action' => false,
			],
		];
	}

	private function getCurrentSubNavRenderDefinition() :array {
		$subNav = (string)( $this->action_data[ 'nav_sub' ] ?? '' );
		return $this->getSubNavRenderDefinitions()[ $subNav ] ?? [];
	}

	private function getCurrentWorkspaceDefinition() :array {
		$subNav = (string)( $this->action_data[ 'nav_sub' ] ?? '' );
		return PluginNavs::reportsWorkspaceDefinitions()[ $subNav ] ?? [];
	}

	private function buildActionDataForSubNav( string $subNav ) :array {
		return $subNav === PluginNavs::SUBNAV_REPORTS_SETTINGS
			? $this->buildReportsSettingsActionData()
			: [];
	}

	private function buildReportsSettingsActionData() :array {
		return [
			'options' => $this->buildReportsSettingsOptions(),
		];
	}

	private function buildReportsSettingsOptions() :array {
		return ( new GetOptionsForZoneComponents() )->run(
			PluginNavs::reportsSettingsZoneComponentSlugs()
		);
	}
}
