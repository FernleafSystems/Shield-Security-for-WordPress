<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\ReportsTable;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageReportsLanding extends PageModeLandingBase {

	public const SLUG = 'plugin_admin_page_reports_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/reports_landing.twig';

	protected function getLandingTitle() :string {
		return __( 'Reports', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Review security reports and manage reporting and alert settings.', 'wp-simple-firewall' );
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
 *   step:array<string,string>,
 *   step_json:string
 * }>
 */
	private function getReportsLandingTiles() :array {
		$workspace = PluginNavs::reportsLandingWorkspaceDefinitions();
		$reportsTable = self::con()->action_router->render( ReportsTable::class );
		$settingsDefinition = $workspace[ PluginNavs::SUBNAV_REPORTS_SETTINGS ];
		$settingsForm = self::con()->action_router->render(
			OptionsFormFor::class,
			( new ReportsSettingsActionDataBuilder() )->build( $settingsDefinition[ 'config_zone_component_slugs' ] )
		);

		$listStep = $this->buildTileStep(
			$workspace[ PluginNavs::SUBNAV_REPORTS_LIST ][ 'menu_title' ],
			__( 'Review security reports and report output.', 'wp-simple-firewall' ),
			__( 'Use the table to inspect report history and open the next report details you need.', 'wp-simple-firewall' ),
			'bi bi-file-text',
			'warning',
			__( 'Reports', 'wp-simple-firewall' )
		);
		$settingsStep = $this->buildTileStep(
			$settingsDefinition[ 'menu_title' ],
			$settingsDefinition[ 'page_subtitle' ],
			__( 'Adjust reporting and alert settings inline, then save your changes.', 'wp-simple-firewall' ),
			'bi bi-sliders',
			'warning',
			__( 'Settings', 'wp-simple-firewall' )
		);

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
				'step'             => $listStep,
				'step_json'        => $this->encodeJson( $listStep ),
			],
			[
				'key'              => PluginNavs::SUBNAV_REPORTS_SETTINGS,
				'panel_target'     => PluginNavs::SUBNAV_REPORTS_SETTINGS,
				'is_enabled'       => true,
				'is_disabled'      => false,
				'status'           => 'warning',
				'icon_class'       => 'bi bi-sliders',
				'title'            => $settingsDefinition[ 'menu_title' ],
				'stat_line'        => __( 'Inline settings', 'wp-simple-firewall' ),
				'panel_title'      => $settingsDefinition[ 'menu_title' ],
				'panel_status'     => 'warning',
				'panel_variant'    => 'config_form',
				'panel_content'    => $settingsForm,
				'panel_description' => $settingsDefinition[ 'page_subtitle' ],
				'step'             => $settingsStep,
				'step_json'        => $this->encodeJson( $settingsStep ),
			],
		];
	}

	protected function getOperatorRootStep() :array {
		return \array_replace(
			parent::getOperatorRootStep(),
			[
				'focus'     => __( 'Security reports and reporting settings stay in the same in-page workspace.', 'wp-simple-firewall' ),
				'next_step' => __( 'Choose a reports area to review or update.', 'wp-simple-firewall' ),
			]
		);
	}

	private function buildTileStep(
		string $label,
		string $summary,
		string $nextStep,
		string $iconClass,
		string $status,
		string $badge
	) :array {
		return $this->normalizeOperatorChromeStep( [
			'breadcrumb_label' => $label,
			'title'            => $label,
			'summary'          => \trim( $summary ),
			'focus'            => '',
			'next_step'        => \trim( $nextStep ),
			'icon_class'       => $iconClass,
			'badge'            => $badge,
			'badge_status'     => $status,
			'color_key'        => $status,
		] );
	}
}
