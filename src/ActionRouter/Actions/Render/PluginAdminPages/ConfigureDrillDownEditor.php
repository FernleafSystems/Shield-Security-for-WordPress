<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class ConfigureDrillDownEditor extends ConfigureDrillDownRenderBase {

	public const SLUG = 'configure_drill_down_editor';
	public const TEMPLATE = '/wpadmin/components/configure/layer_editor.twig';

	protected function getRenderData() :array {
		$zoneKey = $this->getSelectedConfigureZoneKey();
		$diagnosis = $this->getConfigureZoneDiagnosis( $zoneKey );

		return [
			'zone'             => $this->getConfigureLandingTileLookup()[ $zoneKey ],
			'diagnosis'        => $diagnosis,
			'editor'           => [
				'container_id' => 'configure-editor-'.$zoneKey,
			],
			'header'           => $diagnosis[ 'editor_header' ],
			'editor_selection' => $diagnosis[ 'editor_selection' ],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'zone',
		];
	}
}
