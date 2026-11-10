<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageConfigureLanding extends PageDrillDownLandingBase {

	use BuildsConfigureLandingData;

	public const SLUG = 'plugin_admin_page_configure_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/configure_landing.twig';

	protected function getLandingTitle() :string {
		return __( 'Configure', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Check coverage and move from zone review into focused settings changes.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'gear';
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_CONFIGURE;
	}

	protected function getLandingVars() :array {
		$diagnosisAction = $this->buildAjaxRenderActionData( ConfigureDrillDownDiagnosis::class, [
			Constants::NAV_ID     => PluginNavs::NAV_ZONES,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ZONES_OVERVIEW,
		] );
		$searchAction = $this->buildAjaxRenderActionData( ConfigureSearchResults::class, [
			Constants::NAV_ID     => PluginNavs::NAV_ZONES,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ZONES_OVERVIEW,
		] );
		return \array_merge(
			parent::getLandingVars(),
			[
				'configure_ajax'                  => [
					'diagnosis_render_action_json' => OperatorChromeContract::encodeJson( $diagnosisAction ),
					'search_render_action_json'    => OperatorChromeContract::encodeJson( $searchAction ),
				],
				'configure_focus_request_json'    => $this->buildRequestedConfigureFocusRequestJson(),
				'configure_component_action_json' => OperatorChromeContract::encodeJson( $this->buildRequestedComponentAction() ),
				'configure_tabs'                  => $this->buildConfigureTabs(),
				'configure_current_tab'           => $this->getValidRequestedConfigureZoneKey() ?: 'search',
			]
		);
	}

	private function buildRequestedComponentAction() :array {
		$con = self::con();
		$slug = $this->getTextInputFromRequestOrActionData( 'component' );
		if ( !isset( $con->comps->zones->enumZoneComponents()[ $slug ] ) ) {
			return [];
		}

		$component = $con->comps->zones->getZoneComponent( $slug );
		$options = \array_values( \array_filter(
			$component->getOptions(),
			static fn( string $key ) :bool => !\in_array(
				$con->cfg->configuration->options[ $key ][ 'section' ] ?? '',
				[ '', 'section_hidden', 'section_deprecated' ],
				true
			)
		) );
		if ( empty( $options ) ) {
			return [];
		}

		$data = $component->getActions()[ 'config' ][ 'data' ];
		$data[ 'option_keys' ] = \implode( ',', $options );
		$data[ 'form_context' ] = 'offcanvas';
		$configItem = $this->getTextInputFromRequestOrActionData( 'config_item' );
		if ( \in_array( $configItem, $options, true ) ) {
			$data[ 'config_item' ] = $configItem;
		}
		return $this->buildAjaxRenderActionData( ZoneComponentConfig::class, $data );
	}

	protected function getLandingStrings() :array {
		return [
			'diagnosis_loading'   => __( 'Loading diagnosis...', 'wp-simple-firewall' ),
			'settings_loading'    => __( 'Loading settings.', 'wp-simple-firewall' ),
			'layer_load_error'    => __( 'Unable to load this step right now.', 'wp-simple-firewall' ),
			'layer_retry'         => __( 'Try again', 'wp-simple-firewall' ),
			'search_label'        => __( 'Search every setting', 'wp-simple-firewall' ),
			'search_placeholder'  => __( 'Search every setting, such as "2FA", "login" or "email"', 'wp-simple-firewall' ),
			'search_tab'          => __( 'Search', 'wp-simple-firewall' ),
			'tabs_label'          => __( 'Configure', 'wp-simple-firewall' ),
			'search_loading'      => __( 'Searching Configure...', 'wp-simple-firewall' ),
		];
	}

	protected function getOperatorRootStep() :array {
		return \array_replace(
			parent::getOperatorRootStep(),
			$this->buildConfigureOperatorRootStep()
		);
	}

	protected function getLayers() :array {
		$selectedZoneKey = $this->getValidRequestedConfigureZoneKey();
		$selectedDiagnosis = $selectedZoneKey !== ''
			? $this->getConfigureZoneDiagnosis( $selectedZoneKey )
			: [];

		return [
			[
				'key'    => 'zones',
				'body'   => $this->renderConfigureZonesLayer(),
				'header' => [
					'breadcrumb_label'   => __( 'Zones', 'wp-simple-firewall' ),
					'title'              => __( 'Zones', 'wp-simple-firewall' ),
					'summary'            => __( 'Review posture by zone and choose where to focus next.', 'wp-simple-firewall' ),
					'next_step'          => __( 'Open one zone to continue.', 'wp-simple-firewall' ),
					'icon_class'         => 'bi bi-grid-1x2',
					'badge_status'       => 'good',
					'color_key'          => 'good',
				],
			],
			[
				'key'    => 'diagnosis',
				'body'   => $selectedZoneKey !== ''
					? $this->renderConfigureDiagnosisLayer( $selectedZoneKey )
					: '',
				'header' => $selectedZoneKey !== ''
					? $selectedDiagnosis[ 'header' ]
					: [
						'breadcrumb_label'   => __( 'Review findings', 'wp-simple-firewall' ),
						'title'              => __( 'Review findings', 'wp-simple-firewall' ),
						'summary'            => __( 'Open one zone to continue.', 'wp-simple-firewall' ),
						'next_step'          => __( 'Choose the zone that needs the next settings change.', 'wp-simple-firewall' ),
						'icon_class'         => 'bi bi-sliders',
						'badge'              => __( 'Select', 'wp-simple-firewall' ),
						'badge_status'       => 'neutral',
						'color_key'          => 'neutral',
					],
			],
		];
	}

	protected function getActiveLayerIndex() :int {
		return $this->getValidRequestedConfigureZoneKey() !== '' ? 1 : 0;
	}

	/**
	 * @param class-string<\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender> $renderAction
	 * @param array<string,mixed> $auxData
	 * @return array<string,mixed>
	 */
	protected function buildAjaxRenderActionData( string $renderAction, array $auxData = [] ) :array {
		return ActionData::BuildAjaxRender( $renderAction, $auxData );
	}
}
