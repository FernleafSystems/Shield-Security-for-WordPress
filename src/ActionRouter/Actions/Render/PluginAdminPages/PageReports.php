<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\GetOptionsForZoneComponents;

class PageReports extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_reports';

	protected function getPageContextualHrefs() :array {
		$con = self::con();
		$hrefs = [];
		$renderDefinition = $this->getCurrentSubNavRenderDefinition();
		if ( $renderDefinition[ 'show_create_action' ] && $con->caps->canReportsLocal() ) {
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
		return $definition[ 'page_title' ];
	}

	protected function getInnerPageSubTitle() :string {
		$definition = $this->getCurrentWorkspaceDefinition();
		return $definition[ 'page_subtitle' ];
	}

	private function buildContent() :array {
		$subNav = $this->getCurrentSubNav();
		$definition = $this->getCurrentSubNavRenderDefinition();

		return [
			$definition[ 'content_key' ] => self::con()->action_router->render(
				$definition[ 'render_action' ],
				$this->buildActionDataForSubNav( $subNav )
			),
		];
	}

	private function getCurrentSubNavRenderDefinition() :array {
		return $this->getWorkspaceDefinitionForSubNav( $this->getCurrentSubNav() );
	}

	private function getCurrentWorkspaceDefinition() :array {
		return $this->getWorkspaceDefinitionForSubNav( $this->getCurrentSubNav() );
	}

	private function getCurrentSubNav() :string {
		return $this->action_data[ 'nav_sub' ];
	}

	private function buildActionDataForSubNav( string $subNav ) :array {
		return $subNav === PluginNavs::SUBNAV_REPORTS_SETTINGS
			? $this->buildReportsSettingsActionData()
			: [];
	}

	/**
	 * @return array{
	 *   menu_title:string,
	 *   landing_cta:string,
	 *   page_title:string,
	 *   page_subtitle:string,
	 *   content_key:string,
	 *   render_action:string,
	 *   show_create_action:bool
	 * }
	 */
	private function getWorkspaceDefinitionForSubNav( string $subNav ) :array {
		$definitions = PluginNavs::reportsWorkspaceDefinitions();
		if ( !isset( $definitions[ $subNav ] ) ) {
			throw new \LogicException( 'Missing reports workspace definition for subnav: '.$subNav );
		}
		return $definitions[ $subNav ];
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

	protected function getRequiredDataKeys() :array {
		return [
			'nav_sub',
		];
	}
}
