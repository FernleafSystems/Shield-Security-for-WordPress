<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type ConfigureLandingTile from ConfigureLandingRenderContracts
 * @phpstan-import-type ConfigureLandingViewData from ConfigureLandingRenderContracts
 * @phpstan-import-type DiagnosisContract from ConfigureLandingRenderContracts
 * @phpstan-import-type ZoneSection from ConfigureLandingRenderContracts
 * @phpstan-import-type OperatorChromeStep from OperatorChromeContract
 */
trait BuildsConfigureLandingData {

	private ?array $configureLandingViewDataCache = null;
	private ?array $configureFocusRequestCache = null;

	protected function getRequestedConfigureZoneKey() :string {
		return sanitize_key( $this->getTextInputFromRequestOrActionData( 'zone' ) );
	}

	protected function getValidRequestedConfigureZoneKey() :string {
		$zoneKey = $this->getRequestedConfigureZoneKey();
		return isset( $this->getConfigureLandingTileLookup()[ $zoneKey ] ) ? $zoneKey : '';
	}

	/**
	 * @return array{
	 *   row_key:string,
	 *   config_item:string
	 * }|array{}
	 */
	protected function getRequestedConfigureFocusRequest() :array {
		if ( $this->configureFocusRequestCache !== null ) {
			return $this->configureFocusRequestCache;
		}

		$focus = [];
		$zoneKey = $this->getValidRequestedConfigureZoneKey();
		if ( $zoneKey !== '' ) {
			$rowKey = sanitize_key( $this->getTextInputFromRequestOrActionData( 'row_key' ) );
			$configItem = sanitize_key( $this->getTextInputFromRequestOrActionData( 'config_item' ) );
			if ( empty( self::con()->cfg->configuration->options[ $configItem ] ?? [] ) ) {
				$configItem = '';
			}

			if ( $rowKey !== '' && \in_array( $rowKey, $this->getConfigureDiagnosisRowKeys( $zoneKey ), true ) ) {
				$focus = [
					'row_key'     => $rowKey,
					'config_item' => $configItem,
				];
			}
		}

		$this->configureFocusRequestCache = $focus;
		return $this->configureFocusRequestCache;
	}

	protected function buildRequestedConfigureFocusRequestJson() :string {
		$focus = $this->getRequestedConfigureFocusRequest();
		return empty( $focus ) ? '' : OperatorChromeContract::encodeJson( $focus );
	}

	/**
	 * @return ConfigureLandingViewData
	 */
	protected function getConfigureLandingViewData() :array {
		if ( $this->configureLandingViewDataCache === null ) {
			$this->configureLandingViewDataCache = $this->buildConfigureLandingViewData();
		}

		return $this->configureLandingViewDataCache;
	}

	/**
	 * @return ConfigureLandingViewData
	 */
	protected function buildConfigureLandingViewData() :array {
		return ( new ConfigureLandingViewBuilder() )->build();
	}

	/**
	 * @return array<string,ConfigureLandingTile>
	 */
	protected function getConfigureLandingTileLookup() :array {
		return $this->getConfigureLandingViewData()[ 'tile_lookup' ];
	}

	/**
	 * @return DiagnosisContract
	 */
	protected function getConfigureZoneDiagnosis( string $zoneKey ) :array {
		return $this->getConfigureLandingViewData()[ 'diagnoses' ][ $zoneKey ];
	}

	/**
	 * @return list<ZoneSection>
	 */
	protected function getConfigureZoneSections() :array {
		return $this->getConfigureLandingViewData()[ 'sections' ];
	}

	protected function renderConfigureZonesLayer() :string {
		return self::con()->comps->render
			->setTemplate( '/wpadmin/components/configure/layer_zones.twig' )
			->setData( [
				'sections' => $this->getConfigureZoneSections(),
			] )
			->render();
	}

	protected function renderConfigureDiagnosisLayer( string $zoneKey ) :string {
		return self::con()->comps->render
			->setTemplate( '/wpadmin/components/configure/layer_diagnosis.twig' )
			->setData( $this->buildConfigureDiagnosisRenderData( $zoneKey ) )
			->render();
	}

	protected function buildConfigureDiagnosisRenderData( string $zoneKey ) :array {
		$diagnosis = $this->getConfigureZoneDiagnosis( $zoneKey );

		return [
			'diagnosis'      => $diagnosis,
			'header'         => $diagnosis[ 'header' ],
			'zone_selection' => $diagnosis[ 'zone_selection' ],
		];
	}

	/**
	 * @return array{
	 *   root_step_json:string,
	 *   zones_html:string
	 * }
	 */
	protected function buildConfigureLandingRefresh() :array {
		return [
			'root_step_json' => $this->buildConfigureOperatorRootStepJson(),
			'zones_html'     => $this->renderConfigureZonesLayer(),
		];
	}

	/**
	 * @return OperatorChromeStep
	 */
	protected function buildConfigureOperatorRootStep() :array {
		return $this->getConfigureLandingViewData()[ 'root_step' ];
	}

	protected function buildConfigureOperatorRootStepJson() :string {
		return $this->getConfigureLandingViewData()[ 'root_step_json' ];
	}

	/**
	 * @return list<string>
	 */
	private function getConfigureDiagnosisRowKeys( string $zoneKey ) :array {
		$rows = \array_merge(
			$this->getConfigureZoneDiagnosis( $zoneKey )[ 'problem_rows' ],
			$this->getConfigureZoneDiagnosis( $zoneKey )[ 'review_rows' ],
			$this->getConfigureZoneDiagnosis( $zoneKey )[ 'healthy_rows' ]
		);

		return \array_values( \array_filter( \array_map(
			static fn( array $row ) :string => !empty( $row[ 'expand_action' ][ 'is_expandable' ] )
				? (string)( $row[ 'key' ] ?? '' )
				: '',
			$rows
		) ) );
	}
}
