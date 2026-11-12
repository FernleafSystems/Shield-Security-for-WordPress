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
 * @phpstan-type ScoredConfigureSearchResult array{
 *   type:'zone'|'option',
 *   icon_class:string,
 *   label:string,
 *   summary:string,
 *   path:string,
 *   value:string,
 *   selection_json:string,
 *   focus_request_json:string,
 *   href:string,
 *   score:int
 * }
 */
class ConfigureSearchResultsBuilder {

	use PluginControllerConsumer;

	/**
	 * Name matches outrank summary matches, which outrank matches found only in descriptions
	 * or section text, so "email" lists "Report Email" ahead of options that only mention email.
	 */
	private const WEIGHT_NAME = 10;
	private const WEIGHT_SUMMARY = 4;
	private const WEIGHT_ANY = 1;
	private const BONUS_NAME_PHRASE = 20;

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
		$search = sanitize_text_field( $search );
		$terms = $this->searchTextTokenBuilder->extractTerms( $search );
		if ( empty( $terms ) ) {
			return [];
		}

		$results = \array_merge(
			$this->buildOptionResults( $terms, $search ),
			$this->buildZoneResults( $terms, $search )
		);

		\usort( $results, function ( array $a, array $b ) :int {
			$scoreCompare = $b[ 'score' ] <=> $a[ 'score' ];
			if ( $scoreCompare !== 0 ) {
				return $scoreCompare;
			}

			$typeCompare = $this->typePriority( $a[ 'type' ] )
				<=> $this->typePriority( $b[ 'type' ] );
			if ( $typeCompare !== 0 ) {
				return $typeCompare;
			}

			// Among equal scores a shorter name is the more specific match: "Report Email" before "Allow Email Override".
			$lengthCompare = \strlen( $a[ 'label' ] ) <=> \strlen( $b[ 'label' ] );
			if ( $lengthCompare !== 0 ) {
				return $lengthCompare;
			}

			return \strcasecmp( $a[ 'label' ], $b[ 'label' ] );
		} );

		return \array_values( \array_map( static function ( array $result ) :array {
			unset( $result[ 'score' ] );
			return $result;
		}, $results ) );
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

	/**
	 * @return list<ScoredConfigureSearchResult>
	 */
	private function buildZoneResults( array $terms, string $search ) :array {
		$results = [];
		$landingViewData = $this->getConfigureLandingViewData();

		foreach ( $landingViewData[ 'diagnoses' ] as $diagnosis ) {
			$zoneSummary = $this->requireZoneSummary( $diagnosis[ 'zone_key' ] );
			$score = $this->weightedScore(
				$terms,
				$search,
				$diagnosis[ 'zone_label' ],
				$zoneSummary,
				$this->buildZoneTokens( $diagnosis, $zoneSummary )
			);
			if ( $score < 1 ) {
				continue;
			}

			$results[] = [
				'type'       => 'zone',
				'icon_class' => $diagnosis[ 'zone_icon_class' ],
				'label'      => $diagnosis[ 'zone_label' ],
				'summary'    => $zoneSummary,
				'path'       => '',
				'value'      => '',
				'selection_json'     => $diagnosis[ 'zone_selection_json' ],
				'focus_request_json' => '',
				'href'       => self::con()->plugin_urls->configureHome( $diagnosis[ 'zone_key' ] ),
				'score'      => $score,
			];
		}

		return $results;
	}

	private function requireZoneSummary( string $zoneKey ) :string {
		$tile = $this->getConfigureLandingViewData()[ 'tile_lookup' ][ $zoneKey ] ?? null;
		if ( !\is_array( $tile ) ) {
			throw new \LogicException( 'Configure search zone results require a landing tile for every diagnosis: '.$zoneKey );
		}

		$summary = \trim( (string)( $tile[ 'summary' ] ?? '' ) );
		if ( $summary === '' ) {
			throw new \LogicException( 'Configure search zone results require a non-empty tile summary: '.$zoneKey );
		}

		return $summary;
	}

