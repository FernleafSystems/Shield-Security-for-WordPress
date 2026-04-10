<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsSections;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SearchTextTokenBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 * @phpstan-import-type ConfigureLandingViewData from ConfigureLandingRenderContracts
 * @phpstan-import-type ConfigureSearchResult from ConfigureLandingRenderContracts
 * @phpstan-import-type DiagnosisContract from ConfigureLandingRenderContracts
 * @phpstan-import-type DiagnosisFinding from ConfigureLandingRenderContracts
 */
class ConfigureSearchResultsBuilder {

	use PluginControllerConsumer;

	private ConfigureLandingViewBuilder $landingViewBuilder;
	private SearchTextTokenBuilder $searchTextTokenBuilder;
	private ?array $configureLandingViewDataCache = null;

	public function __construct(
		?ConfigureLandingViewBuilder $landingViewBuilder = null,
		?SearchTextTokenBuilder $searchTextTokenBuilder = null
	) {
		$this->landingViewBuilder = $landingViewBuilder ?? new ConfigureLandingViewBuilder();
		$this->searchTextTokenBuilder = $searchTextTokenBuilder ?? new SearchTextTokenBuilder();
	}

	/**
	 * @return list<ConfigureSearchResult>
	 */
	public function build( string $search ) :array {
		$terms = $this->extractSearchTerms( $search );
		if ( empty( $terms ) ) {
			return [];
		}

		$results = \array_merge(
			$this->buildOptionResults( $terms ),
			$this->buildZoneResults( $terms )
		);

		\usort( $results, function ( array $a, array $b ) :int {
			$scoreCompare = ( $b[ 'score' ] ?? 0 ) <=> ( $a[ 'score' ] ?? 0 );
			if ( $scoreCompare !== 0 ) {
				return $scoreCompare;
			}

			$typeCompare = $this->typePriority( (string)( $a[ 'type' ] ?? '' ) )
				<=> $this->typePriority( (string)( $b[ 'type' ] ?? '' ) );
			if ( $typeCompare !== 0 ) {
				return $typeCompare;
			}

			return \strcasecmp( (string)( $a[ 'label' ] ?? '' ), (string)( $b[ 'label' ] ?? '' ) );
		} );

		return \array_values( \array_map( static function ( array $result ) :array {
			unset( $result[ 'score' ] );
			return $result;
		}, $results ) );
	}

	/**
	 * @return list<string>
	 */
	private function extractSearchTerms( string $search ) :array {
		return \array_values( \array_filter( \array_unique( \array_map(
			static function ( string $term ) :string {
				$term = \strtolower( \trim( $term ) );
				return \strlen( $term ) > 2 ? $term : '';
			},
			\explode( ' ', sanitize_text_field( $search ) )
		) ) ) );
	}

	/**
	 * @return ConfigureLandingViewData
	 */
	private function getConfigureLandingViewData() :array {
		if ( $this->configureLandingViewDataCache === null ) {
			$this->configureLandingViewDataCache = $this->landingViewBuilder->build();
		}
		return $this->configureLandingViewDataCache;
	}

	private function buildZoneResults( array $terms ) :array {
		$results = [];

		foreach ( $this->getConfigureLandingViewData()[ 'diagnoses' ] as $diagnosis ) {
			$tokens = $this->buildZoneTokens( $diagnosis );
			$score = $this->searchString( $tokens, $terms );
			if ( $score < 1 ) {
				continue;
			}

			$results[] = [
				'type'       => 'zone',
				'icon_class' => $diagnosis[ 'zone_icon_class' ],
				'label'      => $diagnosis[ 'zone_label' ],
				'summary'    => $diagnosis[ 'preview_text' ] !== ''
					? $diagnosis[ 'preview_text' ]
					: $diagnosis[ 'risk_context' ],
				'selection_json'     => $diagnosis[ 'zone_selection_json' ],
				'focus_request_json' => '',
				'href'       => self::con()->plugin_urls->configureHome( $diagnosis[ 'zone_key' ] ),
				'score'      => $score,
			];
		}

		return $results;
	}

	private function buildZoneTokens( array $diagnosis ) :string {
		$rowTexts = [];
		foreach ( $this->getDiagnosisRowsInSearchOrder( $diagnosis ) as $row ) {
			$rowTexts[] = $row[ 'title' ];
			$rowTexts[] = $row[ 'summary' ];
			foreach ( $row[ 'explanations' ] as $explanation ) {
				$rowTexts[] = $explanation;
			}
		}

		return $this->searchTextTokenBuilder->build( \array_merge(
			[
				$diagnosis[ 'zone_label' ],
				$diagnosis[ 'preview_text' ],
				$diagnosis[ 'risk_context' ],
			],
			$rowTexts
		) );
	}

