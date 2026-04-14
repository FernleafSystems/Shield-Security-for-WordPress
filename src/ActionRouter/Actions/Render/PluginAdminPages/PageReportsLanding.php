<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\{
	ChartsTrends,
	ReportsTable
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageReportsLanding extends PageDrillDownLandingBase {

	public const SLUG = 'plugin_admin_page_reports_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/reports_landing.twig';

	/**
	 * @var array{
	 *   cards:list<array{
	 *     key:string,
	 *     tile:array{
	 *       tag:string,
	 *       status:string,
	 *       icon_class:string,
	 *       title:string,
	 *       status_label:string,
	 *       oneliner:string,
	 *       data_drill_target:string,
	 *       data_drill_zone_selection:string,
	 *       data_drill_bucket_selection:string,
	 *       data_drill_group_selection:string,
	 *       data_reports_workspace_selection:string,
	 *       is_disabled:bool,
	 *       class_name:string,
	 *       footer_links:list<array<string,string>>
	 *     }
	 *   }>,
	 *   panels:list<array{
	 *     key:string,
	 *     description:string,
	 *     body:string,
	 *     data_reports_workspace_selection:string,
	 *     is_default:bool
	 *   }>
	 * }|null
	 */
	private ?array $workspaceContractsCache = null;

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

	protected function getLayers() :array {
		return [
			[
				'key'    => 'workspaces',
				'body'   => $this->renderWorkspacesLayer(),
				'header' => [
					'compact_back_label' => sprintf( __( 'Back to %s', 'wp-simple-firewall' ), __( 'Reports', 'wp-simple-firewall' ) ),
					'breadcrumb_label'   => __( 'Workspaces', 'wp-simple-firewall' ),
					'title'              => __( 'Workspaces', 'wp-simple-firewall' ),
					'summary'            => __( 'Choose the reports workspace you want to open.', 'wp-simple-firewall' ),
					'next_step'          => __( 'Open reports, adjust reporting settings, or chart recent trends.', 'wp-simple-firewall' ),
					'icon_class'         => 'bi bi-grid-1x2',
					'badge'              => __( 'Choose', 'wp-simple-firewall' ),
					'badge_status'       => 'neutral',
					'color_key'          => 'reports',
				],
			],
			[
				'key'    => 'workspace',
				'body'   => $this->renderWorkspaceLayer(),
				'header' => $this->buildDefaultWorkspaceHeader(),
			],
		];
	}

	protected function getActiveLayerIndex() :int {
		return 0;
	}

	protected function getOperatorRootStep() :array {
		return \array_replace(
			parent::getOperatorRootStep(),
			[
				'focus'     => __( 'Security reports, delivery settings, and charts stay in the same in-page workspace.', 'wp-simple-firewall' ),
				'next_step' => __( 'Choose a reports area to review, configure, or chart.', 'wp-simple-firewall' ),
			]
		);
	}

	protected function renderWorkspacesLayer() :string {
		$workspaces = $this->getWorkspaceCards();

		return self::con()->comps->render
			->setTemplate( '/wpadmin/components/reports/layer_workspaces.twig' )
			->setData( [
				'workspaces' => $workspaces,
			] )
			->render();
	}

	protected function renderWorkspaceLayer() :string {
		$workspaces = $this->getWorkspacePanels();

		return self::con()->comps->render
			->setTemplate( '/wpadmin/components/reports/layer_workspace.twig' )
			->setData( [
				'workspaces' => $workspaces,
			] )
			->render();
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   tile:array{
	 *     tag:string,
	 *     status:string,
	 *     icon_class:string,
	 *     title:string,
	 *     status_label:string,
	 *     oneliner:string,
	 *     data_drill_target:string,
	 *     data_drill_zone_selection:string,
	 *     data_drill_bucket_selection:string,
	 *     data_drill_group_selection:string,
	 *     data_reports_workspace_selection:string,
	 *     is_disabled:bool,
	 *     class_name:string,
	 *     footer_links:list<array<string,string>>
	 *   }
	 * }>
	 */
	protected function getWorkspaceCards() :array {
		return $this->getWorkspaceContracts()[ 'cards' ];
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   description:string,
	 *   body:string,
	 *   data_reports_workspace_selection:string,
	 *   is_default:bool
	 * }>
	 */
	protected function getWorkspacePanels() :array {
		return $this->getWorkspaceContracts()[ 'panels' ];
	}

	/**
	 * @return array{
	 *   cards:list<array{
	 *     key:string,
	 *     tile:array{
	 *       tag:string,
	 *       status:string,
	 *       icon_class:string,
	 *       title:string,
	 *       status_label:string,
	 *       oneliner:string,
	 *       data_drill_target:string,
	 *       data_drill_zone_selection:string,
	 *       data_drill_bucket_selection:string,
	 *       data_drill_group_selection:string,
	 *       data_reports_workspace_selection:string,
	 *       is_disabled:bool,
	 *       class_name:string,
	 *       footer_links:list<array<string,string>>
	 *     }
	 *   }>,
	 *   panels:list<array{
	 *     key:string,
	 *     description:string,
	 *     body:string,
	 *     data_reports_workspace_selection:string,
	 *     is_default:bool
	 *   }>
	 * }
	 */
	private function getWorkspaceContracts() :array {
		if ( $this->workspaceContractsCache !== null ) {
			return $this->workspaceContractsCache;
		}

		$workspaceDefinitions = PluginNavs::reportsLandingWorkspaceDefinitions();
		$settingsDefinition = $workspaceDefinitions[ PluginNavs::SUBNAV_REPORTS_SETTINGS ];
		$workspaceBodies = [
			PluginNavs::SUBNAV_REPORTS_LIST     => self::con()->action_router->render( ReportsTable::class ),
			PluginNavs::SUBNAV_REPORTS_SETTINGS => self::con()->action_router->render(
				OptionsFormFor::class,
				( new ReportsSettingsActionDataBuilder() )->build( $settingsDefinition[ 'config_zone_component_slugs' ] )
			),
			PluginNavs::SUBNAV_REPORTS_CHARTS   => self::con()->action_router->render( ChartsTrends::class ),
		];
		$workspaceCopy = [
			PluginNavs::SUBNAV_REPORTS_LIST     => [
				'icon_class'   => 'bi bi-file-text',
				'status'       => 'neutral',
				'status_label' => __( 'Review', 'wp-simple-firewall' ),
				'oneliner'     => __( 'Inspect report history and open the report details you need next.', 'wp-simple-firewall' ),
				'description'  => '',
				'header'       => $this->buildWorkspaceHeader(
					$workspaceDefinitions[ PluginNavs::SUBNAV_REPORTS_LIST ][ 'menu_title' ],
					__( 'Review security reports and report output.', 'wp-simple-firewall' ),
					__( 'Use the table to inspect report history and open the next report details you need.', 'wp-simple-firewall' ),
					'bi bi-file-text',
					__( 'Reports', 'wp-simple-firewall' )
				),
			],
			PluginNavs::SUBNAV_REPORTS_SETTINGS => [
				'icon_class'   => 'bi bi-sliders',
				'status'       => 'neutral',
				'status_label' => __( 'Configure', 'wp-simple-firewall' ),
				'oneliner'     => __( 'Adjust reporting and alert settings inline without leaving the landing view.', 'wp-simple-firewall' ),
				'description'  => $settingsDefinition[ 'page_subtitle' ],
				'header'       => $this->buildWorkspaceHeader(
					$settingsDefinition[ 'menu_title' ],
					$settingsDefinition[ 'page_subtitle' ],
					__( 'Adjust reporting and alert settings inline, then save your changes.', 'wp-simple-firewall' ),
					'bi bi-sliders',
					__( 'Settings', 'wp-simple-firewall' )
				),
			],
			PluginNavs::SUBNAV_REPORTS_CHARTS   => [
				'icon_class'   => 'bi bi-graph-up-arrow',
				'status'       => 'neutral',
				'status_label' => __( 'Chart', 'wp-simple-firewall' ),
				'oneliner'     => __( 'Compare key security events over fixed reporting periods in one chart.', 'wp-simple-firewall' ),
				'description'  => '',
				'header'       => $this->buildWorkspaceHeader(
					$workspaceDefinitions[ PluginNavs::SUBNAV_REPORTS_CHARTS ][ 'menu_title' ],
					'',
					__( 'Select the events and period you want to compare, then update the chart.', 'wp-simple-firewall' ),
					'bi bi-graph-up-arrow',
					__( 'Trends', 'wp-simple-firewall' )
				),
			],
		];

		$cards = [];
		$panels = [];

		foreach ( $workspaceDefinitions as $workspaceKey => $workspaceDefinition ) {
			if ( !isset( $workspaceCopy[ $workspaceKey ], $workspaceBodies[ $workspaceKey ] ) ) {
				continue;
			}

			$selection = [
				'key'        => $workspaceKey,
				'label'      => $workspaceDefinition[ 'menu_title' ],
				'status'     => $workspaceCopy[ $workspaceKey ][ 'status' ],
				'icon_class' => $workspaceCopy[ $workspaceKey ][ 'icon_class' ],
				'header'     => $workspaceCopy[ $workspaceKey ][ 'header' ],
			];
			$selectionJson = OperatorChromeContract::encodeJson( $selection );

			$cards[] = [
				'key'  => $workspaceKey,
				'tile' => [
					'tag'               => 'button',
					'status'            => $workspaceCopy[ $workspaceKey ][ 'status' ],
					'icon_class'        => $workspaceCopy[ $workspaceKey ][ 'icon_class' ],
					'title'             => $workspaceDefinition[ 'menu_title' ],
					'status_label'      => $workspaceCopy[ $workspaceKey ][ 'status_label' ],
					'oneliner'          => $workspaceCopy[ $workspaceKey ][ 'oneliner' ],
					'data_drill_target' => 'workspace',
					'data_drill_zone_selection' => '',
					'data_drill_bucket_selection' => '',
					'data_drill_group_selection' => '',
					'data_reports_workspace_selection' => $selectionJson,
					'is_disabled'       => false,
					'class_name'        => 'operator-tile-card--reports',
					'footer_links'      => [],
				],
			];

			$panels[] = [
				'key'                              => $workspaceKey,
				'description'                      => $workspaceCopy[ $workspaceKey ][ 'description' ],
				'body'                             => $workspaceBodies[ $workspaceKey ],
				'data_reports_workspace_selection' => $selectionJson,
				'is_default'                       => $workspaceKey === PluginNavs::SUBNAV_REPORTS_LIST,
			];
		}

		$this->workspaceContractsCache = [
			'cards'  => $cards,
			'panels' => $panels,
		];

		return $this->workspaceContractsCache;
	}

	private function buildDefaultWorkspaceHeader() :array {
		return OperatorChromeContract::normalizeHeader( [
			'compact_back_label' => sprintf( __( 'Back to %s', 'wp-simple-firewall' ), __( 'Workspaces', 'wp-simple-firewall' ) ),
			'active_back_label'  => sprintf( __( 'Back to %s', 'wp-simple-firewall' ), __( 'Workspaces', 'wp-simple-firewall' ) ),
			'breadcrumb_label'   => __( 'Workspace', 'wp-simple-firewall' ),
			'title'              => __( 'Workspace', 'wp-simple-firewall' ),
			'summary'            => __( 'Choose a reports workspace to continue.', 'wp-simple-firewall' ),
			'next_step'          => __( 'Open reports, shared settings, or charts and trends.', 'wp-simple-firewall' ),
			'icon_class'         => 'bi bi-layout-text-window-reverse',
			'badge'              => __( 'Select', 'wp-simple-firewall' ),
			'badge_status'       => 'neutral',
			'color_key'          => 'neutral',
		] );
	}

	private function buildWorkspaceHeader(
		string $label,
		string $summary,
		string $nextStep,
		string $iconClass,
		string $badge
	) :array {
		return OperatorChromeContract::normalizeHeader( [
			'compact_back_label' => sprintf( __( 'Back to %s', 'wp-simple-firewall' ), __( 'Workspaces', 'wp-simple-firewall' ) ),
			'active_back_label'  => sprintf( __( 'Back to %s', 'wp-simple-firewall' ), __( 'Workspaces', 'wp-simple-firewall' ) ),
			'breadcrumb_label'   => $label,
			'title'              => $label,
			'summary'            => \trim( $summary ),
			'next_step'          => \trim( $nextStep ),
			'icon_class'         => $iconClass,
			'badge'              => $badge,
			'badge_status'       => 'neutral',
			'color_key'          => 'reports',
		] );
	}
}
