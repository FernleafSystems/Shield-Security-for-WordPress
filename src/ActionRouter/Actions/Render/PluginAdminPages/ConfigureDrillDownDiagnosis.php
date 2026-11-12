<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class ConfigureDrillDownDiagnosis extends ConfigureDrillDownRenderBase {

	public const SLUG = 'configure_drill_down_diagnosis';
	public const TEMPLATE = '/wpadmin/components/configure/layer_diagnosis.twig';

	protected function getRenderData() :array {
		$zoneKey = $this->getSelectedConfigureZoneKey();
		$data = $this->buildConfigureDiagnosisRenderData( $zoneKey );
		if ( $this->fields()->flag( 'include_landing_refresh', '1', '0', false ) ) {
			$data[ 'landing_refresh' ] = $this->buildConfigureLandingRefresh();
		}
		return $data;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'zone',
		];
	}

	protected function promotedRenderDataKeys() :array {
		return [
			'zone_selection',
			'landing_refresh',
		];
	}
}