	private function buildZoneTokens( array $diagnosis, string $zoneSummary ) :string {
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
				$zoneSummary,
				$diagnosis[ 'preview_text' ],
				$diagnosis[ 'risk_context' ],
			],
			$rowTexts
		) );
	}

	/**
	 * @return list<ScoredConfigureSearchResult>
	 */
	private function buildOptionResults( array $terms, string $search ) :array {
		$results = [];
		$stringsOptions = new StringsOptions();

		foreach ( $this->buildOptionFocusLookup() as $optionKey => $focusTarget ) {
			try {
				$optionStrings = $stringsOptions->getFor( $optionKey );
			}
			catch ( \Exception $e ) {
				continue;
			}

			$score = $this->weightedScore(
				$terms,
				$search,
				(string)$optionStrings[ 'name' ],
				(string)( $optionStrings[ 'summary' ] ?? '' ),
				$this->buildOptionTokens( $optionKey )
			);
			if ( $score < 1 ) {
				continue;
			}

			$results[] = [
				'type'       => 'option',
				'icon_class' => self::con()->svgs->iconClass( 'sliders' ),
				'label'      => $optionStrings[ 'name' ],
				'summary'    => $this->buildOptionSummary( $focusTarget[ 'row_title' ], $optionStrings ),
				'path'       => \implode( ' › ', \array_filter( [ $focusTarget[ 'zone_label' ], \trim( $focusTarget[ 'row_title' ] ) ] ) ),
				'value'      => $this->currentValueLabel( $optionKey ),
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

	/**
	 * Returns 0 when no term appears anywhere, so callers keep dropping non-matches with "score < 1".
	 * @param list<string> $terms
	 */
	private function weightedScore( array $terms, string $search, string $name, string $summary, string $allTokens ) :int {
		$tokens = $this->searchTextTokenBuilder;
		$any = $tokens->countMatches( $allTokens, $terms );
		if ( $any < 1 ) {
			return 0;
		}

		$score = $any*self::WEIGHT_ANY
				 + $tokens->countMatches( $tokens->build( [ $name ] ), $terms )*self::WEIGHT_NAME
				 + $tokens->countMatches( $tokens->build( [ $summary ] ), $terms )*self::WEIGHT_SUMMARY;

		$phrase = \strtolower( $search );
		if ( $phrase !== '' && \str_contains( \strtolower( $name ), $phrase ) ) {
			$score += self::BONUS_NAME_PHRASE;
		}

		return $score;
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

	/**
	 * A short reading of the option's current setting, shown beside the result so the user can see
	 * where it stands before opening it. Secrets and long values are left out.
	 */
	private function currentValueLabel( string $optionKey ) :string {
		$def = self::con()->opts->optDef( $optionKey );
		$value = self::con()->opts->optGet( $optionKey );
		switch ( (string)( $def[ 'type' ] ?? '' ) ) {
			case 'checkbox':
				return $value === 'Y' ? __( 'On', 'wp-simple-firewall' ) : __( 'Off', 'wp-simple-firewall' );
			case 'select':
				foreach ( $def[ 'value_options' ] ?? [] as $valueOption ) {
					if ( (string)( $valueOption[ 'value_key' ] ?? '' ) === (string)$value ) {
						return (string)( $valueOption[ 'text' ] ?? '' );
					}
				}
				return '';
			case 'multiple_select':
			case 'array':
				return \is_array( $value ) && !empty( $value )
					/* translators: %s: number of selected items */
					? \sprintf( _n( '%s selected', '%s selected', \count( $value ), 'wp-simple-firewall' ), \count( $value ) )
					: '';
			case 'text':
			case 'email':
			case 'integer':
				$text = \is_scalar( $value ) ? \trim( (string)$value ) : '';
				return \strlen( $text ) > 32 ? \substr( $text, 0, 31 ).'…' : $text;
			default:
				return '';
		}
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
	 *   zone_label:string,
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
				if ( !$expandAction[ 'is_expandable' ] ) {
					continue;
				}

				$dataAttributes = $expandAction[ 'data_attributes' ];
				$rowKey = $row[ 'key' ];
				$optionKeys = $this->normalizeCsvString( $dataAttributes[ 'option_keys' ] );
				$configItem = $dataAttributes[ 'config_item' ] ?? '';

				$this->assignOptionTargets(
					$lookup,
					$this->extractOptionKeysForRow( $eligibleOptionKeys, $optionKeys, $configItem ),
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
		string $optionKeys,
		string $configItem
	) :array {
		$candidates = [];

		if ( $configItem !== '' && isset( $eligibleOptionKeys[ $configItem ] ) ) {
			$candidates[ $configItem ] = 4;
		}

		foreach ( $this->extractCsvValues( $optionKeys ) as $optionKey ) {
			if ( isset( $eligibleOptionKeys[ $optionKey ] ) && !isset( $candidates[ $optionKey ] ) ) {
				$candidates[ $optionKey ] = 3;
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
				'zone_label' => $diagnosis[ 'zone_label' ],
				'row_key'    => $rowKey,
				'row_title'  => $row[ 'title' ],
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
