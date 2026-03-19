<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions,
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
		$inlineSaveAction = ActionData::Build( Actions\ModuleOptionsSave::class );

		return \array_merge(
			parent::getLandingVars(),
			[
				'configure_posture_strip' => $this->getConfigurePostureStrip(),
				'configure_ajax'          => [
					'diagnosis_render_action'      => $diagnosisAction,
					'diagnosis_render_action_json' => $this->encodeJson( $diagnosisAction ),
					'editor_render_action'         => $editorAction,
					'editor_render_action_json'    => $this->encodeJson( $editorAction ),
					'inline_save_action_json'      => $this->encodeJson( $inlineSaveAction ),
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

	protected function getLayers() :array {
		$selectedZoneKey = $this->getValidRequestedConfigureZoneKey();
		$selectedDiagnosis = $selectedZoneKey !== ''
			? $this->getConfigureZoneDiagnosis( $selectedZoneKey )
			: [];

		return [
			[
				'key'          => 'zones',
				'label'        => $selectedZoneKey !== ''
					? $selectedDiagnosis[ 'strip_text' ]
					: __( 'Choose a zone', 'wp-simple-firewall' ),
				'badge'        => $selectedZoneKey !== ''
					? $selectedDiagnosis[ 'strip_badge' ]
					: '',
				'badge_status' => $selectedZoneKey !== ''
					? $selectedDiagnosis[ 'strip_badge_status' ]
					: 'neutral',
				'body'         => $this->renderConfigureZonesLayer(),
				'context'      => [
					'path'      => [ __( 'Configure', 'wp-simple-firewall' ) ],
					'focus'     => __( 'Choose the security zone you want to review.', 'wp-simple-firewall' ),
					'next_step' => __( 'Open a diagnosis before editing any settings.', 'wp-simple-firewall' ),
				],
			],
			[
				'key'          => 'diagnosis',
				'label'        => __( 'Review findings', 'wp-simple-firewall' ),
				'badge'        => '',
				'badge_status' => 'neutral',
				'body'         => $selectedZoneKey !== ''
					? $this->renderConfigureDiagnosisLayer( $selectedZoneKey )
					: '',
				'context'      => $selectedZoneKey !== ''
					? $selectedDiagnosis[ 'context' ]
					: [
						'path'      => [ __( 'Configure', 'wp-simple-firewall' ) ],
						'focus'     => __( 'Review why a zone needs attention before changing the settings.', 'wp-simple-firewall' ),
						'next_step' => __( 'Open one zone to continue.', 'wp-simple-firewall' ),
					],
			],
			[
				'key'          => 'editor',
				'label'        => __( 'Open settings', 'wp-simple-firewall' ),
				'badge'        => '',
				'badge_status' => 'neutral',
				'body'         => '',
				'context'      => [
					'path'      => [ __( 'Configure', 'wp-simple-firewall' ) ],
					'focus'     => __( 'Use focused settings for one zone at a time.', 'wp-simple-firewall' ),
					'next_step' => __( 'Save your changes when you are done.', 'wp-simple-firewall' ),
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

	private function encodeJson( array $data ) :string {
		return (string)( \json_encode( $data ) ?: '' );
	}
}
