<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\UiPreferences;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

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
		$lookup = $this->getConfigureLandingTileLookup();
		if ( isset( $lookup[ $zoneKey ] ) ) {
			return $zoneKey;
		}
		if ( $zoneKey === '' ) {
			return '';
		}

		// Links from before Configure was organised by goal name a zone; send them to the goal that now
		// holds the requested setting, or else the one that holds most of that zone's settings.
		$topic = ConfigureGoalPanels::topicForOption( $this->getRequestedConfigureItem() );
		$goalKey = $topic[ 'goal' ] ?? ( PluginNavs::configureLegacyZoneGoals()[ $zoneKey ] ?? '' );
		return isset( $lookup[ $goalKey ] ) ? $goalKey : '';
	}

	private function getRequestedConfigureItem() :string {
		$configItem = sanitize_key( $this->getTextInputFromRequestOrActionData( 'config_item' ) );
		return empty( self::con()->cfg->configuration->options[ $configItem ] ?? [] ) ? '' : $configItem;
	}

	/**
	 * An older link's row is a component; its settings now live in a topic of the goal.
	 */
	private function resolveLegacyConfigureRowKey( string $zoneKey, string $rowKey, string $configItem ) :string {
		$optionKey = $configItem;
		if ( $optionKey === '' && $rowKey !== '' ) {
			try {
				$optionKey = (string)( self::con()->comps->zones->getZoneComponent( $rowKey )->getOptions()[ 0 ] ?? '' );
			}
			catch ( \Exception $e ) {
				$optionKey = '';
			}
		}
		$topic = $optionKey === '' ? null : ConfigureGoalPanels::topicForOption( $optionKey );
		return ( $topic !== null && $topic[ 'goal' ] === $zoneKey ) ? $topic[ 'topic' ] : '';
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
			$configItem = $this->getRequestedConfigureItem();

			$rowFocusLookup = $this->getConfigureDiagnosisRowFocusLookup( $zoneKey );
			if ( ( $rowKey !== '' || $configItem !== '' ) && !isset( $rowFocusLookup[ $rowKey ] ) ) {
				$rowKey = $this->resolveLegacyConfigureRowKey( $zoneKey, $rowKey, $configItem );
			}
			if ( $rowKey !== '' && isset( $rowFocusLookup[ $rowKey ] ) ) {
				if ( $configItem !== '' && !\in_array( $configItem, $rowFocusLookup[ $rowKey ], true ) ) {
					$configItem = '';
				}

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
			// Built after the diagnosis above, so every component has recorded which option fixes each explanation.
			'explanation_fixes' => Component\Base::explanationFixLookup(),
			'show_me_label'  => __( 'Show me', 'wp-simple-firewall' ),
			'zone_tools'     => [
				'layout'             => ( new UiPreferences() )->get( UiPreferences::CONFIGURE_ZONE_LAYOUT ),
				'attention_count'    => \count( \array_filter(
					\array_merge( $diagnosis[ 'problem_rows' ], $diagnosis[ 'review_rows' ] ),
					static fn( array $row ) :bool => \in_array( $row[ 'status' ], [ 'warning', 'critical' ], true )
				) ),
				'filter_label'       => __( 'Filter settings in this zone', 'wp-simple-firewall' ),
				'filter_placeholder' => __( 'Filter settings', 'wp-simple-firewall' ),
				'filter_empty'       => __( 'No settings in this zone match.', 'wp-simple-firewall' ),
				'show_label'         => __( 'Show', 'wp-simple-firewall' ),
				'show_all'           => __( 'All', 'wp-simple-firewall' ),
				'show_attention'     => __( 'Needs attention', 'wp-simple-firewall' ),
				'layout_label'       => __( 'Layout', 'wp-simple-firewall' ),
				'layout_expand'      => __( 'Expand in place', 'wp-simple-firewall' ),
				'layout_list'        => __( 'Component list', 'wp-simple-firewall' ),
			],
			'savebar'        => [
				'label'        => __( 'Unsaved changes', 'wp-simple-firewall' ),
				/* translators: %s: number of unsaved changes */
				'count_one'    => __( '%s unsaved change', 'wp-simple-firewall' ),
				/* translators: %s: number of unsaved changes */
				'count_many'   => __( '%s unsaved changes', 'wp-simple-firewall' ),
				'discard'      => __( 'Discard', 'wp-simple-firewall' ),
				'save'         => __( 'Save changes', 'wp-simple-firewall' ),
				'saving'       => __( 'Saving...', 'wp-simple-firewall' ),
				'pin_mismatch' => __( 'The two Security Admin PIN fields don\'t match. Enter the same PIN in both.', 'wp-simple-firewall' ),
			],
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
	 * @return array<string,list<string>>
	 */
	private function getConfigureDiagnosisRowFocusLookup( string $zoneKey ) :array {
		$rows = \array_merge(
			$this->getConfigureZoneDiagnosis( $zoneKey )[ 'problem_rows' ],
			$this->getConfigureZoneDiagnosis( $zoneKey )[ 'review_rows' ],
			$this->getConfigureZoneDiagnosis( $zoneKey )[ 'healthy_rows' ]
		);

		$lookup = [];
		foreach ( $rows as $row ) {
			if ( empty( $row[ 'expand_action' ][ 'is_expandable' ] ) ) {
				continue;
			}

			$rowKey = $row[ 'key' ];
			$dataAttributes = $row[ 'expand_action' ][ 'data_attributes' ];
			$allowedOptionKeys = [];
			$configItem = sanitize_key( (string)( $dataAttributes[ 'config_item' ] ?? '' ) );
			if ( $configItem !== '' ) {
				$allowedOptionKeys[] = $configItem;
			}
			foreach ( \explode( ',', $dataAttributes[ 'option_keys' ] ) as $optionKey ) {
				$optionKey = sanitize_key( $optionKey );
				if ( $optionKey !== '' ) {
					$allowedOptionKeys[] = $optionKey;
				}
			}

			$lookup[ $rowKey ] = \array_values( \array_unique( $allowedOptionKeys ) );
		}

		return $lookup;
	}
}
