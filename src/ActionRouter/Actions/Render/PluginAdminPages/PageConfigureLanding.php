<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageConfigureLanding extends PageDrillDownLandingBase {

	use BuildsConfigureLandingData;

	public const SLUG = 'plugin_admin_page_configure_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/configure_landing.twig';

	protected function getLandingTitle() :string {
		return __( 'Configure', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Check posture and move from zone review into focused settings changes.', 'wp-simple-firewall' );
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
		$editorAction = $this->buildAjaxRenderActionData( ConfigureDrillDownEditor::class, [
			Constants::NAV_ID     => PluginNavs::NAV_ZONES,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ZONES_OVERVIEW,
		] );
		return \array_merge(
			parent::getLandingVars(),
			[
				'configure_ajax'          => [
					'diagnosis_render_action'      => $diagnosisAction,
					'diagnosis_render_action_json' => $this->encodeJson( $diagnosisAction ),
					'editor_render_action'         => $editorAction,
					'editor_render_action_json'    => $this->encodeJson( $editorAction ),
				],
			]
		);
	}

	protected function getLandingStrings() :array {
		return [
			'posture_title'       => __( 'Configuration Posture', 'wp-simple-firewall' ),
			'diagnosis_loading'   => __( 'Loading diagnosis...', 'wp-simple-firewall' ),
			'editor_loading'      => __( 'Loading settings...', 'wp-simple-firewall' ),
			'layer_load_error'    => __( 'Unable to load this step right now.', 'wp-simple-firewall' ),
			'layer_retry'         => __( 'Try again', 'wp-simple-firewall' ),
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
					'compact_back_label' => sprintf( __( 'Back to %s', 'wp-simple-firewall' ), __( 'Configure', 'wp-simple-firewall' ) ),
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
						'compact_back_label' => sprintf( __( 'Back to %s', 'wp-simple-firewall' ), __( 'Review Findings', 'wp-simple-firewall' ) ),
						'active_back_label'  => sprintf( __( 'Back to %s', 'wp-simple-firewall' ), __( 'Configure', 'wp-simple-firewall' ) ),
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
			[
				'key'    => 'editor',
				'body'   => '',
				'header' => [
					'compact_back_label' => sprintf( __( 'Back to %s', 'wp-simple-firewall' ), __( 'Settings', 'wp-simple-firewall' ) ),
					'active_back_label'  => sprintf( __( 'Back to %s', 'wp-simple-firewall' ), __( 'Review findings', 'wp-simple-firewall' ) ),
					'breadcrumb_label'   => __( 'Settings', 'wp-simple-firewall' ),
					'title'              => __( 'Open settings', 'wp-simple-firewall' ),
					'summary'            => __( 'Save your changes when you are done.', 'wp-simple-firewall' ),
					'next_step'          => __( 'Review the form and save the settings update.', 'wp-simple-firewall' ),
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
	 * @param class-string<BasePluginAdminPage|PageModeLandingBase> $renderAction
	 * @param array<string,mixed> $auxData
	 * @return array<string,mixed>
	 */
	protected function buildAjaxRenderActionData( string $renderAction, array $auxData = [] ) :array {
		return ActionData::BuildAjaxRender( $renderAction, $auxData );
	}
}