	private function buildOptionResults( array $terms ) :array {
		$results = [];
		$stringsOptions = new StringsOptions();

		foreach ( $this->buildOptionFocusLookup() as $optionKey => $focusTarget ) {
			try {
				$optionStrings = $stringsOptions->getFor( $optionKey );
			}
			catch ( \Exception $e ) {
				continue;
			}

			$score = $this->searchString(
				$this->buildOptionTokens( $optionKey ),
				$terms
			);
			if ( $score < 1 ) {
				continue;
			}

			$results[] = [
				'type'       => 'option',
				'icon_class' => self::con()->svgs->iconClass( 'sliders' ),
				'label'      => $optionStrings[ 'name' ],
				'summary'    => $this->buildOptionSummary( $focusTarget[ 'row_title' ], $optionStrings ),
				'selection_json'     => $focusTarget[ 'selection_json' ],
				'focus_request_json' => $this->buildFocusRequestJson( $optionKey, $focusTarget ),
				'href'       => URL::Build(
					self::con()->plugin_urls->configureHome( $focusTarget[ 'zone_key' ] ),
					$this->buildFocusQueryArgs( $optionKey, $focusTarget )
				),
				'score'      => $score,
			];
		}

		return $results;
	}

	private function buildOptionTokens( string $optionKey ) :string {
		$optDef = self::con()->opts->optDef( $optionKey );
		if ( empty( $optDef ) ) {
			return '';
		}

		$optionStrings = ( new StringsOptions() )->getFor( $optionKey );
		$sectionStrings = ( new StringsSections() )->getFor( (string)( $optDef[ 'section' ] ?? '' ) );
		$description = $optionStrings[ 'description' ];

		return $this->searchTextTokenBuilder->build( \array_merge(
			[
				$optionStrings[ 'name' ],
				$optionStrings[ 'summary' ],
				\is_array( $description ) ? \implode( ' ', $description ) : (string)$description,
				$sectionStrings[ 'title' ],
				$sectionStrings[ 'title_short' ],
			],
			$sectionStrings[ 'summary' ]
		) );
	}

	private function buildOptionSummary( string $rowTitle, array $optionStrings ) :string {
		$summary = \trim( (string)( $optionStrings[ 'summary' ] ?? '' ) );
		if ( $summary !== '' && \strcasecmp( $summary, (string)( $optionStrings[ 'name' ] ?? '' ) ) !== 0 ) {
			return $summary;
		}

		$description = $optionStrings[ 'description' ] ?? [];
		$descriptionSummary = \trim( \is_array( $description ) ? (string)( $description[ 0 ] ?? '' ) : (string)$description );
		return $descriptionSummary !== ''
			? $descriptionSummary
			: \trim( $rowTitle );
	}

	private function buildFocusQueryArgs( string $optionKey, array $focusTarget ) :array {
		return [
			'row_key'     => $focusTarget[ 'row_key' ],
			'config_item' => $optionKey,
		];
	}

	private function buildFocusRequestJson( string $optionKey, array $focusTarget ) :string {
		return OperatorChromeContract::encodeJson(
			$this->buildFocusQueryArgs( $optionKey, $focusTarget )
		);
	}

	/**
	 * @return array<string,array{
	 *   zone_key:string,
	 *   row_key:string,
	 *   row_title:string,
	 *   selection_json:string,
	 *   priority:int
	 * }>
	 */
	private function buildOptionFocusLookup() :array {
		$lookup = [];
		$eligibleOptionKeys = $this->getEligibleOptionKeys();

		foreach ( $this->getConfigureLandingViewData()[ 'diagnoses' ] as $diagnosis ) {
			foreach ( $this->getDiagnosisRowsInSearchOrder( $diagnosis ) as $row ) {
				$expandAction = $row[ 'expand_action' ];
				if ( empty( $expandAction[ 'is_expandable' ] ) ) {
					continue;
				}

				$dataAttributes = $expandAction[ 'data_attributes' ] ?? [];
				$rowKey = (string)( $row[ 'key' ] ?? '' );
				$zoneComponentSlug = $this->normalizeCsvString( (string)( $dataAttributes[ 'zone_component_slug' ] ?? '' ) );
				$optionKeys = $this->normalizeCsvString( (string)( $dataAttributes[ 'option_keys' ] ?? '' ) );
				$configItem = (string)( $dataAttributes[ 'config_item' ] ?? '' );
				if ( $rowKey === '' ) {
					throw new \LogicException( 'Configure search rows require a producer-owned non-empty row key.' );
				}

				$this->assignOptionTargets(
					$lookup,
					$this->extractOptionKeysForRow( $eligibleOptionKeys, $zoneComponentSlug, $optionKeys, $configItem ),
					$diagnosis,
					$row,
					$rowKey
				);
			}
		}

		return $lookup;
	}

