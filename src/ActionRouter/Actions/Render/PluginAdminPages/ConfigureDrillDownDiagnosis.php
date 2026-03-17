<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class ConfigureDrillDownDiagnosis extends ConfigureDrillDownRenderBase {

	public const SLUG = 'configure_drill_down_diagnosis';
	public const TEMPLATE = '/wpadmin/components/configure/layer_diagnosis.twig';

	protected function getRenderData() :array {
		$zoneKey = $this->getSelectedConfigureZoneKey();
		$diagnosis = $this->getConfigureZoneDiagnosis( $zoneKey );
		$data = [
			'diagnosis'          => $diagnosis,
			'context'            => $diagnosis[ 'context' ],
			'strip_text'         => $diagnosis[ 'strip_text' ],
			'strip_badge'        => $diagnosis[ 'strip_badge' ],
			'strip_badge_status' => $diagnosis[ 'strip_badge_status' ],
			'zone_selection'     => $diagnosis[ 'zone_selection' ],
			'editor_selection'   => $diagnosis[ 'editor_selection' ],
		];
		if ( !empty( $this->action_data[ 'include_landing_refresh' ] ) ) {
			$data[ 'landing_refresh' ] = $this->buildConfigureLandingRefresh();
		}
		return $data;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'zone',
		];
	}
}
