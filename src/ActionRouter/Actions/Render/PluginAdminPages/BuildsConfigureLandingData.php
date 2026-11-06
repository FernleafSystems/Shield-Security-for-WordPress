<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

/**
 * @phpstan-import-type ConfigureLandingTile from ConfigureLandingRenderContracts
 * @phpstan-import-type ConfigureLandingViewData from ConfigureLandingRenderContracts
 * @phpstan-import-type DiagnosisContract from ConfigureLandingRenderContracts
 * @phpstan-import-type OperatorChromeStep from OperatorChromeContract
 */
trait BuildsConfigureLandingData {

	private ?array $configureLandingViewDataCache = null;
	private ?array $configureFocusRequestCache = null;
	private ?array $configureTabsCache = null;

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
			elseif ( $configItem !== '' && !\in_array( $configItem, $rowFocusLookup[ $rowKey ] ?? [], true ) ) {
				// A link that names a setting goes to the topic that now holds it, even if it names another.
				$holdingRow = $this->resolveLegacyConfigureRowKey( $zoneKey, '', $configItem );
				$rowKey = $holdingRow !== '' ? $holdingRow : $rowKey;
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
	 * Configure's first layer is the Search tab's home: what needs attention, one goal to a row. The goals
	 * themselves are tabs.
	 */
	protected function renderConfigureZonesLayer() :string {
		return self::con()->comps->render
			->setTemplate( '/wpadmin/components/configure/layer_home.twig' )
			->setData( [
				'goals'   => \array_values( \array_filter(
					$this->buildConfigureTabs(),
					static fn( array $tab ) :bool => $tab[ 'needs_attention' ]
				) ),
				'strings' => [
					'attention_heading' => __( 'Needs attention', 'wp-simple-firewall' ),
					'attention_none'    => __( 'Nothing needs attention right now.', 'wp-simple-firewall' ),
				],
			] )
			->render();
	}

	/**
	 * One tab for each goal, in the goals' own order, carrying its icon, what opens it, its status, and the
	 * words of its settings so a filter can show which goals have anything that matches.
	 * @return list<array{key:string,label:string,icon_class:string,title:string,summary:string,status:string,needs_attention:bool,selection_json:string,search_text:string}>
	 */
	protected function buildConfigureTabs() :array {
		if ( $this->configureTabsCache !== null ) {
			return $this->configureTabsCache;
		}

		$view = $this->getConfigureLandingViewData();
		$labels = ConfigureGoalPanels::labels();
		$tabs = [];
		foreach ( ConfigureGoalPanels::goals() as $goalKey => $goal ) {
			$tile = $view[ 'tile_lookup' ][ $goalKey ] ?? null;
			if ( $tile === null || $tile[ 'is_disabled' ] ) {
				continue;
			}
			$tabs[] = [
				'key'             => $goalKey,
				'label'           => $labels[ 'goals' ][ $goalKey ][ 'tab' ] ?? $tile[ 'label' ],
				'icon_class'      => self::con()->svgs->iconClass( $goal[ 'icon' ] ),
				'title'           => $tile[ 'label' ],
				'summary'         => $tile[ 'summary' ],
				'status'          => $tile[ 'status' ],
				'needs_attention' => \in_array( $tile[ 'status' ], [ 'warning', 'critical' ], true ),
				'selection_json'  => $view[ 'diagnoses' ][ $goalKey ][ 'zone_selection_json' ],
				'search_text'     => $this->goalSearchText( $goalKey ),
			];
		}
		return $this->configureTabsCache = $tabs;
	}

	private function goalSearchText( string $goalKey ) :string {
		$labels = ConfigureGoalPanels::labels()[ 'topics' ];
		$strings = new StringsOptions();
		$words = [];
		foreach ( \array_keys( ConfigureGoalPanels::goals()[ $goalKey ][ 'topics' ] ) as $topicKey ) {
			$words[] = $labels[ $topicKey ][ 'label' ] ?? '';
			foreach ( ConfigureGoalPanels::topicOptionKeys( $goalKey, $topicKey ) as $optionKey ) {
				try {
					$optionStrings = $strings->getFor( $optionKey );
					$words[] = (string)$optionStrings[ 'name' ];
					$words[] = (string)( $optionStrings[ 'summary' ] ?? '' );
				}
				catch ( \Exception $e ) {
				}
			}
		}
		return \strtolower( \implode( ' ', \array_filter( $words ) ) );
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
			'goal_summary'   => ConfigureGoalPanels::labels()[ 'goals' ][ $zoneKey ][ 'summary' ] ?? '',
			'header'         => $diagnosis[ 'header' ],
			'zone_selection' => $diagnosis[ 'zone_selection' ],
			// Built after the diagnosis above, so every component has recorded which option fixes each explanation.
			'explanation_fixes' => Component\Base::explanationFixLookup(),
			'show_me_label'  => __( 'Show me', 'wp-simple-firewall' ),
			'is_embedded'    => false,
			'zone_tools'     => [
				'attention_count'    => \count( \array_filter(
					\array_merge( $diagnosis[ 'problem_rows' ], $diagnosis[ 'review_rows' ] ),
					static fn( array $row ) :bool => \in_array( $row[ 'status' ], [ 'warning', 'critical' ], true )
				) ),
				'filter_label'       => __( "Filter this goal's settings", 'wp-simple-firewall' ),
				'filter_placeholder' => __( 'Type to filter settings', 'wp-simple-firewall' ),
				'filter_empty'       => __( 'No settings in this goal match.', 'wp-simple-firewall' ),
				'show_label'         => __( 'Show', 'wp-simple-firewall' ),
				'show_all'           => __( 'All', 'wp-simple-firewall' ),
				'show_attention'     => __( 'Needs attention', 'wp-simple-firewall' ),
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
	 *   zones_html:string,
	 *   tab_states:array<string,array{status:string,needs_attention:bool}>
	 * }
	 */
	protected function buildConfigureLandingRefresh() :array {
		$tabStates = [];
		foreach ( $this->buildConfigureTabs() as $tab ) {
			$tabStates[ $tab[ 'key' ] ] = [
				'status'          => $tab[ 'status' ],
				'needs_attention' => $tab[ 'needs_attention' ],
			];
		}
		return [
			'root_step_json' => $this->buildConfigureOperatorRootStepJson(),
			'zones_html'     => $this->renderConfigureZonesLayer(),
			'tab_states'     => $tabStates,
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