	/**
	 * @return list<DiagnosisFinding>
	 */
	private function getDiagnosisRowsInSearchOrder( array $diagnosis ) :array {
		return \array_merge(
			$diagnosis[ 'problem_rows' ],
			$diagnosis[ 'review_rows' ],
			$diagnosis[ 'healthy_rows' ]
		);
	}

	/**
	 * @param array<string,bool> $eligibleOptionKeys
	 * @return array<string,int>
	 */
	private function extractOptionKeysForRow(
		array $eligibleOptionKeys,
		string $zoneComponentSlug,
		string $optionKeys,
		string $configItem
	) :array {
		$candidates = [];

		if ( $configItem !== '' && isset( $eligibleOptionKeys[ $configItem ] ) ) {
			$candidates[ $configItem ] = 3;
		}

		foreach ( $this->extractCsvValues( $optionKeys ) as $optionKey ) {
			if ( isset( $eligibleOptionKeys[ $optionKey ] ) && !isset( $candidates[ $optionKey ] ) ) {
				$candidates[ $optionKey ] = 2;
			}
		}

		if ( $zoneComponentSlug !== '' ) {
			foreach ( $this->getOptionsForZoneComponentSlugs( $this->extractCsvValues( $zoneComponentSlug ) ) as $optionKey ) {
				if ( isset( $eligibleOptionKeys[ $optionKey ] ) && !isset( $candidates[ $optionKey ] ) ) {
					$candidates[ $optionKey ] = 1;
				}
			}
		}

		return $candidates;
	}

	private function assignOptionTargets(
		array &$lookup,
		array $optionPriorities,
		array $diagnosis,
		array $row,
		string $rowKey
	) :void {
		foreach ( $optionPriorities as $optionKey => $priority ) {
			$existingPriority = $lookup[ $optionKey ][ 'priority' ] ?? -1;
			if ( $existingPriority > $priority ) {
				continue;
			}
			if ( $existingPriority === $priority ) {
				continue;
			}

			$lookup[ $optionKey ] = [
				'zone_key'   => $diagnosis[ 'zone_key' ],
				'row_key'    => $rowKey,
				'row_title'  => (string)( $row[ 'title' ] ?? '' ),
				'selection_json' => $diagnosis[ 'zone_selection_json' ],
				'priority'   => $priority,
			];
		}
	}

	/**
	 * @return array<string,bool>
	 */
	private function getEligibleOptionKeys() :array {
		$optionKeys = \array_keys( \array_filter(
			self::con()->cfg->configuration->options,
			static fn( array $optDef ) :bool => !\in_array( $optDef[ 'section' ] ?? '', [ 'section_hidden', 'section_deprecated' ], true )
		) );

		return \array_fill_keys( $optionKeys, true );
	}

	/**
	 * @param list<string> $zoneComponentSlugs
	 * @return list<string>
	 */
	private function getOptionsForZoneComponentSlugs( array $zoneComponentSlugs ) :array {
		if ( empty( $zoneComponentSlugs ) ) {
			return [];
		}

		return \array_values( \array_keys( \array_filter(
			self::con()->cfg->configuration->options,
			static function ( array $optionDef ) use ( $zoneComponentSlugs ) :bool {
				$ownerSlugs = \array_filter( $optionDef[ 'zone_comp_slugs' ] ?? [], 'is_string' );
				return \count( \array_intersect( $zoneComponentSlugs, $ownerSlugs ) ) > 0;
			}
		) ) );
	}

	private function searchString( string $haystack, array $needles ) :int {
		return \count( \array_intersect(
			$needles,
			\array_map( '\trim', \explode( ' ', \strtolower( $haystack ) ) )
		) );
	}

	private function typePriority( string $type ) :int {
		return $type === 'zone' ? 0 : 1;
	}

	private function normalizeCsvString( string $value ) :string {
		return \implode( ',', $this->extractCsvValues( $value ) );
	}

	/**
	 * @return list<string>
	 */
	private function extractCsvValues( string $value ) :array {
		$values = \array_filter( \array_map(
			static fn( string $item ) :string => $item,
			\explode( ',', $value )
		) );

		return \array_values( \array_unique( $values ) );
	}
}
